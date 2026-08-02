#!/usr/bin/env bash

set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

[[ $# -eq 1 ]] || gis_fail 'Usage: publish-map-assets.sh russia-YYYYMMDD'
release="$1"
gis_validate_release "$release"

for command_name in aws cmp curl find flock install mkdir php sha256sum sort; do
    gis_require_command "$command_name"
done

target="${GIS_RELEASES_DIR}/${release}"
[[ -d "$target" && ! -L "$target" ]] || gis_fail 'Verified release directory is unavailable.'
target="$(gis_assert_inside_base "$target")"
manifest="${target}/release-manifest.json"
pmtiles="${target}/map/russia.pmtiles"
assets="${target}/map/assets"
[[ -s "$manifest" && ! -L "$manifest" && -s "$pmtiles" && ! -L "$pmtiles" ]] \
    || gis_fail 'Verified release manifest and PMTiles archive are required.'
php "${GIS_SCRIPT_DIR}/validate-map-assets.php" "$assets"

manifest_status="$(MANIFEST="$manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true,flags:JSON_THROW_ON_ERROR);echo (string)($v["status"]??"");')"
manifest_release="$(MANIFEST="$manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true,flags:JSON_THROW_ON_ERROR);echo (string)($v["release"]??"");')"
expected_sha="$(MANIFEST="$manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true,flags:JSON_THROW_ON_ERROR);echo strtolower((string)($v["pmtiles"]["sha256"]??""));')"
expected_size="$(MANIFEST="$manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true,flags:JSON_THROW_ON_ERROR);echo (int)($v["pmtiles"]["size_bytes"]??0);')"
[[ "$manifest_status" == "verified" && "$manifest_release" == "$release" ]] \
    || gis_fail 'Only the matching verified release can publish map assets.'
[[ "$expected_sha" =~ ^[0-9a-f]{64}$ && "$(gis_sha256 "$pmtiles")" == "$expected_sha" ]] \
    || gis_fail 'PMTiles checksum does not match the verified release manifest.'
[[ "$expected_size" -gt 0 && "$(gis_file_size "$pmtiles")" == "$expected_size" ]] \
    || gis_fail 'PMTiles size does not match the verified release manifest.'

bucket="${GIS_OBJECT_STORAGE_BUCKET:-}"
prefix="${GIS_OBJECT_STORAGE_PREFIX:-logistics}"
public_base="${GIS_PUBLIC_ASSET_BASE_URL:-}"
application_origin="${GIS_MAP_APPLICATION_ORIGIN:-}"
[[ "$bucket" =~ ^[A-Za-z0-9][A-Za-z0-9._-]{1,61}[A-Za-z0-9]$ ]] \
    || gis_fail 'GIS_OBJECT_STORAGE_BUCKET is missing or invalid.'
[[ "$prefix" =~ ^[A-Za-z0-9][A-Za-z0-9._/-]*[A-Za-z0-9]$ \
    && "/${prefix}/" != *"/../"* && "$prefix" != *"//"* ]] \
    || gis_fail 'GIS_OBJECT_STORAGE_PREFIX must be a safe non-empty object prefix.'

url_settings_valid="$(PUBLIC_BASE="$public_base" APP_ORIGIN="$application_origin" php -r '
    $public=parse_url((string)getenv("PUBLIC_BASE"));
    $origin=parse_url((string)getenv("APP_ORIGIN"));
    $valid=static fn($parts): bool => is_array($parts)
        &&strtolower((string)($parts["scheme"]??""))==="https"
        &&is_string($parts["host"]??null)&&$parts["host"]!==""
        &&!isset($parts["user"])&&!isset($parts["pass"])
        &&!isset($parts["query"])&&!isset($parts["fragment"]);
    $publicPath=(string)($public["path"]??"");
    $originPath=(string)($origin["path"]??"");
    echo $valid($public)&&$valid($origin)
        &&!str_ends_with($publicPath,"/")
        &&in_array($originPath,["","/"],true)?"yes":"no";
')"
[[ "$url_settings_valid" == "yes" ]] \
    || gis_fail 'Public base must be clean HTTPS without a trailing slash; application origin must be an exact HTTPS origin.'
public_base="${public_base%/}"

for directory in "$GIS_LOCK_DIR" "$GIS_STATE_DIR"; do
    [[ -d "$directory" && -w "$directory" ]] || gis_fail "Required writable directory is missing: ${directory}"
    gis_assert_inside_base "$directory" >/dev/null
done
exec 9>"${GIS_LOCK_DIR}/gis-map-publish.lock"
flock -n 9 || gis_fail 'Another GIS map publication is already running.'

