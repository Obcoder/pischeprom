#!/usr/bin/env bash

set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

[[ $# -eq 1 ]] || gis_fail 'Usage: check-pmtiles-range.sh https://maps.example/maps/logistics/russia.pmtiles'
url="$1"
[[ "$url" =~ ^https:// ]] || gis_fail 'Range test needs a public HTTPS URL.'
[[ -d "$GIS_STATE_DIR" && -w "$GIS_STATE_DIR" ]] || gis_fail 'GIS state directory is not writable.'
body="$(mktemp "${GIS_STATE_DIR}/range-body.XXXXXXXX")"
headers="$(mktemp "${GIS_STATE_DIR}/range-headers.XXXXXXXX")"
head_headers="$(mktemp "${GIS_STATE_DIR}/head-headers.XXXXXXXX")"
cleanup() { rm -- "$body" "$headers" "$head_headers"; }
trap cleanup EXIT

head_status=""
cors_origin="${GIS_EXPECTED_CORS_ORIGIN:-}"
curl_headers=()
if [[ -n "$cors_origin" ]]; then
    [[ "$cors_origin" =~ ^https://[^/?#]+$ ]] || gis_fail 'GIS_EXPECTED_CORS_ORIGIN must be an HTTPS origin without a path.'
    curl_headers+=(--header "Origin: ${cors_origin}")
fi
if ! head_status="$(curl --silent --show-error --location "${curl_headers[@]}" --head --dump-header "$head_headers" --output /dev/null --write-out '%{http_code}' "$url")"; then
    head_status="${head_status:-000}"
fi
head_length="$(awk '{line=tolower($0)} line ~ /^content-length:/{sub(/^[^:]+:[[:space:]]*/,"");sub(/\r$/ ,"");value=$0}END{gsub(/[^0-9]/,"",value);if(value=="")print 0;else print value}' "$head_headers")"
head_type="$(awk '{line=tolower($0)} line ~ /^content-type:/{sub(/^[^:]+:[[:space:]]*/,"");sub(/\r$/ ,"");value=tolower($0)}END{print value}' "$head_headers")"
status=""
if ! status="$(curl --silent --show-error --location "${curl_headers[@]}" --range 0-15 --dump-header "$headers" --output "$body" --write-out '%{http_code}' "$url")"; then
    status="${status:-000}"
fi
accept_ranges="$(awk '{line=tolower($0)} line ~ /^accept-ranges:/{sub(/^[^:]+:[[:space:]]*/,"");sub(/\r$/,"");value=tolower($0)}END{print value}' "$headers")"
content_range="$(awk '{line=tolower($0)} line ~ /^content-range:/{sub(/^[^:]+:[[:space:]]*/,"");sub(/\r$/,"");value=tolower($0)}END{print value}' "$headers")"
bytes="$(gis_file_size "$body")"
range_total="$(printf '%s' "$content_range" | awk -F/ '{value=$2}END{gsub(/[^0-9]/,"",value);if(value=="")print 0;else print value}')"
cors_allow="$(awk '{line=tolower($0)} line ~ /^access-control-allow-origin:/{sub(/^[^:]+:[[:space:]]*/,"");sub(/\r$/ ,"");value=$0}END{print value}' "$headers")"
cors_expose="$(awk '{line=tolower($0)} line ~ /^access-control-expose-headers:/{sub(/^[^:]+:[[:space:]]*/,"");sub(/\r$/ ,"");value=tolower($0)}END{print value}' "$headers")"
cors_expose_normalized="$(printf '%s' "$cors_expose" | tr -d '[:space:]')"
healthy=false
if [[ "$head_status" == "200" && "$head_length" -gt 0 \
    && "$head_type" == application/vnd.pmtiles* \
    && "$status" == "206" && "$bytes" == "16" && "$accept_ranges" == "bytes" \
    && "$content_range" == bytes\ 0-15/* && "$range_total" == "$head_length" ]]
then
    healthy=true
fi
if [[ -n "$cors_origin" ]]; then
    for exposed_header in accept-ranges content-length content-range etag; do
        if [[ "$cors_expose_normalized" != "*" && ",${cors_expose_normalized}," != *",${exposed_header},"* ]]; then
            healthy=false
        fi
    done
    [[ "$cors_allow" == "$cors_origin" ]] || healthy=false
fi

export RANGE_HEALTHY="$healthy" RANGE_STATUS="$status" RANGE_ACCEPT="$accept_ranges" RANGE_CONTENT="$content_range" RANGE_BYTES="$bytes"
export RANGE_HEAD_STATUS="$head_status" RANGE_HEAD_LENGTH="$head_length" RANGE_HEAD_TYPE="$head_type"
export RANGE_CORS_ORIGIN="$cors_origin" RANGE_CORS_ALLOW="$cors_allow" RANGE_CORS_EXPOSE="$cors_expose"
php -r '
    $value=["healthy"=>getenv("RANGE_HEALTHY")==="true","head_status_code"=>(int)getenv("RANGE_HEAD_STATUS"),"content_length"=>(int)getenv("RANGE_HEAD_LENGTH"),"content_type"=>getenv("RANGE_HEAD_TYPE"),"status_code"=>(int)getenv("RANGE_STATUS"),"accept_ranges"=>getenv("RANGE_ACCEPT"),"content_range"=>getenv("RANGE_CONTENT"),"response_bytes"=>(int)getenv("RANGE_BYTES"),"cors_origin"=>getenv("RANGE_CORS_ORIGIN")?:null,"cors_allow_origin"=>getenv("RANGE_CORS_ALLOW")?:null,"cors_expose_headers"=>getenv("RANGE_CORS_EXPOSE")?:null,"checked_at"=>gmdate("c")];
    file_put_contents($argv[1],json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL,LOCK_EX);
' "${GIS_STATE_DIR}/.last-range-check.json.$$"
mv -- "${GIS_STATE_DIR}/.last-range-check.json.$$" "${GIS_STATE_DIR}/last-range-check.json"
$healthy || gis_fail "PMTiles HTTP check failed: HEAD=${head_status}/${head_length}/${head_type}; Range=${status}, Accept-Ranges=${accept_ranges}, Content-Range=${content_range}, bytes=${bytes}, CORS=${cors_allow}/${cors_expose}."
gis_log 'PMTiles HEAD, Range/206 and requested CORS checks passed.'
