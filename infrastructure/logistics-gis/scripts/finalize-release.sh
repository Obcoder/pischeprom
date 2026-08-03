#!/usr/bin/env bash

set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

retry_failed=false
if [[ $# -eq 1 ]]; then
    release="$1"
elif [[ $# -eq 2 && "$1" == '--retry-smoke' ]]; then
    retry_failed=true
    release="$2"
else
    gis_fail 'Usage: finalize-release.sh [--retry-smoke] russia-YYYYMMDD'
fi
gis_validate_release "$release"
pbf="${GIS_SOURCE_DIR}/${release}.osm.pbf"
GIS_PREFLIGHT_PBF_PATH="$pbf" "${GIS_SCRIPT_DIR}/preflight.sh" --mode verify
for directory in "$GIS_STAGING_DIR" "$GIS_RELEASES_DIR" "$GIS_LOCK_DIR" "$GIS_LOG_DIR" "$GIS_STATE_DIR"; do
    [[ -d "$directory" && -w "$directory" ]] || gis_fail "Required writable directory is missing: ${directory}"
    gis_assert_inside_base "$directory" >/dev/null
done
exec 9>"${GIS_LOCK_DIR}/gis-build.lock"
flock -n 9 || gis_fail 'Another GIS build/finalize operation is already running.'

stage="${GIS_STAGING_DIR}/${release}"
target="${GIS_RELEASES_DIR}/${release}"

if $retry_failed; then
    [[ ! -e "$stage" && -d "$target" && ! -L "$target" ]] \
        || gis_fail 'Smoke retry requires an absent staging path and one real failed release target.'
    target="$(gis_assert_inside_base "$target")"
    failed_manifest="${target}/release-manifest.json"
    [[ -s "$failed_manifest" && ! -L "$failed_manifest" ]] \
        || gis_fail 'Smoke retry requires the managed failed release manifest.'
    failed_release_status="$(MANIFEST="$failed_manifest" EXPECTED_RELEASE="$release" php -r '
        $v=json_decode((string)file_get_contents(getenv("MANIFEST")),true,flags:JSON_THROW_ON_ERROR);
        echo (($v["release"]??null)===getenv("EXPECTED_RELEASE")&&($v["status"]??null)==="failed")?"yes":"no";
    ')"
    [[ "$failed_release_status" == 'yes' ]] \
        || gis_fail 'Only the exact failed release may be returned to staging for a smoke retry.'
    [[ "$(stat -c '%d' -- "$target")" == "$(stat -c '%d' -- "$GIS_STAGING_DIR")" ]] \
        || gis_fail 'Failed release and staging must share a filesystem for atomic smoke retry.'
    mv -- "$target" "$stage"
    gis_log "Returned failed inactive release to staging for checksum verification and smoke retry: ${release}."
fi

[[ -d "$stage" && ! -e "$target" ]] || gis_fail 'Expected a unique staged release and an absent final target.'
[[ ! -L "$stage" ]] || gis_fail 'Release staging path must not be a symlink.'
stage="$(gis_assert_inside_base "$stage")"
[[ "$(stat -c '%d' -- "$stage")" == "$(stat -c '%d' -- "$GIS_RELEASES_DIR")" ]] \
    || gis_fail 'Staging and releases must share a filesystem for atomic finalization.'
first_symlink="$(find "$stage" -type l -print -quit)"
[[ -z "$first_symlink" ]] || gis_fail "Staged release contains a forbidden symlink: ${first_symlink}"
valhalla_manifest="${stage}/valhalla/component-manifest.json"
pmtiles_manifest="${stage}/map/component-manifest.json"
pbf_manifest="${GIS_SOURCE_DIR}/${release}.manifest.json"
[[ ! -L "$pbf" && ! -L "$pbf_manifest" ]] || gis_fail 'Source PBF/manifest must not be symlinks.'
for file in "$pbf" "$valhalla_manifest" "$pmtiles_manifest" "$pbf_manifest" \
    "${stage}/valhalla/valhalla.json" "${stage}/valhalla/valhalla_tiles.tar" \
    "${stage}/map/russia.pmtiles" "${stage}/map/assets/SHA256SUMS"
do
    [[ -s "$file" ]] || gis_fail "Release component is missing: ${file}"
done
expected_pbf_md5="$(MANIFEST="$pbf_manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true);echo strtolower((string)($v["md5"]??""));')"
[[ "$expected_pbf_md5" =~ ^[0-9a-f]{32}$ && "$(gis_md5 "$pbf")" == "$expected_pbf_md5" ]] \
    || gis_fail 'Verified source PBF checksum changed before release finalization.'
matching="$(VALHALLA_MANIFEST="$valhalla_manifest" PMTILES_MANIFEST="$pmtiles_manifest" PBF_MANIFEST="$pbf_manifest" php -r '
    $v=json_decode((string)file_get_contents(getenv("VALHALLA_MANIFEST")),true,flags:JSON_THROW_ON_ERROR);
    $m=json_decode((string)file_get_contents(getenv("PMTILES_MANIFEST")),true,flags:JSON_THROW_ON_ERROR);
    $p=json_decode((string)file_get_contents(getenv("PBF_MANIFEST")),true,flags:JSON_THROW_ON_ERROR);
    echo (($v["pbf_md5"]??null)===($m["pbf_md5"]??null)&&($v["pbf_md5"]??null)===($p["md5"]??null))?"yes":"no";
')"
[[ "$matching" == "yes" ]] || gis_fail 'Valhalla, PMTiles and PBF do not belong to one OSM dataset release.'
expected_pmtiles_sha="$(MANIFEST="$pmtiles_manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true);echo (string)($v["sha256"]??"");')"
[[ "$expected_pmtiles_sha" =~ ^[0-9a-f]{64}$ && "$(gis_sha256 "${stage}/map/russia.pmtiles")" == "$expected_pmtiles_sha" ]] \
    || gis_fail 'Staged PMTiles checksum mismatch.'
expected_graph_sha="$(MANIFEST="$valhalla_manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true);echo (string)($v["graph_sha256"]??"");')"
[[ "$expected_graph_sha" =~ ^[0-9a-f]{64}$ && "$(gis_sha256 "${stage}/valhalla/valhalla_tiles.tar")" == "$expected_graph_sha" ]] \
    || gis_fail 'Staged Valhalla graph checksum mismatch.'
expected_assets_sha="$(MANIFEST="$pmtiles_manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true);echo (string)($v["assets_manifest_sha256"]??"");')"
[[ "$expected_assets_sha" =~ ^[0-9a-f]{64}$ && "$(gis_sha256 "${stage}/map/assets/SHA256SUMS")" == "$expected_assets_sha" ]] \
    || gis_fail 'Staged map assets manifest checksum mismatch.'