aws_options=()
if [[ -n "${GIS_OBJECT_STORAGE_ENDPOINT:-}" ]]; then
    [[ "${GIS_OBJECT_STORAGE_ENDPOINT}" =~ ^https://[^/?#]+/?$ ]] \
        || gis_fail 'GIS_OBJECT_STORAGE_ENDPOINT must be an HTTPS origin.'
    aws_options+=(--endpoint-url "${GIS_OBJECT_STORAGE_ENDPOINT%/}")
fi
if [[ -n "${GIS_OBJECT_STORAGE_REGION:-}" ]]; then
    aws_options+=(--region "$GIS_OBJECT_STORAGE_REGION")
fi

release_prefix="${prefix}/releases/${release}"
publication_key="${release_prefix}/map-publication.json"
declare -a inventory_sources=()
declare -a inventory_keys=()
declare -a inventory_types=()
declare -a inventory_shas=()
declare -a inventory_sizes=()
declare -A expected_keys=()

add_inventory() {
    local source="$1" key="$2" content_type="$3"
    [[ -s "$source" && ! -L "$source" ]] || gis_fail "Public map source is missing or unsafe: ${source}"
    [[ -z "${expected_keys[$key]:-}" ]] || gis_fail "Duplicate object-storage key: ${key}"
    inventory_sources+=("$source")
    inventory_keys+=("$key")
    inventory_types+=("$content_type")
    inventory_shas+=("$(gis_sha256 "$source")")
    inventory_sizes+=("$(gis_file_size "$source")")
    expected_keys["$key"]=1
}

add_inventory "$pmtiles" "${release_prefix}/russia.pmtiles" 'application/vnd.pmtiles'
while IFS= read -r -d '' asset; do
    relative="${asset#${assets}/}"
    case "$relative" in
        *.pbf) content_type='application/x-protobuf' ;;
        *.json) content_type='application/json' ;;
        *.png) content_type='image/png' ;;
        SHA256SUMS) content_type='text/plain' ;;
        *) gis_fail "Unsupported public map asset type: ${relative}" ;;
    esac
    add_inventory "$asset" "${release_prefix}/assets/${relative}" "$content_type"
done < <(find "$assets" -type f -print0 | sort -z)
add_inventory "$manifest" "${release_prefix}/release-manifest.json" 'application/json'

list_release_keys() {
    aws "${aws_options[@]}" s3api list-objects-v2 \
        --bucket "$bucket" --prefix "${release_prefix}/" --output json
}

