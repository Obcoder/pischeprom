#!/usr/bin/env bash

set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

[[ $# -eq 1 ]] || gis_fail 'Usage: check-pmtiles-range.sh https://same-origin.example/maps/logistics/russia.pmtiles'
url="$1"
[[ "$url" =~ ^https?:// ]] || gis_fail 'Range test needs the public same-origin absolute URL.'
[[ -d "$GIS_STATE_DIR" && -w "$GIS_STATE_DIR" ]] || gis_fail 'GIS state directory is not writable.'
body="$(mktemp "${GIS_STATE_DIR}/range-body.XXXXXXXX")"
headers="$(mktemp "${GIS_STATE_DIR}/range-headers.XXXXXXXX")"
head_headers="$(mktemp "${GIS_STATE_DIR}/head-headers.XXXXXXXX")"
cleanup() { rm -- "$body" "$headers" "$head_headers"; }
trap cleanup EXIT

head_status=""
if ! head_status="$(curl --silent --show-error --location --head --dump-header "$head_headers" --output /dev/null --write-out '%{http_code}' "$url")"; then
    head_status="${head_status:-000}"
fi
head_length="$(awk '{line=tolower($0)} line ~ /^content-length:/{sub(/^[^:]+:[[:space:]]*/,"");sub(/\r$/ ,"");value=$0}END{gsub(/[^0-9]/,"",value);if(value=="")print 0;else print value}' "$head_headers")"
head_type="$(awk '{line=tolower($0)} line ~ /^content-type:/{sub(/^[^:]+:[[:space:]]*/,"");sub(/\r$/ ,"");value=tolower($0)}END{print value}' "$head_headers")"
status=""
if ! status="$(curl --silent --show-error --location --range 0-15 --dump-header "$headers" --output "$body" --write-out '%{http_code}' "$url")"; then
    status="${status:-000}"
fi
accept_ranges="$(awk '{line=tolower($0)} line ~ /^accept-ranges:/{sub(/^[^:]+:[[:space:]]*/,"");sub(/\r$/,"");value=tolower($0)}END{print value}' "$headers")"
content_range="$(awk '{line=tolower($0)} line ~ /^content-range:/{sub(/^[^:]+:[[:space:]]*/,"");sub(/\r$/,"");value=tolower($0)}END{print value}' "$headers")"
bytes="$(gis_file_size "$body")"
range_total="$(printf '%s' "$content_range" | awk -F/ '{value=$2}END{gsub(/[^0-9]/,"",value);if(value=="")print 0;else print value}')"
healthy=false
if [[ "$head_status" == "200" && "$head_length" -gt 0 \
    && "$head_type" == application/vnd.pmtiles* \
    && "$status" == "206" && "$bytes" == "16" && "$accept_ranges" == "bytes" \
    && "$content_range" == bytes\ 0-15/* && "$range_total" == "$head_length" ]]
then
    healthy=true
fi

export RANGE_HEALTHY="$healthy" RANGE_STATUS="$status" RANGE_ACCEPT="$accept_ranges" RANGE_CONTENT="$content_range" RANGE_BYTES="$bytes"
export RANGE_HEAD_STATUS="$head_status" RANGE_HEAD_LENGTH="$head_length" RANGE_HEAD_TYPE="$head_type"
php -r '
    $value=["healthy"=>getenv("RANGE_HEALTHY")==="true","head_status_code"=>(int)getenv("RANGE_HEAD_STATUS"),"content_length"=>(int)getenv("RANGE_HEAD_LENGTH"),"content_type"=>getenv("RANGE_HEAD_TYPE"),"status_code"=>(int)getenv("RANGE_STATUS"),"accept_ranges"=>getenv("RANGE_ACCEPT"),"content_range"=>getenv("RANGE_CONTENT"),"response_bytes"=>(int)getenv("RANGE_BYTES"),"checked_at"=>gmdate("c")];
    file_put_contents($argv[1],json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL,LOCK_EX);
' "${GIS_STATE_DIR}/.last-range-check.json.$$"
mv -- "${GIS_STATE_DIR}/.last-range-check.json.$$" "${GIS_STATE_DIR}/last-range-check.json"
$healthy || gis_fail "PMTiles HTTP check failed: HEAD=${head_status}/${head_length}/${head_type}; Range=${status}, Accept-Ranges=${accept_ranges}, Content-Range=${content_range}, bytes=${bytes}."
gis_log 'PMTiles HEAD and Range/206 checks passed.'
