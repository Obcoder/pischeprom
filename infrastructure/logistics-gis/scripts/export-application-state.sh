#!/usr/bin/env bash

set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

[[ $# -eq 2 ]] || gis_fail 'Usage: export-application-state.sh russia-YYYYMMDD /absolute/new-output-directory'
release="$1"
output="$2"
gis_validate_release "$release"
[[ "$output" == /* && "$output" != "/" && ! -e "$output" ]] \
    || gis_fail 'Output must be an absent absolute non-root path.'
output_parent="$(dirname "$output")"
[[ -d "$output_parent" && -w "$output_parent" && ! -L "$output_parent" ]] \
    || gis_fail 'Output parent must be an existing writable real directory.'

activation="${GIS_STATE_DIR}/last-activation.json"
[[ -s "$activation" && -f "$activation" && ! -L "$activation" ]] \
    || gis_fail 'An active map or paired-routing activation state is required.'
activation_scope="$(ACTIVATION="$activation" RELEASE="$release" php -r '
    $v=json_decode((string)file_get_contents(getenv("ACTIVATION")),true,flags:JSON_THROW_ON_ERROR);
    if(($v["status"]??null)!=="active"||($v["release"]??null)!==getenv("RELEASE"))exit(1);
    if(($v["map_delivery"]??null)==="passed"&&($v["routing_activation"]??null)==="independent"){echo "map_only";exit;}
    if(($v["production_smoke"]??null)==="passed"){echo "paired_routing";exit;}
    exit(1);
')" || gis_fail 'Activation state does not authorize this release for application export.'
if [[ "$activation_scope" == 'map_only' ]]; then
    current="${GIS_RELEASES_DIR}/${release}"
    [[ -d "$current" && ! -L "$current" ]] \
        || gis_fail 'Map-only export requires the verified immutable release directory.'
else
    current_link="${GIS_BASE_DIR}/current"
    [[ -L "$current_link" ]] || gis_fail 'Paired-routing export requires an active current GIS release.'
    current="$(realpath -- "$current_link")"
    gis_assert_inside_base "$current" >/dev/null
    [[ "$(basename "$current")" == "$release" ]] \
        || gis_fail 'Requested release is not the active current routing release.'
fi
current="$(gis_assert_inside_base "$current")"

manifest="${current}/release-manifest.json"
range="${GIS_STATE_DIR}/last-range-check.json"
smoke="${GIS_STATE_DIR}/last-production-smoke.json"
publication="${GIS_STATE_DIR}/last-map-publication.json"
for file in "$manifest" "$activation" "$range" "$smoke" "$publication"; do
    [[ -s "$file" && -f "$file" && ! -L "$file" ]] || gis_fail "Required application-state file is unavailable: ${file}"
    size="$(gis_file_size "$file")"
    (( size >= 2 && size <= 1048576 )) || gis_fail "Application-state file has an unsafe size: ${file}"
done

state_matches="$(RELEASE="$release" ACTIVATION_SCOPE="$activation_scope" MANIFEST="$manifest" ACTIVATION="$activation" RANGE_STATE="$range" SMOKE="$smoke" PUBLICATION="$publication" php -r '
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
[[ "$state_matches" == "yes" ]] || gis_fail 'GIS state files do not describe one active verified persistent-map release.'

output_part="${output}.next.$$"
[[ ! -e "$output_part" ]] || gis_fail 'Temporary output path already exists.'
mkdir -m 0750 -- "$output_part"
cleanup() {
    [[ ! -d "$output_part" ]] || rm -r -- "$output_part"
}
trap cleanup EXIT
install -m 0640 -- "$manifest" "${output_part}/release-manifest.json"
install -m 0640 -- "$activation" "${output_part}/last-activation.json"
install -m 0640 -- "$range" "${output_part}/last-range-check.json"
install -m 0640 -- "$smoke" "${output_part}/last-production-smoke.json"
install -m 0640 -- "$publication" "${output_part}/last-map-publication.json"
preflight="${GIS_STATE_DIR}/last-preflight.json"
if [[ -e "$preflight" ]]; then
    [[ -s "$preflight" && -f "$preflight" && ! -L "$preflight" ]] \
        || gis_fail 'Optional preflight state must be a non-empty regular file.'
    preflight_size="$(gis_file_size "$preflight")"
    (( preflight_size >= 2 && preflight_size <= 1048576 )) \
        || gis_fail 'Optional preflight state has an unsafe size.'
    install -m 0640 -- "$preflight" "${output_part}/last-preflight.json"
fi

BUNDLE_RELEASE="$release" BUNDLE_SCOPE="$activation_scope" BUNDLE_OUTPUT="${output_part}/bundle.json" php -r '
    $value=[
        "schema_version"=>getenv("BUNDLE_SCOPE")==="map_only"?2:1,
        "release"=>getenv("BUNDLE_RELEASE"),
        "purpose"=>"pischeprom-logistics-map-state",
        "activation_scope"=>getenv("BUNDLE_SCOPE"),
        "exported_at"=>gmdate("c"),
    ];
    file_put_contents(getenv("BUNDLE_OUTPUT"),json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL,LOCK_EX);
'
(
    cd "$output_part"
    sha256sum -- bundle.json release-manifest.json last-activation.json last-range-check.json \
        last-production-smoke.json last-map-publication.json > SHA256SUMS
    [[ ! -f last-preflight.json ]] || sha256sum -- last-preflight.json >> SHA256SUMS
)
chmod 0640 "${output_part}/SHA256SUMS" "${output_part}/bundle.json"
mv -- "$output_part" "$output"
trap - EXIT

gis_log "Sanitized application-state bundle exported: ${output}"
gis_log 'It contains no PBF, graph, PMTiles archive, credentials, logs, or private filesystem paths.'