listing="$(list_release_keys)" || gis_fail 'Unable to inspect the immutable object-storage release prefix.'
mapfile -t existing_keys < <(LISTING="$listing" php -r '
    $v=json_decode((string)getenv("LISTING"),true,flags:JSON_THROW_ON_ERROR);
    foreach($v["Contents"]??[] as $item){if(is_string($item["Key"]??null))echo $item["Key"],PHP_EOL;}
')
declare -A existing_key_set=()
publication_exists=false
for key in "${existing_keys[@]}"; do
    [[ -n "$key" ]] || continue
    existing_key_set["$key"]=1
    if [[ "$key" == "$publication_key" ]]; then
        publication_exists=true
    elif [[ -z "${expected_keys[$key]:-}" ]]; then
        gis_fail "Immutable release prefix contains an unexpected object: ${key}"
    fi
done
if $publication_exists; then
    for key in "${inventory_keys[@]}"; do
        [[ -n "${existing_key_set[$key]:-}" ]] \
            || gis_fail 'Published release marker exists but its immutable asset set is incomplete.'
    done
fi

upload_file() {
    local source="$1" key="$2" content_type="$3" checksum="$4"
    aws "${aws_options[@]}" s3 cp "$source" "s3://${bucket}/${key}" \
        --only-show-errors \
        --content-type "$content_type" \
        --cache-control 'public,max-age=31536000,immutable' \
        --metadata "sha256=${checksum},release=${release}"
}

validate_existing_object() {
    local key="$1" content_type="$2" checksum="$3" size="$4"
    local head_json matches
    head_json="$(aws "${aws_options[@]}" s3api head-object --bucket "$bucket" --key "$key" --output json)" \
        || gis_fail "Unable to inspect existing immutable object: ${key}"
    matches="$(HEAD_JSON="$head_json" EXPECTED_TYPE="$content_type" EXPECTED_SHA="$checksum" EXPECTED_SIZE="$size" EXPECTED_RELEASE="$release" php -r '
        $v=json_decode((string)getenv("HEAD_JSON"),true,flags:JSON_THROW_ON_ERROR);
        $metadata=array_change_key_case(is_array($v["Metadata"]??null)?$v["Metadata"]:[],CASE_LOWER);
        $valid=(int)($v["ContentLength"]??-1)===(int)getenv("EXPECTED_SIZE")
            &&strtolower((string)($v["ContentType"]??""))===strtolower((string)getenv("EXPECTED_TYPE"))
            &&(string)($v["CacheControl"]??"")==="public,max-age=31536000,immutable"
            &&strtolower((string)($metadata["sha256"]??""))===strtolower((string)getenv("EXPECTED_SHA"))
            &&(string)($metadata["release"]??"")===(string)getenv("EXPECTED_RELEASE");
        echo $valid?"yes":"no";
    ')"
    [[ "$matches" == "yes" ]] \
        || gis_fail "Existing immutable object differs from the verified local release: ${key}"
}

gis_log "Publishing/resuming immutable map assets for ${release}; existing objects are verified and never overwritten."
for index in "${!inventory_keys[@]}"; do
    source="${inventory_sources[$index]}"
    key="${inventory_keys[$index]}"
    content_type="${inventory_types[$index]}"
    checksum="${inventory_shas[$index]}"
    size="${inventory_sizes[$index]}"
    if [[ -n "${existing_key_set[$key]:-}" ]]; then
        validate_existing_object "$key" "$content_type" "$checksum" "$size"
    else
        upload_file "$source" "$key" "$content_type" "$checksum"
    fi
done

pmtiles_url="${public_base}/releases/${release}/russia.pmtiles"
GIS_EXPECTED_CORS_ORIGIN="$application_origin" \
    "${GIS_SCRIPT_DIR}/check-pmtiles-range.sh" "$pmtiles_url"
published_size="$(STATE="${GIS_STATE_DIR}/last-range-check.json" php -r '$v=json_decode((string)file_get_contents(getenv("STATE")),true,flags:JSON_THROW_ON_ERROR);echo (int)($v["content_length"]??0);')"
[[ "$published_size" == "$expected_size" ]] \
    || gis_fail 'Published PMTiles Content-Length differs from the verified release manifest.'

if [[ "${GIS_VERIFY_PUBLIC_PMTILES_SHA256:-true}" == "true" ]]; then
    gis_log 'Streaming the public PMTiles object once for end-to-end SHA-256 verification.'
    published_sha="$(curl --fail --location --retry 3 --retry-all-errors --silent --show-error "$pmtiles_url" \
        | gis_low_priority sha256sum | awk '{print $1}')"
    [[ "$published_sha" == "$expected_sha" ]] \
        || gis_fail 'Published PMTiles SHA-256 differs from the verified release manifest.'
fi

public_asset_url() {
    PUBLIC_BASE="$public_base" RELEASE="$release" RELATIVE="$1" php -r '
        $segments=array_map("rawurlencode",explode("/",(string)getenv("RELATIVE")));
        echo getenv("PUBLIC_BASE")."/releases/".rawurlencode((string)getenv("RELEASE"))."/assets/".implode("/",$segments);
    '
}

asset_headers="$(mktemp "${GIS_STATE_DIR}/asset-headers.XXXXXXXX")"
cleanup_headers() { rm -- "$asset_headers"; }
trap cleanup_headers EXIT
while IFS=$' \t' read -r checksum relative; do
    relative="${relative#\*}"
    [[ "$checksum" =~ ^[0-9a-fA-F]{64}$ ]] || gis_fail 'Map asset checksum manifest is invalid during public verification.'
    asset_url="$(public_asset_url "$relative")"
    public_checksum="$(curl --fail --location --retry 3 --retry-all-errors --silent --show-error \
        --header "Origin: ${application_origin}" --dump-header "$asset_headers" "$asset_url" \
        | sha256sum | awk '{print $1}')"
    [[ "${public_checksum,,}" == "${checksum,,}" ]] \
        || gis_fail "Published map asset checksum mismatch: ${relative}"
    asset_cors="$(awk '{line=tolower($0)} line ~ /^access-control-allow-origin:/{sub(/^[^:]+:[[:space:]]*/,"");sub(/\r$/,"");value=$0}END{print value}' "$asset_headers")"
    [[ "$asset_cors" == "$application_origin" ]] \
        || gis_fail "Published map asset CORS mismatch: ${relative}"
done < "${assets}/SHA256SUMS"
rm -- "$asset_headers"
trap - EXIT

publication_part="${GIS_STATE_DIR}/.last-map-publication.json.$$"
publication_state="${GIS_STATE_DIR}/last-map-publication.json"
[[ ! -L "$publication_state" ]] || gis_fail 'Map publication state path must not be a symlink.'
[[ ! -e "$publication_part" ]] || gis_fail 'Temporary map publication state already exists.'
cleanup_publication() {
    [[ ! -f "$publication_part" ]] || rm -- "$publication_part"
}
trap cleanup_publication EXIT
if $publication_exists; then
    aws "${aws_options[@]}" s3 cp "s3://${bucket}/${publication_key}" "$publication_part" --only-show-errors
    publication_matches="$(PUBLICATION="$publication_part" EXPECTED_RELEASE="$release" EXPECTED_URL="$pmtiles_url" EXPECTED_BASE="$public_base" EXPECTED_ORIGIN="$application_origin" EXPECTED_SIZE="$expected_size" EXPECTED_SHA="$expected_sha" php -r '
        $v=json_decode((string)file_get_contents(getenv("PUBLICATION")),true,flags:JSON_THROW_ON_ERROR);$pmtiles=$v["pmtiles"]??[];
        $valid=($v["status"]??null)==="verified"&&($v["release"]??null)===getenv("EXPECTED_RELEASE")
            &&($v["public_base_url"]??null)===getenv("EXPECTED_BASE")&&($v["application_origin"]??null)===getenv("EXPECTED_ORIGIN")
            &&($pmtiles["url"]??null)===getenv("EXPECTED_URL")&&(int)($pmtiles["size_bytes"]??0)===(int)getenv("EXPECTED_SIZE")
            &&($pmtiles["sha256"]??null)===getenv("EXPECTED_SHA")
            &&($pmtiles["range_requests"]??null)==="passed"&&($pmtiles["cors"]??null)==="passed";
        echo $valid?"yes":"no";
    ')"
    [[ "$publication_matches" == "yes" ]] || gis_fail 'Existing publication marker does not match this verified release.'
else
    PUBLICATION_RELEASE="$release" PUBLICATION_URL="$pmtiles_url" PUBLICATION_BASE="$public_base" \
    PUBLICATION_ORIGIN="$application_origin" PUBLICATION_SIZE="$expected_size" PUBLICATION_SHA="$expected_sha" \
    php -r '
        $value=[
            "status"=>"verified",
            "release"=>getenv("PUBLICATION_RELEASE"),
            "published_at"=>gmdate("c"),
            "public_base_url"=>getenv("PUBLICATION_BASE"),
            "application_origin"=>getenv("PUBLICATION_ORIGIN"),
            "pmtiles"=>[
                "url"=>getenv("PUBLICATION_URL"),
                "size_bytes"=>(int)getenv("PUBLICATION_SIZE"),
                "sha256"=>getenv("PUBLICATION_SHA"),
                "range_requests"=>"passed",
                "cors"=>"passed",
            ],
        ];
        file_put_contents($argv[1],json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL,LOCK_EX);
    ' "$publication_part"
    publication_sha="$(gis_sha256 "$publication_part")"
    upload_file "$publication_part" "$publication_key" 'application/json' "$publication_sha"
fi
publications_dir="${GIS_STATE_DIR}/map-publications"
mkdir -m 0750 -p -- "$publications_dir"
[[ -d "$publications_dir" && ! -L "$publications_dir" ]] \
    || gis_fail 'Release publication state directory is unsafe.'
gis_assert_inside_base "$publications_dir" >/dev/null
release_publication="${publications_dir}/${release}.json"
if [[ -e "$release_publication" ]]; then
    [[ -f "$release_publication" && ! -L "$release_publication" ]] \
        || gis_fail 'Immutable release publication state is unsafe.'
    cmp --silent "$publication_part" "$release_publication" \
        || gis_fail 'Immutable release publication state already differs.'
else
    install -m 0640 -- "$publication_part" "$release_publication"
fi
mv -- "$publication_part" "$publication_state"
trap - EXIT

final_listing="$(list_release_keys)" || gis_fail 'Unable to verify the completed immutable object-storage release.'
final_inventory_valid="$(LISTING="$final_listing" EXPECTED_COUNT="$((${#inventory_keys[@]} + 1))" PUBLICATION_KEY="$publication_key" php -r '
    $v=json_decode((string)getenv("LISTING"),true,flags:JSON_THROW_ON_ERROR);
    $keys=array_values(array_filter(array_map(static fn($item)=>$item["Key"]??null,$v["Contents"]??[]),"is_string"));
    echo count($keys)===(int)getenv("EXPECTED_COUNT")&&in_array(getenv("PUBLICATION_KEY"),$keys,true)?"yes":"no";
')"
[[ "$final_inventory_valid" == "yes" ]] || gis_fail 'Completed object-storage release inventory is inconsistent.'

gis_log "Persistent map publication verified: ${pmtiles_url}"
gis_log 'The object-storage release is immutable; activation remains a separate supervised step.'
