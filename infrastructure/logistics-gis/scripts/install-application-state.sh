#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'
umask 027

fail() {
    printf '[logistics-gis-state] ERROR: %s\n' "$*" >&2
    exit 1
}

[[ $# -eq 2 ]] || fail 'Usage: install-application-state.sh /absolute/bundle-directory /absolute/state-base-directory'
source_dir="$1"
state_base="$2"
[[ "$source_dir" == /* && -d "$source_dir" && ! -L "$source_dir" ]] \
    || fail 'Bundle source must be an absolute real directory.'
[[ "$state_base" == /* && "$state_base" != "/" && -d "$state_base" && ! -L "$state_base" && -w "$state_base" ]] \
    || fail 'State base must be an existing writable absolute non-root real directory.'
source_dir="$(realpath -- "$source_dir")"
state_base="$(realpath -- "$state_base")"

for command_name in awk cmp php sha256sum; do
    command -v "$command_name" >/dev/null 2>&1 || fail "Required command is unavailable: ${command_name}"
done
for file_name in bundle.json SHA256SUMS release-manifest.json last-activation.json \
    last-range-check.json last-production-smoke.json last-map-publication.json
do
    path="${source_dir}/${file_name}"
    [[ -s "$path" && -f "$path" && ! -L "$path" ]] || fail "Bundle file is unavailable: ${file_name}"
    size="$(stat -c '%s' -- "$path")"
    (( size >= 2 && size <= 1048576 )) || fail "Bundle file has an unsafe size: ${file_name}"
done

checksum_manifest_valid="$(CHECKSUMS="${source_dir}/SHA256SUMS" php -r '
    $required=[
        "bundle.json","release-manifest.json","last-activation.json",
        "last-range-check.json","last-production-smoke.json","last-map-publication.json",
    ];
    $allowed=array_fill_keys([...$required,"last-preflight.json"],true);
    $seen=[];
    foreach(file(getenv("CHECKSUMS"),FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){
        if(!preg_match("/^([0-9a-f]{64})  ([A-Za-z0-9.-]+)$/",$line,$matches)
            ||!isset($allowed[$matches[2]])||isset($seen[$matches[2]])){echo "no";exit;}
        $seen[$matches[2]]=true;
    }
    foreach($required as $name){if(!isset($seen[$name])){echo "no";exit;}}
    $hasPreflight=is_file(dirname(getenv("CHECKSUMS"))."/last-preflight.json");
    if($hasPreflight!==isset($seen["last-preflight.json"])){echo "no";exit;}
    echo "yes";
')"
[[ "$checksum_manifest_valid" == "yes" ]] || fail 'Bundle checksum manifest contains unsafe or unexpected entries.'
if [[ -e "${source_dir}/last-preflight.json" ]]; then
    [[ -s "${source_dir}/last-preflight.json" && -f "${source_dir}/last-preflight.json" && ! -L "${source_dir}/last-preflight.json" ]] \
        || fail 'Optional preflight state must be a non-empty regular file.'
    preflight_size="$(stat -c '%s' -- "${source_dir}/last-preflight.json")"
    (( preflight_size >= 2 && preflight_size <= 1048576 )) || fail 'Optional preflight state has an unsafe size.'
fi
(
    cd "$source_dir"
    sha256sum --check --strict SHA256SUMS >/dev/null
) || fail 'Bundle checksum verification failed.'

bundle_values="$(BUNDLE="${source_dir}/bundle.json" php -r '
    $v=json_decode((string)file_get_contents(getenv("BUNDLE")),true,flags:JSON_THROW_ON_ERROR);
    $schema=$v["schema_version"]??null;$scope=$v["activation_scope"]??($schema===1?"paired_routing":null);
    if(!in_array($schema,[1,2],true)||($v["purpose"]??null)!=="pischeprom-logistics-map-state"
        ||!in_array($scope,["map_only","paired_routing"],true)
        ||($schema===1&&$scope!=="paired_routing")||($schema===2&&$scope!=="map_only"))exit(1);
    echo (string)($v["release"]??""),PHP_EOL,$scope,PHP_EOL,$schema,PHP_EOL;
')"
mapfile -t bundle_value_lines <<< "$bundle_values"
release="${bundle_value_lines[0]:-}"
activation_scope="${bundle_value_lines[1]:-}"
schema_version="${bundle_value_lines[2]:-}"
[[ "$release" =~ ^russia-[0-9]{8}$ ]] || fail 'Bundle release name is invalid.'
state_matches="$(RELEASE="$release" ACTIVATION_SCOPE="$activation_scope" MANIFEST="${source_dir}/release-manifest.json" ACTIVATION="${source_dir}/last-activation.json" RANGE_STATE="${source_dir}/last-range-check.json" SMOKE="${source_dir}/last-production-smoke.json" PUBLICATION="${source_dir}/last-map-publication.json" php -r '
    $read=static fn(string $name): array => json_decode((string)file_get_contents(getenv($name)),true,flags:JSON_THROW_ON_ERROR);
    $release=getenv("RELEASE");$manifest=$read("MANIFEST");$activation=$read("ACTIVATION");$range=$read("RANGE_STATE");$smoke=$read("SMOKE");$publication=$read("PUBLICATION");
    $manifestPmtiles=$manifest["pmtiles"]??[];$publishedPmtiles=$publication["pmtiles"]??[];
    $scope=getenv("ACTIVATION_SCOPE");
    $activationValid=($activation["release"]??null)===$release&&($activation["status"]??null)==="active"
        &&(($scope==="map_only"&&($activation["map_delivery"]??null)==="passed"&&($activation["routing_activation"]??null)==="independent"&&($smoke["kind"]??null)==="map-delivery")
            ||($scope==="paired_routing"&&($activation["production_smoke"]??null)==="passed"&&($smoke["kind"]??null)==="production"));
    $valid=($manifest["release"]??null)===$release&&($manifest["status"]??null)==="verified"&&$activationValid
        &&is_string($manifestPmtiles["sha256"]??null)&&preg_match("/^[0-9a-f]{64}$/",$manifestPmtiles["sha256"])===1&&(int)($manifestPmtiles["size_bytes"]??0)>0
        &&($smoke["release"]??null)===$release&&($smoke["status"]??null)==="passed"
        &&($publication["release"]??null)===$release&&($publication["status"]??null)==="verified"
        &&is_string($publication["application_origin"]??null)&&$publication["application_origin"]!==""
        &&($publishedPmtiles["sha256"]??null)===$manifestPmtiles["sha256"]
        &&(int)($publishedPmtiles["size_bytes"]??0)===(int)$manifestPmtiles["size_bytes"]
        &&is_string($publishedPmtiles["url"]??null)&&str_starts_with($publishedPmtiles["url"],"https://")
        &&($publishedPmtiles["range_requests"]??null)==="passed"&&($publishedPmtiles["cors"]??null)==="passed"
        &&($range["healthy"]??false)===true&&(int)($range["content_length"]??0)===(int)$manifestPmtiles["size_bytes"]
        &&($range["cors_origin"]??null)===$publication["application_origin"]
        &&($range["cors_allow_origin"]??null)===$publication["application_origin"];
    echo $valid?"yes":"no";
')"
[[ "$state_matches" == "yes" ]] || fail 'Bundle does not describe one active verified release.'

releases_dir="${state_base}/releases"
mkdir -m 0750 -p -- "$releases_dir"
[[ -d "$releases_dir" && ! -L "$releases_dir" ]] || fail 'State releases path must be a real directory.'

current_link="${state_base}/current"
previous_link="${state_base}/previous"
[[ ! -e "$current_link" || -L "$current_link" ]] || fail 'Current state path must be absent or a symlink.'
[[ ! -e "$previous_link" || -L "$previous_link" ]] || fail 'Previous state path must be absent or a symlink.'
old_current=""
if [[ -L "$current_link" ]]; then
    old_current="$(realpath -- "$current_link")"
    [[ "$old_current" == "${releases_dir}/"* && -d "$old_current" ]] || fail 'Current state symlink escapes the state base.'
fi

target_name="$release"
if [[ "$schema_version" == '2' ]]; then
    bundle_digest="$(sha256sum -- "${source_dir}/SHA256SUMS" | awk '{print $1}')"
    [[ "$bundle_digest" =~ ^[0-9a-f]{64}$ ]] || fail 'Unable to derive the schema-2 state snapshot identity.'
    target_name="${release}-map-${bundle_digest:0:16}"
fi
target="${releases_dir}/${target_name}"
bundle_files=(bundle.json release-manifest.json last-activation.json last-range-check.json last-production-smoke.json last-map-publication.json SHA256SUMS)
[[ ! -f "${source_dir}/last-preflight.json" ]] || bundle_files+=(last-preflight.json)
if [[ -e "$target" ]]; then
    [[ -d "$target" && ! -L "$target" ]] || fail 'Existing immutable application-state release is unsafe.'
    source_has_preflight=false
    target_has_preflight=false
    [[ ! -f "${source_dir}/last-preflight.json" ]] || source_has_preflight=true
    [[ ! -f "${target}/last-preflight.json" ]] || target_has_preflight=true
    [[ "$source_has_preflight" == "$target_has_preflight" ]] \
        || fail 'Existing immutable state has a different optional preflight payload.'
    for file_name in "${bundle_files[@]}"; do
        [[ -f "${target}/${file_name}" && ! -L "${target}/${file_name}" ]] \
            || fail "Existing immutable state is incomplete: ${file_name}"
        cmp --silent "${source_dir}/${file_name}" "${target}/${file_name}" \
            || fail "Existing immutable state differs from the verified bundle: ${file_name}"
    done
else
    target_part="${releases_dir}/.${target_name}.next.$$"
    mkdir -m 0750 -- "$target_part"
    cleanup() {
        [[ ! -d "$target_part" ]] || rm -r -- "$target_part"
    }
    trap cleanup EXIT
    for file_name in "${bundle_files[@]}"; do
        install -m 0640 -- "${source_dir}/${file_name}" "${target_part}/${file_name}"
    done
    mv -- "$target_part" "$target"
    trap - EXIT
fi

if [[ "$old_current" == "$target" ]]; then
    printf '[logistics-gis-state] Immutable application state for %s is already current.\n' "$release"
    exit 0
fi
if [[ -n "$old_current" ]]; then
    previous_next="${previous_link}.next.$$"
    ln -s -- "$old_current" "$previous_next"
    mv -Tf -- "$previous_next" "$previous_link"
fi
current_next="${current_link}.next.$$"
ln -s -- "$target" "$current_next"
mv -Tf -- "$current_next" "$current_link"

printf '[logistics-gis-state] Installed immutable application state for %s.\n' "$release"
printf '[logistics-gis-state] Point LOGISTICS_GIS_* paths at %s/current/*.json.\n' "$state_base"
