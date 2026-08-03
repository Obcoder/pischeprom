#!/usr/bin/env bash

set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

[[ $# -eq 1 ]] || gis_fail 'Usage: publish-valhalla-artifacts.sh russia-YYYYMMDD'
release="$1"
gis_validate_release "$release"

for command_name in cmp curl find flock install mktemp mv php sha256sum sort; do
    gis_require_command "$command_name"
done

storage_cli="${GIS_OBJECT_STORAGE_CLI:-aws}"
case "$storage_cli" in
    yc)
        gis_require_command yc
        [[ -n "${YC_IAM_TOKEN:-}" ]] \
            || gis_fail 'YC_IAM_TOKEN is required for the short-lived Yandex CLI publication session.'
        ;;
    aws)
        gis_require_command aws
        ;;
    *)
        gis_fail 'GIS_OBJECT_STORAGE_CLI must be aws or yc.'
        ;;
esac

target="${GIS_RELEASES_DIR}/${release}"
[[ -d "$target" && ! -L "$target" ]] || gis_fail 'Verified release directory is unavailable.'
target="$(gis_assert_inside_base "$target")"
manifest="${target}/release-manifest.json"
valhalla="${target}/valhalla"
[[ -s "$manifest" && ! -L "$manifest" && -d "$valhalla" && ! -L "$valhalla" ]] \
    || gis_fail 'Verified release manifest and Valhalla directory are required.'
[[ -z "$(find "$valhalla" -type l -print -quit)" ]] \
    || gis_fail 'Valhalla artifact directory contains a forbidden symlink.'

mapfile -t manifest_values < <(MANIFEST="$manifest" RELEASE="$release" php -r '
    $v=json_decode((string)file_get_contents(getenv("MANIFEST")),true,flags:JSON_THROW_ON_ERROR);
    $graph=$v["valhalla"]??[];
    if(($v["status"]??null)!=="verified"||($v["release"]??null)!==getenv("RELEASE"))exit(1);
    echo (string)($graph["graph_sha256"]??""),PHP_EOL,(int)($graph["graph_size_bytes"]??0),PHP_EOL;
')
expected_graph_sha="${manifest_values[0]:-}"
expected_graph_size="${manifest_values[1]:-0}"
graph="${valhalla}/valhalla_tiles.tar"
[[ "$expected_graph_sha" =~ ^[0-9a-f]{64}$ && "$expected_graph_size" -gt 0 \
    && -s "$graph" && ! -L "$graph" && "$(gis_sha256 "$graph")" == "$expected_graph_sha" ]] \
    || gis_fail 'Valhalla graph does not match the verified release manifest.'

declare -a relative_paths=(
    release-manifest.json
    valhalla/component-manifest.json
    valhalla/valhalla.json
    valhalla/valhalla_tiles.tar
    valhalla/tiles/admins.sqlite
    valhalla/tiles/timezones.sqlite
    smoke-tests.json
)
declare -a sources=()
declare -a keys=()
declare -a checksums=()
declare -a sizes=()
declare -a content_types=()
declare -A expected_keys=()

bucket="${GIS_PRIVATE_OBJECT_STORAGE_BUCKET:-}"
prefix="${GIS_PRIVATE_OBJECT_STORAGE_PREFIX:-logistics-gis}"
[[ "$bucket" =~ ^[A-Za-z0-9][A-Za-z0-9._-]{1,61}[A-Za-z0-9]$ ]] \
    || gis_fail 'GIS_PRIVATE_OBJECT_STORAGE_BUCKET is missing or invalid.'
[[ "$prefix" =~ ^[A-Za-z0-9][A-Za-z0-9._/-]*[A-Za-z0-9]$ \
    && "/${prefix}/" != *"/../"* && "$prefix" != *"//"* ]] \
    || gis_fail 'GIS_PRIVATE_OBJECT_STORAGE_PREFIX must be a safe non-empty object prefix.'
release_prefix="${prefix}/releases/${release}"
marker_key="${release_prefix}/private-publication.json"

for relative in "${relative_paths[@]}"; do
    source="${target}/${relative}"
    [[ -s "$source" && -f "$source" && ! -L "$source" ]] \
        || gis_fail "Required Valhalla artifact is unavailable: ${relative}"
    key="${release_prefix}/${relative}"
    case "$relative" in
        *.json) content_type='application/json' ;;
        *.sqlite) content_type='application/vnd.sqlite3' ;;
        *.tar) content_type='application/x-tar' ;;
        *) content_type='application/octet-stream' ;;
    esac
    sources+=("$source")
    keys+=("$key")
    checksums+=("$(gis_sha256 "$source")")
    sizes+=("$(gis_file_size "$source")")
    content_types+=("$content_type")
    expected_keys["$key"]=1
done
expected_keys["$marker_key"]=1

for directory in "$GIS_LOCK_DIR" "$GIS_STATE_DIR"; do
    [[ -d "$directory" && -w "$directory" ]] || gis_fail "Required writable directory is missing: ${directory}"
    gis_assert_inside_base "$directory" >/dev/null
