#!/usr/bin/env bash

set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

[[ $# -ge 1 && $# -le 2 ]] || gis_fail 'Usage: smoke-test-release.sh RELEASE_DIR [BASE_URL]'
release_dir="$(gis_realpath_existing "$1")"
gis_assert_inside_base "$release_dir" >/dev/null
config="${release_dir}/valhalla/valhalla.json"
[[ -s "$config" ]] || gis_fail 'Release Valhalla config is required.'
base_url="${2:-}"
external_service=false
[[ -z "$base_url" ]] || external_service=true
service_pid=""
smoke_config=""
status_response=""
smoke_completed=false
output="${GIS_SMOKE_OUTPUT:-}"
if [[ -z "$output" ]]; then
    if $external_service; then
        output="${GIS_STATE_DIR}/last-production-smoke.json"
    else
        output="${release_dir}/smoke-tests.json"
    fi
fi
output_directory="$(dirname "$output")"
[[ -d "$output_directory" && -w "$output_directory" ]] || gis_fail 'Smoke output directory is not writable.'
gis_assert_inside_base "$output_directory" >/dev/null
[[ ! -L "$output" ]] || gis_fail 'Smoke output must not be a symlink.'
output_part="${output}.part.$$"
results_file="$(mktemp "${GIS_STATE_DIR:-/tmp}/smoke-results.XXXXXXXX")"
service_log="${GIS_LOG_DIR}/smoke-$(basename "$release_dir")-$(date -u +%Y%m%dT%H%M%SZ).log"

cleanup() {
    local status="$?"
    if [[ -n "$service_pid" ]] && kill -0 "$service_pid" 2>/dev/null; then
        kill "$service_pid" 2>/dev/null || true
        wait "$service_pid" 2>/dev/null || true
    fi
    [[ -z "$smoke_config" || ! -f "$smoke_config" ]] || rm -- "$smoke_config"
    [[ -z "$status_response" || ! -f "$status_response" ]] || rm -- "$status_response"
    [[ ! -f "$results_file" ]] || rm -- "$results_file"
    if (( status != 0 )) && ! $smoke_completed; then
        SMOKE_RELEASE="$(basename "$release_dir")" SMOKE_KIND="$($external_service && printf production || printf staging)" \
            SMOKE_OUTPUT="$output_part" php -r '
                $value=["status"=>"failed","kind"=>getenv("SMOKE_KIND"),"release"=>getenv("SMOKE_RELEASE"),"checked_at"=>gmdate("c")];
                file_put_contents(getenv("SMOKE_OUTPUT"),json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL,LOCK_EX);
            ' 2>/dev/null || true
        [[ ! -f "$output_part" ]] || mv -f -- "$output_part" "$output" || true
    fi
    return "$status"
}
trap cleanup EXIT

if [[ -z "$base_url" ]]; then
    smoke_port="${VALHALLA_SMOKE_PORT:-18002}"
    [[ "$smoke_port" =~ ^[0-9]+$ && "$smoke_port" -ge 1024 && "$smoke_port" -le 65535 ]] \
        || gis_fail 'VALHALLA_SMOKE_PORT must be an unprivileged TCP port.'
    smoke_config="${release_dir}/valhalla/.valhalla-smoke-$$.json"
    CONFIG_SOURCE="$config" CONFIG_TARGET="$smoke_config" SMOKE_PORT="$smoke_port" php -r '
        $config=json_decode((string)file_get_contents(getenv("CONFIG_SOURCE")),true,flags:JSON_THROW_ON_ERROR);
        $config["httpd"]["service"]["listen"]="tcp://127.0.0.1:".getenv("SMOKE_PORT");
        $suffix=getmypid();
        foreach (["loopback","interrupt"] as $key) {
            if (isset($config["httpd"]["service"][$key])) {
                $config["httpd"]["service"][$key]="ipc:///tmp/pischeprom-valhalla-smoke-{$suffix}-{$key}";
            }
        }
        file_put_contents(getenv("CONFIG_TARGET"),json_encode($config,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL,LOCK_EX);
    '
    nice -n "${GIS_SMOKE_NICE:-5}" valhalla_service "$smoke_config" "${VALHALLA_SMOKE_THREADS:-2}" > "$service_log" 2>&1 &
    service_pid="$!"
    base_url="http://127.0.0.1:${smoke_port}"
fi
base_url="${base_url%/}"

for attempt in $(seq 1 60); do
    if curl --fail --silent --max-time 5 "${base_url}/status" >/dev/null; then
        break
    fi
    if [[ -n "$service_pid" ]] && ! kill -0 "$service_pid" 2>/dev/null; then
        gis_fail "Staging Valhalla exited before healthcheck; inspect ${service_log}."
    fi
    [[ "$attempt" -lt 60 ]] || gis_fail 'Valhalla did not become healthy within 60 seconds.'
    sleep 1
done

status_response="$(mktemp "${GIS_STATE_DIR:-/tmp}/smoke-status.XXXXXXXX")"
curl --fail --silent --show-error --max-time 10 "${base_url}/status" > "$status_response"
[[ -n "${VALHALLA_EXPECTED_VERSION:-}" ]] || gis_fail 'VALHALLA_EXPECTED_VERSION is required for smoke tests.'
EXPECTED_VERSION="$VALHALLA_EXPECTED_VERSION" php -r '
    $v=json_decode((string)file_get_contents($argv[1]),true,flags:JSON_THROW_ON_ERROR);
    $version=is_string($v["version"]??null)?$v["version"]:"";
    if($version===""||!str_contains($version,(string)getenv("EXPECTED_VERSION")))exit(1);
    echo json_encode(["name"=>"Valhalla /status","type"=>"health","version"=>$version],JSON_UNESCAPED_SLASHES).PHP_EOL;
' "$status_response" >> "$results_file"
rm -- "$status_response"

validate_route() {
    local name="$1" costing="$2" from_lat="$3" from_lon="$4" to_lat="$5" to_lon="$6"
    local response_file
    response_file="$(mktemp "${GIS_STATE_DIR:-/tmp}/smoke-route.XXXXXXXX")"
    curl --fail-with-body --silent --show-error --max-time "${GIS_SMOKE_TIMEOUT:-180}" \
        --header 'Content-Type: application/json' \
        --data "{\"locations\":[{\"lat\":${from_lat},\"lon\":${from_lon}},{\"lat\":${to_lat},\"lon\":${to_lon}}],\"costing\":\"${costing}\",\"units\":\"kilometers\",\"shape_format\":\"polyline6\"}" \
        "${base_url}/route" > "$response_file"
    TEST_NAME="$name" TEST_COSTING="$costing" TEST_FROM_LAT="$from_lat" TEST_FROM_LON="$from_lon" TEST_TO_LAT="$to_lat" TEST_TO_LON="$to_lon" php -r '
        $v=json_decode((string)file_get_contents($argv[1]),true,flags:JSON_THROW_ON_ERROR);
        $distance=$v["trip"]["summary"]["length"]??null;
        $duration=$v["trip"]["summary"]["time"]??null;
        $shape=$v["trip"]["legs"][0]["shape"]??null;
        if(!is_numeric($distance)||$distance<=0||!is_numeric($duration)||$duration<=0||!is_string($shape)||strlen($shape)<10) exit(1);
        $lat1=deg2rad((float)getenv("TEST_FROM_LAT"));$lat2=deg2rad((float)getenv("TEST_TO_LAT"));
        $dLat=$lat2-$lat1;$dLon=deg2rad((float)getenv("TEST_TO_LON")-(float)getenv("TEST_FROM_LON"));
        $a=sin($dLat/2)**2+cos($lat1)*cos($lat2)*sin($dLon/2)**2;
        $straight=6371.0088*2*atan2(sqrt($a),sqrt(max(0,1-$a)));
        if((float)$distance<=$straight*1.001)exit(1);
        echo json_encode(["name"=>getenv("TEST_NAME"),"type"=>"route","costing"=>getenv("TEST_COSTING"),"distance_km"=>(float)$distance,"straight_line_km"=>round($straight,3),"duration_s"=>(int)$duration,"geometry"=>true],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
    ' "$response_file" >> "$results_file"
    rm -- "$response_file"
}

validate_route 'Санкт-Петербург → Псков' auto 59.938784 30.314997 57.819274 28.331786
validate_route 'Москва → Нижний Новгород' auto 55.755826 37.617300 56.326887 44.005986
validate_route 'Екатеринбург → Тюмень' auto 56.838926 60.605703 57.152985 65.541227
validate_route 'Новосибирск → Красноярск' auto 55.008353 82.935733 56.015283 92.893248
validate_route 'Хабаровск → Владивосток' auto 48.480223 135.071917 43.115536 131.885485
validate_route 'Москва → Новосибирск' auto 55.755826 37.617300 55.008353 82.935733
validate_route 'Москва → Нижний Новгород (truck)' truck 55.755826 37.617300 56.326887 44.005986

matrix_response="$(mktemp "${GIS_STATE_DIR:-/tmp}/smoke-matrix.XXXXXXXX")"
curl --fail-with-body --silent --show-error --max-time "${GIS_SMOKE_TIMEOUT:-180}" \
    --header 'Content-Type: application/json' \
    --data '{"sources":[{"lat":55.755826,"lon":37.617300},{"lat":56.326887,"lon":44.005986},{"lat":56.838926,"lon":60.605703}],"targets":[{"lat":55.755826,"lon":37.617300},{"lat":56.326887,"lon":44.005986},{"lat":56.838926,"lon":60.605703}],"costing":"truck","units":"kilometers","verbose":false}' \
    "${base_url}/sources_to_targets" > "$matrix_response"
php -r '
    $v=json_decode((string)file_get_contents($argv[1]),true,flags:JSON_THROW_ON_ERROR);
    $distances=$v["sources_to_targets"]["distances"]??null;
    $durations=$v["sources_to_targets"]["durations"]??null;
    if(!is_array($distances)||count($distances)!==3||!is_array($durations)||count($durations)!==3) exit(1);
    foreach($distances as $i=>$row){if(!is_array($row)||count($row)!==3)exit(1);foreach($row as $j=>$value){if($i!==$j&&(!is_numeric($value)||$value<=0))exit(1);}}
    echo json_encode(["name"=>"Матрица трёх городов","type"=>"matrix","costing"=>"truck","cells"=>9],JSON_UNESCAPED_UNICODE).PHP_EOL;
' "$matrix_response" >> "$results_file"
rm -- "$matrix_response"

SMOKE_RESULTS="$results_file" SMOKE_OUTPUT="$output_part" SMOKE_RELEASE="$(basename "$release_dir")" SMOKE_KIND="$($external_service && printf production || printf staging)" php -r '
    $results=[];
    foreach(file(getenv("SMOKE_RESULTS"),FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){$results[]=json_decode($line,true,flags:JSON_THROW_ON_ERROR);}
    $value=["status"=>"passed","kind"=>getenv("SMOKE_KIND"),"release"=>getenv("SMOKE_RELEASE"),"checked_at"=>gmdate("c"),"results"=>$results];
    file_put_contents(getenv("SMOKE_OUTPUT"),json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL,LOCK_EX);
'
mv -- "$output_part" "$output"
smoke_completed=true
gis_log "All-Russia Valhalla smoke tests passed against ${base_url}."
