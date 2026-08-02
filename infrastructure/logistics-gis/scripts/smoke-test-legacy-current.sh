#!/usr/bin/env bash

set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

[[ $# -eq 2 ]] || gis_fail 'Usage: smoke-test-legacy-current.sh RELEASE_DIR http://127.0.0.1:8002'
release_dir="$(gis_realpath_existing "$1")"
gis_assert_inside_base "$release_dir" >/dev/null
base_url="${2%/}"
[[ "$base_url" =~ ^http://127\.0\.0\.1:[0-9]{1,5}$ ]] \
    || gis_fail 'Legacy rollback smoke must use the loopback Valhalla URL.'
[[ -d "$GIS_STATE_DIR" && -w "$GIS_STATE_DIR" ]] || gis_fail 'GIS state directory is not writable.'

status_file="$(mktemp "${GIS_STATE_DIR}/legacy-status.XXXXXXXX")"
route_file="$(mktemp "${GIS_STATE_DIR}/legacy-route.XXXXXXXX")"
matrix_file="$(mktemp "${GIS_STATE_DIR}/legacy-matrix.XXXXXXXX")"
output="${GIS_STATE_DIR}/last-production-smoke.json"
output_part="${output}.part.$$"
completed=false

cleanup() {
    local status="$?"
    rm -- "$status_file" "$route_file" "$matrix_file" 2>/dev/null || true
    if (( status != 0 )) && ! $completed; then
        LEGACY_RELEASE="$(basename "$release_dir")" LEGACY_OUTPUT="$output_part" php -r '
            $value=["status"=>"failed","kind"=>"production","coverage"=>"legacy","release"=>getenv("LEGACY_RELEASE"),"checked_at"=>gmdate("c")];
            file_put_contents(getenv("LEGACY_OUTPUT"),json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL,LOCK_EX);
        ' 2>/dev/null || true
        [[ ! -f "$output_part" ]] || mv -f -- "$output_part" "$output" || true
    fi
    return "$status"
}
trap cleanup EXIT

curl --fail --silent --show-error --max-time 10 "${base_url}/status" > "$status_file"
curl --fail-with-body --silent --show-error --max-time "${GIS_SMOKE_TIMEOUT:-180}" \
    --header 'Content-Type: application/json' \
    --data '{"locations":[{"lat":59.938784,"lon":30.314997},{"lat":55.755826,"lon":37.617300}],"costing":"auto","units":"kilometers","shape_format":"polyline6"}' \
    "${base_url}/route" > "$route_file"
curl --fail-with-body --silent --show-error --max-time "${GIS_SMOKE_TIMEOUT:-180}" \
    --header 'Content-Type: application/json' \
    --data '{"sources":[{"lat":59.938784,"lon":30.314997}],"targets":[{"lat":55.755826,"lon":37.617300}],"costing":"auto","units":"kilometers","verbose":false}' \
    "${base_url}/sources_to_targets" > "$matrix_file"

LEGACY_STATUS="$status_file" LEGACY_ROUTE="$route_file" LEGACY_MATRIX="$matrix_file" \
    LEGACY_RELEASE="$(basename "$release_dir")" LEGACY_EXPECTED_VERSION="${VALHALLA_EXPECTED_VERSION:-}" \
    LEGACY_OUTPUT="$output_part" php -r '
        $status=json_decode((string)file_get_contents(getenv("LEGACY_STATUS")),true,flags:JSON_THROW_ON_ERROR);
        $route=json_decode((string)file_get_contents(getenv("LEGACY_ROUTE")),true,flags:JSON_THROW_ON_ERROR);
        $matrix=json_decode((string)file_get_contents(getenv("LEGACY_MATRIX")),true,flags:JSON_THROW_ON_ERROR);
        $version=is_string($status["version"]??null)?$status["version"]:"";
        $expected=(string)getenv("LEGACY_EXPECTED_VERSION");
        $distance=$route["trip"]["summary"]["length"]??null;
        $duration=$route["trip"]["summary"]["time"]??null;
        $shape=$route["trip"]["legs"][0]["shape"]??null;
        $matrixDistance=$matrix["sources_to_targets"]["distances"][0][0]??null;
        if($version===""||($expected!==""&&!str_contains($version,$expected))||!is_numeric($distance)||$distance<=0||!is_numeric($duration)||$duration<=0||!is_string($shape)||strlen($shape)<10||!is_numeric($matrixDistance)||$matrixDistance<=0)exit(1);
        $value=[
            "status"=>"passed","kind"=>"production","coverage"=>"legacy","release"=>getenv("LEGACY_RELEASE"),"checked_at"=>gmdate("c"),
            "results"=>[
                ["name"=>"Valhalla /status","type"=>"health","version"=>$version],
                ["name"=>"Санкт-Петербург → Москва","type"=>"route","costing"=>"auto","distance_km"=>(float)$distance,"duration_s"=>(int)$duration,"geometry"=>true],
                ["name"=>"Матрица Санкт-Петербург → Москва","type"=>"matrix","costing"=>"auto","distance_km"=>(float)$matrixDistance],
            ],
        ];
        file_put_contents(getenv("LEGACY_OUTPUT"),json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL,LOCK_EX);
    '
mv -- "$output_part" "$output"
completed=true
gis_log 'Legacy rollback routing smoke passed; full-Russia coverage and PMTiles were not claimed.'
