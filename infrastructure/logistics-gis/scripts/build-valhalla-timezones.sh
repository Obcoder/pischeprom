#!/usr/bin/env bash

set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

[[ $# -eq 1 ]] || gis_fail 'Usage: build-valhalla-timezones.sh /absolute/path/timezones.sqlite'
output="$1"
[[ "$output" == /* && "$(basename "$output")" == 'timezones.sqlite' ]] \
    || gis_fail 'Timezone output must be an absolute timezones.sqlite path.'

for command_name in curl find spatialite spatialite_tool tr unzip wc; do
    gis_require_command "$command_name"
done

output_parent="$(dirname "$output")"
[[ -d "$output_parent" && -w "$output_parent" && ! -L "$output_parent" ]] \
    || gis_fail 'Timezone output directory must be a writable real directory.'
output_parent="$(gis_assert_inside_base "$output_parent")"
[[ ! -e "$output" ]] || gis_fail 'Timezone output already exists; refusing to overwrite it.'

timezone_version='2025b'
timezone_sha256='3e63a5bdbdde627570f1fde4ac58f22c9a2214c27ddd53446ba1a8ef7c0c3720'
timezone_url="https://github.com/evansiroky/timezone-boundary-builder/releases/download/${timezone_version}/timezones-with-oceans-1970.shapefile.zip"
work_root="$(mktemp -d "${output_parent}/.timezones.XXXXXXXX")"
cleanup() {
    rm -r -- "$work_root"
}
trap cleanup EXIT

archive="${work_root}/timezones.zip"
extract_dir="${work_root}/shape"
database="${work_root}/timezones.sqlite"
mkdir -- "$extract_dir"

curl \
    --fail \
    --silent \
    --show-error \
    --location \
    --retry 4 \
    --retry-all-errors \
    --output "$archive" \
    "$timezone_url"
[[ "$(gis_sha256 "$archive")" == "$timezone_sha256" ]] \
    || gis_fail 'Timezone boundary archive failed its pinned SHA-256.'

unzip -q "$archive" -d "$extract_dir"
shape_base="${extract_dir}/combined-shapefile-with-oceans-1970"
for extension in dbf prj shp shx; do
    [[ -s "${shape_base}.${extension}" && ! -L "${shape_base}.${extension}" ]] \
        || gis_fail "Pinned timezone archive is missing ${extension} data."
done
[[ "$(find "$extract_dir" -maxdepth 1 -type f | wc -l | tr -d '[:space:]')" == '4' ]] \
    || gis_fail 'Pinned timezone archive contains an unexpected file set.'

spatialite_tool \
    -i \
    -shp "$shape_base" \
    -d "$database" \
    -t tz_world \
    -s 4326 \
    -g geom \
    -c UTF-8 >/dev/null
spatialite "$database" "SELECT CreateSpatialIndex('tz_world', 'geom');" >/dev/null
spatialite "$database" 'VACUUM;' >/dev/null
spatialite "$database" 'ANALYZE;' >/dev/null

[[ -s "$database" ]] || gis_fail 'Timezone database build produced an empty file.'
mv -- "$database" "$output"
trap - EXIT
rm -r -- "$work_root"
gis_log "Built checksum-pinned Valhalla timezone database ${timezone_version}: ${output}"
