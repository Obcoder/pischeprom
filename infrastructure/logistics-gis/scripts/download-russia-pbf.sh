#!/usr/bin/env bash

set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

"${GIS_SCRIPT_DIR}/preflight.sh" --mode download
for directory in "$GIS_SOURCE_DIR" "$GIS_LOCK_DIR" "$GIS_LOG_DIR"; do
    [[ -d "$directory" && -w "$directory" ]] \
        || gis_fail "Required writable directory is missing: ${directory}"
    gis_assert_inside_base "$directory" >/dev/null
done

exec 9>"${GIS_LOCK_DIR}/russia-pbf.lock"
flock -n 9 || gis_fail 'Another Russia PBF operation is already running.'

probe="$(curl --fail --silent --show-error --location --max-time 60 --range 0-0 --dump-header - --output /dev/null --write-out $'\nGIS_EFFECTIVE_URL=%{url_effective}\n' "$GIS_PBF_URL")"
resolved_source_url="$(printf '%s\n' "$probe" | awk -F= '/^GIS_EFFECTIVE_URL=/{value=substr($0,index($0,"=")+1)} END{print value}')"
headers="$(printf '%s\n' "$probe" | sed '/^GIS_EFFECTIVE_URL=/d')"
remote_size="$(printf '%s\n' "$headers" | awk '
    {line=tolower($0)}
    line ~ /^content-range:/ {sub(/\r$/, ""); split($0, parts, "/"); value=parts[length(parts)]}
    line ~ /^content-length:/ && value == "" {sub(/\r$/, ""); value=$2}
    END {gsub(/[^0-9]/, "", value); if (value == "") print 0; else print value}
')"
last_modified="$(printf '%s\n' "$headers" | awk '{line=tolower($0)} line ~ /^last-modified:/ {sub(/^[^:]+:[[:space:]]*/, ""); sub(/\r$/, ""); value=$0} END {print value}')"
[[ "$remote_size" =~ ^[0-9]+$ && "$remote_size" -gt 0 ]] \
    || gis_fail 'Geofabrik did not provide a usable Content-Length/Content-Range.'
[[ -n "$last_modified" ]] || gis_fail 'Geofabrik did not provide Last-Modified for the extract.'
[[ "$resolved_source_url" =~ ^https://download\.geofabrik\.de/russia-[0-9]{6}\.osm\.pbf$ ]] \
    || gis_fail 'Latest Russia extract did not resolve to an immutable Geofabrik PBF URL.'
resolved_checksum_url="${resolved_source_url}.md5"
osm_data_timestamp="$(curl --fail --silent --show-error --location --max-time 30 "$GIS_PBF_INDEX_URL" | php -r '$html=stream_get_contents(STDIN);if(preg_match("/russia-latest\\.osm\\.pbf.*?contains\\s+all\\s+OSM\\s+data\\s+up\\s+to\\s+([0-9T:+-]+Z)/si",$html,$m))echo $m[1];')"
[[ "$osm_data_timestamp" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}T ]] \
    || gis_fail 'Unable to determine the OSM data timestamp from the official Geofabrik index.'
data_date="$(printf '%s' "${osm_data_timestamp%%T*}" | tr -d '-')"
[[ "$data_date" =~ ^[0-9]{8}$ ]] || gis_fail 'Unable to derive the OSM data date from the official index timestamp.'
resolved_date="${resolved_source_url##*-}"
resolved_date="${resolved_date%.osm.pbf}"
[[ "$resolved_date" == "${data_date:2}" ]] \
    || gis_fail 'Immutable PBF filename and official OSM data timestamp refer to different snapshots.'

release="russia-${data_date}"
pbf="${GIS_SOURCE_DIR}/${release}.osm.pbf"
pbf_part="${pbf}.part"
checksum_file="${GIS_SOURCE_DIR}/${release}.osm.pbf.md5"
checksum_part="${checksum_file}.part"
manifest="${GIS_SOURCE_DIR}/${release}.manifest.json"
manifest_part="${manifest}.part"
for operation_path in "$pbf" "$pbf_part" "$checksum_file" "$checksum_part" "$manifest" "$manifest_part"; do
    [[ ! -L "$operation_path" ]] || gis_fail "Refusing a symlinked download/manifest path: ${operation_path}"
done

curl --fail --silent --show-error --location --max-time 60 "$resolved_checksum_url" --output "$checksum_part"
expected_md5="$(awk 'NR==1 {print tolower($1)}' "$checksum_part")"
[[ "$expected_md5" =~ ^[0-9a-f]{32}$ ]] || gis_fail 'Published Geofabrik MD5 is invalid.'

if [[ -s "$pbf" && -s "$manifest" ]]; then
    existing_size="$(gis_file_size "$pbf")"
    existing_md5="$(gis_md5 "$pbf")"
    manifest_md5="$(MANIFEST="$manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true);echo strtolower((string)($v["md5"]??""));')"
    if [[ "$existing_size" == "$remote_size" && "$existing_md5" == "$expected_md5" && "$manifest_md5" == "$expected_md5" ]]; then
        mv -f -- "$checksum_part" "$checksum_file"
        gis_log "Verified ${release} already exists; download skipped."
        printf '%s\n' "$manifest"
        exit 0
    fi
    gis_fail "Immutable target ${pbf} exists but does not match the current verified extract."
fi
[[ ! -e "$manifest" ]] \
    || gis_fail 'A source manifest exists without a valid matching immutable PBF.'
