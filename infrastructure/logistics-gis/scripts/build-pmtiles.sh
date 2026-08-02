#!/usr/bin/env bash

set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

[[ $# -eq 1 ]] || gis_fail 'Usage: build-pmtiles.sh /absolute/path/russia-YYYYMMDD.osm.pbf'
pbf="$(gis_realpath_existing "$1")"
gis_assert_inside_base "$pbf" >/dev/null
manifest="$(gis_verified_pbf_manifest "$pbf")"
release="$(MANIFEST="$manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true,flags:JSON_THROW_ON_ERROR);echo (string)($v["release"]??"");')"
expected_md5="$(MANIFEST="$manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true,flags:JSON_THROW_ON_ERROR);echo strtolower((string)($v["md5"]??""));')"
gis_validate_release "$release"

GIS_PREFLIGHT_PBF_PATH="$pbf" "${GIS_SCRIPT_DIR}/preflight.sh" --mode planetiler
for directory in "$GIS_STAGING_DIR" "$GIS_LOCK_DIR" "$GIS_LOG_DIR"; do
    [[ -d "$directory" && -w "$directory" ]] || gis_fail "Required writable directory is missing: ${directory}"
    gis_assert_inside_base "$directory" >/dev/null
done
exec 9>"${GIS_LOCK_DIR}/gis-build.lock"
flock -n 9 || gis_fail 'Another GIS build is already running.'
[[ "$expected_md5" =~ ^[0-9a-f]{32}$ && "$(gis_md5 "$pbf")" == "$expected_md5" ]] \
    || gis_fail 'Selected PBF no longer matches its verified manifest.'
[[ -x /usr/bin/time ]] || gis_fail 'GNU /usr/bin/time is required for peak-resource metrics.'
planetiler_jar="${PLANETILER_JAR:-/opt/planetiler/planetiler.jar}"
[[ -s "$planetiler_jar" ]] || gis_fail 'Pinned Planetiler JAR is missing.'
expected_planetiler_sha="${PLANETILER_JAR_SHA256:-}"
expected_planetiler_sha_lower="$(printf '%s' "$expected_planetiler_sha" | tr '[:upper:]' '[:lower:]')"
[[ "$expected_planetiler_sha" =~ ^[0-9a-fA-F]{64}$ \
    && "$(gis_sha256 "$planetiler_jar")" == "$expected_planetiler_sha_lower" ]] \
    || gis_fail 'Planetiler JAR does not match PLANETILER_JAR_SHA256.'
[[ -n "${PLANETILER_VERSION:-}" ]] || gis_fail 'PLANETILER_VERSION must pin the installed release.'
[[ -n "${GIS_PMTILES_CLI_VERSION:-}" \
    && "$(pmtiles version 2>/dev/null | head -n1)" == *"${GIS_PMTILES_CLI_VERSION}"* ]] \
    || gis_fail 'PMTiles CLI does not match GIS_PMTILES_CLI_VERSION.'
assets="${GIS_MAP_ASSETS_DIR:-}"
[[ -n "$assets" && -d "$assets/fonts/Noto Sans Regular" ]] \
    || gis_fail 'GIS_MAP_ASSETS_DIR with Noto Sans Regular glyphs is required.'
for asset in \
    "$assets/fonts/Noto Sans Regular/0-255.pbf" \
    "$assets/fonts/Noto Sans Regular/1024-1279.pbf" \
    "$assets/fonts/Noto Sans Regular/1280-1535.pbf" \
    "$assets/fonts/Noto Sans Regular/8192-8447.pbf" \
    "$assets/licenses/MapLibre-Demo-Tiles.BSD-3-Clause.txt" \
    "$assets/licenses/Noto-Sans.OFL-1.1.txt" \
    "$assets/sprites/basic.json" \
    "$assets/sprites/basic.png" \
    "$assets/sprites/basic@2x.json" \
    "$assets/sprites/basic@2x.png"
do
    [[ -s "$asset" ]] || gis_fail "Required pinned map asset is missing: ${asset}"