php "${GIS_SCRIPT_DIR}/validate-map-assets.php" "${stage}/map/assets"
gis_low_priority pmtiles verify "${stage}/map/russia.pmtiles"

mv -- "$stage" "$target"
target="$(gis_assert_inside_base "$target")"
old_prefix="$stage"
new_prefix="$target"
CONFIG="${target}/valhalla/valhalla.json" OLD_PREFIX="$old_prefix" NEW_PREFIX="$new_prefix" php -r '
    $path=getenv("CONFIG");$value=json_decode((string)file_get_contents($path),true,flags:JSON_THROW_ON_ERROR);
    $replace=function($item)use(&$replace){if(is_array($item)){foreach($item as $key=>$value)$item[$key]=$replace($value);return $item;}return is_string($item)?str_replace(getenv("OLD_PREFIX"),getenv("NEW_PREFIX"),$item):$item;};
    $next=$path.".next";file_put_contents($next,json_encode($replace($value),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL,LOCK_EX);rename($next,$path);
'

smoke_output="${target}/smoke-tests.json"
if ! GIS_SMOKE_OUTPUT="$smoke_output" "${GIS_SCRIPT_DIR}/smoke-test-release.sh" "$target"; then
    FAILED_TARGET="$target" php -r '$v=["release"=>basename(getenv("FAILED_TARGET")),"status"=>"failed","coverage"=>"Russia","failed_at"=>gmdate("c")];file_put_contents(getenv("FAILED_TARGET")."/release-manifest.json",json_encode($v,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL,LOCK_EX);'
    gis_fail 'Inactive release failed staging smoke tests and was not activated.'
fi

TARGET="$target" PBF_MANIFEST="$pbf_manifest" php -r '
    $target=getenv("TARGET");
    $pbf=json_decode((string)file_get_contents(getenv("PBF_MANIFEST")),true,flags:JSON_THROW_ON_ERROR);
    $valhalla=json_decode((string)file_get_contents($target."/valhalla/component-manifest.json"),true,flags:JSON_THROW_ON_ERROR);
    $pmtiles=json_decode((string)file_get_contents($target."/map/component-manifest.json"),true,flags:JSON_THROW_ON_ERROR);
    $smoke=json_decode((string)file_get_contents($target."/smoke-tests.json"),true,flags:JSON_THROW_ON_ERROR);
    $value=[
        "release"=>basename($target),"status"=>"verified","coverage"=>"Russia","osm_data_version"=>$pbf["data_date"],"verified_at"=>gmdate("c"),
        "pbf"=>["source_url"=>$pbf["source_url"],"resolved_source_url"=>$pbf["resolved_source_url"]??null,"data_date"=>$pbf["data_date"],"osm_data_timestamp"=>$pbf["osm_data_timestamp"]??null,"size_bytes"=>$pbf["size_bytes"],"md5"=>$pbf["md5"]],
        "valhalla"=>["version"=>$valhalla["version"],"graph_size_bytes"=>$valhalla["graph_size_bytes"],"graph_sha256"=>$valhalla["graph_sha256"],"build_duration_seconds"=>$valhalla["build_duration_seconds"],"peak_rss_kb"=>$valhalla["peak_rss_kb"]],
        "pmtiles"=>["spec_version"=>$pmtiles["spec_version"],"planetiler_version"=>$pmtiles["planetiler_version"],"java_version"=>$pmtiles["java_version"],"size_bytes"=>$pmtiles["size_bytes"],"sha256"=>$pmtiles["sha256"],"assets_manifest_sha256"=>$pmtiles["assets_manifest_sha256"],"build_duration_seconds"=>$pmtiles["build_duration_seconds"],"peak_rss_kb"=>$pmtiles["peak_rss_kb"]],
        "smoke_tests"=>$smoke
    ];
    file_put_contents($target."/release-manifest.json",json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL,LOCK_EX);
'
gis_log "Inactive paired release verified: ${target}."
gis_log 'The current and previous production releases are unchanged.'