final_pbf_ready=false
if [[ -e "$pbf" ]]; then
    [[ -f "$pbf" && ! -e "$pbf_part" ]] \
        || gis_fail 'Ambiguous immutable PBF recovery state; inspect it manually.'
    recovered_size="$(gis_file_size "$pbf")"
    recovered_md5="$(gis_md5 "$pbf")"
    [[ "$recovered_size" == "$remote_size" && "$recovered_md5" == "$expected_md5" ]] \
        || gis_fail 'Incomplete immutable PBF target failed recovery verification.'
    final_pbf_ready=true
    gis_log 'Recovered a checksum-verified final PBF left before the manifest commit; transfer will not be repeated.'
fi

gis_log "Preparing verified/resumable transfer from ${GIS_PBF_URL} to ${pbf_part}."
http_status=""
downloaded_url="$resolved_source_url"
download_mode="recovered_verified_final"
download_candidate="$pbf"
if ! $final_pbf_ready; then
    [[ ! -e "$pbf_part" || -f "$pbf_part" ]] || gis_fail 'PBF .part path exists but is not a regular file.'
    part_size="$(gis_file_size "$pbf_part")"
    (( part_size <= remote_size )) \
        || gis_fail "Resumable PBF is larger than the published extract: ${part_size} > ${remote_size}."
    download_mode="verified_existing_part"
    download_candidate="$pbf_part"
    if (( part_size < remote_size )); then
        download_result="$(curl \
            --fail \
            --location \
            --show-error \
            --continue-at - \
            --output "$pbf_part" \
            --write-out $'%{http_code}\n%{url_effective}' \
            "$resolved_source_url")"
        http_status="$(printf '%s\n' "$download_result" | sed -n '1p')"
        downloaded_url="$(printf '%s\n' "$download_result" | sed -n '2p')"
        [[ "$http_status" == "200" || "$http_status" == "206" ]] \
            || gis_fail "Unexpected PBF HTTP status: ${http_status}"
        [[ "$downloaded_url" == "$resolved_source_url" ]] \
            || gis_fail 'Downloaded PBF resolved to a different URL than the verified metadata probe.'
        download_mode="$([[ "$part_size" -gt 0 ]] && printf resumed || printf fresh)"
    else
        gis_log 'The .part file already has the exact remote size; transfer skipped and full MD5 verification resumed.'
    fi
else
    gis_log 'Final PBF recovery verification completed; rebuilding only its checksum/manifest commit.'
fi
if $final_pbf_ready; then
    actual_size="$recovered_size"
    actual_md5="$recovered_md5"
else
    actual_size="$(gis_file_size "$download_candidate")"
    actual_md5="$(gis_md5 "$download_candidate")"
fi
[[ "$actual_size" == "$remote_size" ]] \
    || gis_fail "Downloaded size mismatch: expected ${remote_size}, got ${actual_size}."
[[ "$actual_md5" == "$expected_md5" ]] \
    || gis_fail 'PBF MD5 mismatch; .part is preserved for investigation and nothing is activated.'

export MANIFEST_SOURCE_URL="$GIS_PBF_URL" MANIFEST_RESOLVED_SOURCE_URL="$resolved_source_url" MANIFEST_MD5_ALIAS_URL="$GIS_PBF_MD5_URL" MANIFEST_MD5_URL="$resolved_checksum_url"
export MANIFEST_DOWNLOADED_AT="$(date -u +%Y-%m-%dT%H:%M:%SZ)" MANIFEST_DATA_DATE="$data_date"
export MANIFEST_OSM_TIMESTAMP="$osm_data_timestamp" MANIFEST_LAST_MODIFIED="$last_modified" MANIFEST_SIZE="$actual_size" MANIFEST_MD5="$actual_md5"
export MANIFEST_HTTP_STATUS="$http_status" MANIFEST_DOWNLOAD_MODE="$download_mode" MANIFEST_INITIATOR="$(id -un)" MANIFEST_RELEASE="$release"
php -r '
    $manifest = [
        "release" => getenv("MANIFEST_RELEASE"),
        "source_url" => getenv("MANIFEST_SOURCE_URL"),
        "resolved_source_url" => getenv("MANIFEST_RESOLVED_SOURCE_URL"),
        "checksum_url" => getenv("MANIFEST_MD5_URL"),
        "checksum_alias_url" => getenv("MANIFEST_MD5_ALIAS_URL"),
        "downloaded_at" => getenv("MANIFEST_DOWNLOADED_AT"),
        "data_date" => getenv("MANIFEST_DATA_DATE"),
        "osm_data_timestamp" => getenv("MANIFEST_OSM_TIMESTAMP"),
        "last_modified" => getenv("MANIFEST_LAST_MODIFIED"),
        "size_bytes" => (int) getenv("MANIFEST_SIZE"),
        "md5" => getenv("MANIFEST_MD5"),
        "http_status" => getenv("MANIFEST_HTTP_STATUS") === "" ? null : (int) getenv("MANIFEST_HTTP_STATUS"),
        "download_mode" => getenv("MANIFEST_DOWNLOAD_MODE"),
        "initiated_by" => getenv("MANIFEST_INITIATOR"),
        "verified" => true,
    ];
    $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
    if (file_put_contents($argv[1], $json, LOCK_EX) === false) exit(1);
' "$manifest_part"

if ! $final_pbf_ready; then
    mv -- "$pbf_part" "$pbf"
fi
mv -- "$checksum_part" "$checksum_file"
mv -- "$manifest_part" "$manifest"
gis_log "Verified full-Russia PBF ready: ${pbf} (${actual_size} bytes, MD5 ${actual_md5})."
gis_log 'No Valhalla graph or active release was changed.'