done
[[ -s "$assets/SHA256SUMS" ]] || gis_fail 'Pinned map assets need a SHA256SUMS manifest.'
php "${GIS_SCRIPT_DIR}/validate-map-assets.php" "$assets"

[[ ! -e "${GIS_RELEASES_DIR}/${release}" ]] || gis_fail 'Immutable release already exists.'
release_stage="${GIS_STAGING_DIR}/${release}"
if [[ -e "$release_stage" ]]; then
    [[ -d "$release_stage" && ! -L "$release_stage" ]] || gis_fail 'Release staging path must be a real directory.'
else
    mkdir -- "$release_stage"
fi
release_stage="$(gis_assert_inside_base "$release_stage")"
component="${release_stage}/map"
if [[ -e "$component" ]]; then
    [[ -d "$component" && ! -L "$component" ]] || gis_fail 'PMTiles component path must be a real directory.'
    component="$(gis_assert_inside_base "$component")"
fi
component_manifest="${component}/component-manifest.json"
if [[ -s "$component_manifest" ]]; then
    existing_md5="$(MANIFEST="$component_manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true);echo strtolower((string)($v["pbf_md5"]??""));')"
    [[ "$existing_md5" == "$expected_md5" ]] || gis_fail 'Existing staged PMTiles belongs to another PBF.'
    existing_pmtiles_sha="$(MANIFEST="$component_manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true);echo strtolower((string)($v["sha256"]??""));')"
    existing_assets_sha="$(MANIFEST="$component_manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true);echo strtolower((string)($v["assets_manifest_sha256"]??""));')"
    [[ -s "${component}/russia.pmtiles" && "$existing_pmtiles_sha" =~ ^[0-9a-f]{64}$ \
        && "$(gis_sha256 "${component}/russia.pmtiles")" == "$existing_pmtiles_sha" ]] \
        || gis_fail 'Existing staged PMTiles failed checksum verification.'
    [[ -s "${component}/assets/SHA256SUMS" && "$existing_assets_sha" =~ ^[0-9a-f]{64}$ \
        && "$(gis_sha256 "${component}/assets/SHA256SUMS")" == "$existing_assets_sha" ]] \
        || gis_fail 'Existing staged map assets manifest failed checksum verification.'
    php "${GIS_SCRIPT_DIR}/validate-map-assets.php" "${component}/assets"
    gis_low_priority pmtiles verify "${component}/russia.pmtiles"
    gis_log "Verified staged PMTiles component already exists: ${component}"
    exit 0
fi
[[ ! -e "$component" ]] || gis_fail 'Incomplete PMTiles staging directory exists; inspect it manually.'

work_root="${GIS_STAGING_DIR}/planetiler-work"
if [[ -e "$work_root" ]]; then
    [[ -d "$work_root" && ! -L "$work_root" ]] || gis_fail 'Planetiler work root must be a real directory.'
else
    mkdir -- "$work_root"
fi
work_root="$(gis_assert_inside_base "$work_root")"
work_dir="${work_root}/${release}"
if [[ -e "$work_dir" ]]; then
    [[ -d "$work_dir" && ! -L "$work_dir" ]] || gis_fail 'Planetiler release work path must be a real directory.'
else
    mkdir -- "$work_dir"
fi
work_dir="$(gis_assert_inside_base "$work_dir")"
mkdir -- "$component"
component="$(gis_assert_inside_base "$component")"
mkdir -- "$component/assets"
pmtiles_file="${component}/russia.pmtiles"
metrics="${component}/resource-usage.log"
build_log="${GIS_LOG_DIR}/planetiler-${release}-$(date -u +%Y%m%dT%H%M%SZ).log"
pbf_size="$(gis_file_size "$pbf")"
heap_mb="${GIS_PLANETILER_HEAP_MB:-$(( (pbf_size + 2097151) / 2097152 ))}"
(( heap_mb >= 2048 )) || heap_mb=2048
started_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
started_epoch="$(date +%s)"