done
exec 9>"${GIS_LOCK_DIR}/gis-private-publish.lock"
flock -n 9 || gis_fail 'Another private GIS publication is already running.'

aws_options=()
if [[ -n "${GIS_OBJECT_STORAGE_ENDPOINT:-}" ]]; then
    [[ "${GIS_OBJECT_STORAGE_ENDPOINT}" =~ ^https://[^/?#]+/?$ ]] \
        || gis_fail 'GIS_OBJECT_STORAGE_ENDPOINT must be an HTTPS origin.'
    aws_options+=(--endpoint-url "${GIS_OBJECT_STORAGE_ENDPOINT%/}")
fi
if [[ -n "${GIS_OBJECT_STORAGE_REGION:-}" ]]; then
    aws_options+=(--region "$GIS_OBJECT_STORAGE_REGION")
fi

list_release_keys() {
    if [[ "$storage_cli" == 'yc' ]]; then
        yc storage s3api list-objects \
            --bucket "$bucket" \
            --prefix "${release_prefix}/" \
            --format json
    else
        aws "${aws_options[@]}" s3api list-objects-v2 \
            --bucket "$bucket" --prefix "${release_prefix}/" --output json
    fi
}

listing="$(list_release_keys)" || gis_fail 'Unable to inspect the immutable private release prefix.'
mapfile -t existing_keys < <(LISTING="$listing" php -r '
    $v=json_decode((string)getenv("LISTING"),true,flags:JSON_THROW_ON_ERROR);
    foreach(($v["Contents"]??$v["contents"]??[]) as $item){
        $key=$item["Key"]??$item["key"]??null;
        if(is_string($key))echo $key,PHP_EOL;
    }
')
declare -A existing_key_set=()
marker_exists=false
for key in "${existing_keys[@]}"; do
    [[ -n "$key" ]] || continue
    [[ -n "${expected_keys[$key]:-}" ]] \
        || gis_fail "Immutable private release prefix contains an unexpected object: ${key}"
    existing_key_set["$key"]=1
    [[ "$key" != "$marker_key" ]] || marker_exists=true
done
if $marker_exists; then
    for key in "${keys[@]}"; do
        [[ -n "${existing_key_set[$key]:-}" ]] \
            || gis_fail 'Private publication marker exists but its immutable artifact set is incomplete.'
    done
fi

upload_file() {
    local source="$1" key="$2" content_type="$3" checksum="$4"
    if [[ "$storage_cli" == 'yc' ]]; then
        yc storage s3 cp "$source" "s3://${bucket}/${key}" \
            --only-show-errors \
            --content-type "$content_type" \
            --cache-control 'private,max-age=31536000,immutable' \
            --metadata "sha256=${checksum},release=${release}"
    else
        aws "${aws_options[@]}" s3 cp "$source" "s3://${bucket}/${key}" \
            --only-show-errors \
            --content-type "$content_type" \
            --cache-control 'private,max-age=31536000,immutable' \
            --metadata "sha256=${checksum},release=${release}"
    fi
}

download_file() {
    local key="$1" destination="$2"
    if [[ "$storage_cli" == 'yc' ]]; then
        yc storage s3 cp "s3://${bucket}/${key}" "$destination" --only-show-errors
    else
        aws "${aws_options[@]}" s3 cp "s3://${bucket}/${key}" "$destination" --only-show-errors
    fi
}

validate_existing_object() {
    local key="$1" content_type="$2" checksum="$3" size="$4"
    local head_json matches
    if [[ "$storage_cli" == 'yc' ]]; then
        head_json="$(yc storage s3api head-object --bucket "$bucket" --key "$key" --format json)" \
            || gis_fail "Unable to inspect existing immutable private object: ${key}"
    else
        head_json="$(aws "${aws_options[@]}" s3api head-object --bucket "$bucket" --key "$key" --output json)" \
            || gis_fail "Unable to inspect existing immutable private object: ${key}"
    fi
    matches="$(HEAD_JSON="$head_json" TYPE="$content_type" SHA="$checksum" SIZE="$size" RELEASE="$release" php -r '
        $v=json_decode((string)getenv("HEAD_JSON"),true,flags:JSON_THROW_ON_ERROR);
        $metadata=array_change_key_case((array)($v["Metadata"]??$v["metadata"]??[]),CASE_LOWER);
        $valid=(int)($v["ContentLength"]??$v["content_length"]??-1)===(int)getenv("SIZE")
            &&strtolower((string)($v["ContentType"]??$v["content_type"]??""))===strtolower(getenv("TYPE"))
            &&(string)($v["CacheControl"]??$v["cache_control"]??"")==="private,max-age=31536000,immutable"
            &&strtolower((string)($metadata["sha256"]??""))===getenv("SHA")
            &&(string)($metadata["release"]??"")===getenv("RELEASE");
        echo $valid?"yes":"no";
    ')"
    [[ "$matches" == 'yes' ]] \
        || gis_fail "Existing immutable private object differs from the verified release: ${key}"
}

gis_log "Publishing/resuming private Valhalla artifacts for ${release}; existing objects are never overwritten."
for index in "${!keys[@]}"; do
    if [[ -n "${existing_key_set[${keys[$index]}]:-}" ]]; then
        validate_existing_object "${keys[$index]}" "${content_types[$index]}" "${checksums[$index]}" "${sizes[$index]}"
    else
        upload_file "${sources[$index]}" "${keys[$index]}" "${content_types[$index]}" "${checksums[$index]}"
    fi
done

work_dir="$(mktemp -d "${GIS_STATE_DIR}/private-publication.XXXXXXXX")"
cleanup() { rm -r -- "$work_dir"; }
trap cleanup EXIT
inventory="${work_dir}/inventory.tsv"
for index in "${!keys[@]}"; do
    printf '%s\t%s\t%s\t%s\n' \
        "${relative_paths[$index]}" "${keys[$index]}" "${checksums[$index]}" "${sizes[$index]}" \
        >> "$inventory"
done
marker="${work_dir}/private-publication.json"
if $marker_exists; then
    download_file "$marker_key" "$marker"
    MARKER="$marker" INVENTORY="$inventory" RELEASE="$release" GRAPH_SHA="$expected_graph_sha" php -r '
        $v=json_decode((string)file_get_contents(getenv("MARKER")),true,flags:JSON_THROW_ON_ERROR);
        $expected=[];
        foreach(file(getenv("INVENTORY"),FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){
            [$path,$key,$sha,$size]=explode("\t",$line);
            $expected[$path]=["key"=>$key,"sha256"=>$sha,"size_bytes"=>(int)$size];
        }
        $actual=[];
        foreach(($v["artifacts"]??[]) as $item){if(is_array($item)&&is_string($item["path"]??null))$actual[$item["path"]]=array_intersect_key($item,["key"=>1,"sha256"=>1,"size_bytes"=>1]);}
        exit(($v["status"]??null)==="verified"&&($v["release"]??null)===getenv("RELEASE")
            &&($v["graph_sha256"]??null)===getenv("GRAPH_SHA")&&$actual===$expected?0:1);
    ' || gis_fail 'Existing private publication marker differs from this verified release.'
else
    MARKER="$marker" INVENTORY="$inventory" RELEASE="$release" GRAPH_SHA="$expected_graph_sha" php -r '
        $artifacts=[];
        foreach(file(getenv("INVENTORY"),FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){
            [$path,$key,$sha,$size]=explode("\t",$line);
            $artifacts[]=["path"=>$path,"key"=>$key,"sha256"=>$sha,"size_bytes"=>(int)$size];
        }
        $value=["status"=>"verified","release"=>getenv("RELEASE"),"published_at"=>gmdate("c"),
            "graph_sha256"=>getenv("GRAPH_SHA"),"artifacts"=>$artifacts];
        file_put_contents(getenv("MARKER"),json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL,LOCK_EX);
    '
    upload_file "$marker" "$marker_key" 'application/json' "$(gis_sha256 "$marker")"
fi

final_listing="$(list_release_keys)" || gis_fail 'Unable to verify the completed private release inventory.'
final_inventory_valid="$(LISTING="$final_listing" EXPECTED_COUNT="$((${#keys[@]} + 1))" MARKER_KEY="$marker_key" php -r '
    $v=json_decode((string)getenv("LISTING"),true,flags:JSON_THROW_ON_ERROR);
    $items=$v["Contents"]??$v["contents"]??[];
    $keys=array_values(array_filter(array_map(static fn($item)=>$item["Key"]??$item["key"]??null,$items),"is_string"));
    echo count($keys)===(int)getenv("EXPECTED_COUNT")&&in_array(getenv("MARKER_KEY"),$keys,true)?"yes":"no";
')"
[[ "$final_inventory_valid" == 'yes' ]] || gis_fail 'Completed private release inventory is inconsistent.'

private_url="https://${bucket}.storage.yandexcloud.net/${marker_key}"
anonymous_status="$(curl --silent --show-error --head --output /dev/null --write-out '%{http_code}' "$private_url")"
[[ "$anonymous_status" == '403' ]] \
    || gis_fail "Private publication marker returned anonymous HTTP ${anonymous_status}, expected 403."

state="${GIS_STATE_DIR}/last-private-publication.json"
[[ ! -e "$state" || ( -f "$state" && ! -L "$state" ) ]] \
    || gis_fail 'Private publication state target is unsafe.'
install -m 0640 -- "$marker" "${state}.next.$$"
mv -f -- "${state}.next.$$" "$state"
trap - EXIT
rm -r -- "$work_dir"

gis_log "Private Valhalla artifact publication verified for ${release}."
gis_log 'Anonymous access is forbidden; the immutable graph can be restored onto an optional GIS runtime later.'
