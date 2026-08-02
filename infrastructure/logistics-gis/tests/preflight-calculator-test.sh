#!/usr/bin/env bash

set -Eeuo pipefail
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
calculator="${script_dir}/../scripts/calculate-preflight.php"

run_case() {
    local expected="$1"
    local expected_exit="$2"
    local input="$3"
    local output
    output="$(printf '%s' "$input" | php "$calculator")"
    php -r '
        $result = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
        if (($result["result"] ?? null) !== $argv[1]) {
            fwrite(STDERR, "Expected {$argv[1]}, got ".($result["result"] ?? "missing").".\n");
            exit(1);
        }
        if ((int) ($result["exit_code"] ?? -1) !== (int) $argv[2]) {
            fwrite(STDERR, "Expected exit code {$argv[2]}, got ".($result["exit_code"] ?? "missing").".\n");
            exit(1);
        }
    ' "$expected" "$expected_exit" <<< "$output"
}

base='{
  "mode":"full",
  "checked_at":"2026-08-02T00:00:00Z",
  "cpu":{"logical_cores":16,"load_average":{"one":2}},
  "memory":{"available_bytes":68719476736,"swap_total_bytes":8589934592},
  "disk":{"free_bytes":536870912000,"free_inodes":1000000,"writable":true},
  "pbf":{"remote_size_bytes":4294967296},
  "existing":{"active_graph_bytes":8589934592,"active_pmtiles_bytes":6442450944},
  "components":{"java_major":21},
  "valhalla":{"healthy":true},
  "thresholds":{"app_disk_reserve_bytes":21474836480,"app_ram_reserve_bytes":4294967296}
}'
run_case PASS 0 "$base"
run_case PASS 0 "$(php -r '$v=json_decode(stream_get_contents(STDIN),true);$v["mode"]="verify";$v["components"]["java_major"]=17;echo json_encode($v);' <<< "$base")"

run_case WARN 2 "$(php -r '$v=json_decode(stream_get_contents(STDIN),true);$v["cpu"]["load_average"]["one"]=12;echo json_encode($v);' <<< "$base")"
run_case FAIL 3 "$(php -r '$v=json_decode(stream_get_contents(STDIN),true);$v["disk"]["free_bytes"]=1073741824;echo json_encode($v);' <<< "$base")"
run_case FAIL 3 "$(php -r '$v=json_decode(stream_get_contents(STDIN),true);$v["components"]["java_major"]=17;echo json_encode($v);' <<< "$base")"
run_case FAIL 3 "$(php -r '$v=json_decode(stream_get_contents(STDIN),true);$v["valhalla"]["healthy"]=false;echo json_encode($v);' <<< "$base")"

printf 'preflight calculator: PASS/WARN/FAIL fixtures passed\n'