{
    gis_log "Building native OpenMapTiles-compatible PMTiles ${release}; active map remains untouched."
    (
        cd "$work_dir"
        /usr/bin/time -v -o "$metrics" \
            nice -n "${GIS_BUILD_NICE:-10}" ionice -c2 -n "${GIS_BUILD_IONICE:-7}" \
            java "-Xmx${heap_mb}m" -jar "$planetiler_jar" \
                --osm-path="$pbf" \
                --output="$pmtiles_file" \
                --download
    )
    gis_low_priority pmtiles verify "$pmtiles_file"
    pmtiles show "$pmtiles_file" --header-json > "$component/pmtiles-header.json"
    pmtiles show "$pmtiles_file" --metadata > "$component/pmtiles-metadata.json"
} > >(tee -a "$build_log") 2>&1

[[ -s "$pmtiles_file" && -s "$component/pmtiles-header.json" ]] \
    || gis_fail 'Planetiler/PMTiles validation did not create all required artifacts.'
cp -a -- "$assets/fonts" "$component/assets/fonts"
cp -a -- "$assets/licenses" "$component/assets/licenses"
cp -a -- "$assets/sprites" "$component/assets/sprites"
cp -- "$assets/SHA256SUMS" "$component/assets/SHA256SUMS"
finished_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
duration="$(( $(date +%s) - started_epoch ))"
size="$(gis_file_size "$pmtiles_file")"
sha256="$(gis_sha256 "$pmtiles_file")"
assets_manifest_sha256="$(gis_sha256 "$component/assets/SHA256SUMS")"
peak_rss_kb="$(awk -F: '/Maximum resident set size/{gsub(/[^0-9]/, "", $2); if ($2+0 > max) max=$2+0} END {print max+0}' "$metrics")"
java_version="$(java -version 2>&1 | head -n1)"
planetiler_version="$PLANETILER_VERSION"

export COMPONENT_RELEASE="$release" COMPONENT_PBF_MD5="$expected_md5" COMPONENT_PBF_SIZE="$pbf_size"
export COMPONENT_PLANETILER="$planetiler_version" COMPONENT_JAVA="$java_version" COMPONENT_STARTED="$started_at" COMPONENT_FINISHED="$finished_at"
export COMPONENT_DURATION="$duration" COMPONENT_SIZE="$size" COMPONENT_SHA256="$sha256" COMPONENT_PEAK_RSS="$peak_rss_kb"
export COMPONENT_ASSETS_SHA256="$assets_manifest_sha256"
php -r '
    $value = [
        "component" => "pmtiles", "status" => "built", "release" => getenv("COMPONENT_RELEASE"),
        "schema" => "OpenMapTiles", "spec_version" => 3, "pbf_md5" => getenv("COMPONENT_PBF_MD5"),
        "pbf_size_bytes" => (int)getenv("COMPONENT_PBF_SIZE"), "planetiler_version" => getenv("COMPONENT_PLANETILER"),
        "java_version" => getenv("COMPONENT_JAVA"), "started_at" => getenv("COMPONENT_STARTED"),
        "finished_at" => getenv("COMPONENT_FINISHED"), "build_duration_seconds" => (int)getenv("COMPONENT_DURATION"),
        "size_bytes" => (int)getenv("COMPONENT_SIZE"), "sha256" => getenv("COMPONENT_SHA256"),
        "assets_manifest_sha256" => getenv("COMPONENT_ASSETS_SHA256"),
        "peak_rss_kb" => (int)getenv("COMPONENT_PEAK_RSS")
    ];
    file_put_contents($argv[1], json_encode($value, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL, LOCK_EX);
' "${component_manifest}.part"
mv -- "${component_manifest}.part" "$component_manifest"
gis_log "PMTiles staging build ready: ${pmtiles_file} (${size} bytes, ${duration}s, SHA256 ${sha256})."
gis_log 'It is not public or active until the paired release passes smoke tests.'
