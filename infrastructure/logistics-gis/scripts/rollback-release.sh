#!/usr/bin/env bash

set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

current_link="${GIS_BASE_DIR}/current"
previous_link="${GIS_BASE_DIR}/previous"
[[ -L "$current_link" && -L "$previous_link" ]] || gis_fail 'Both current and previous release symlinks are required.'
current_target="$(realpath -- "$current_link")"
previous_target="$(realpath -- "$previous_link")"
gis_assert_inside_base "$current_target" >/dev/null
gis_assert_inside_base "$previous_target" >/dev/null
previous_manifest="$previous_target/release-manifest.json"
[[ -s "$previous_manifest" && ! -L "$previous_manifest" ]] || gis_fail 'Previous release is not verified.'
previous_manifest_valid="$(MANIFEST="$previous_manifest" EXPECTED_RELEASE="$(basename "$previous_target")" php -r '
    try{$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true,flags:JSON_THROW_ON_ERROR);}catch(Throwable){echo "no";exit;}
    $status=$v["status"]??null;
    echo (($v["release"]??null)===getenv("EXPECTED_RELEASE")&&in_array($status,["verified","legacy"],true))?"yes":"no";
')"
[[ "$previous_manifest_valid" == "yes" ]] \
    || gis_fail 'Previous release manifest is invalid or does not match its directory.'
gis_validate_valhalla_restart
public_pmtiles_url="${GIS_PUBLIC_PMTILES_URL:-}"
[[ "$public_pmtiles_url" =~ ^https?:// ]] || gis_fail 'GIS_PUBLIC_PMTILES_URL is required.'

exec 9>"${GIS_LOCK_DIR}/gis-activate.lock"
flock -n 9 || gis_fail 'Another GIS activation/rollback is already running.'
has_pmtiles=false
[[ ! -s "$previous_target/map/russia.pmtiles" ]] || has_pmtiles=true
validate_rollback_runtime() {
    if $has_pmtiles; then
        "${GIS_SCRIPT_DIR}/smoke-test-release.sh" "$previous_target" "${VALHALLA_STATUS_URL%/status}"
    else
        "${GIS_SCRIPT_DIR}/smoke-test-legacy-current.sh" "$previous_target" "${VALHALLA_STATUS_URL%/status}"
    fi
}
gis_atomic_symlink "$previous_target" "$current_link"
gis_atomic_symlink "$current_target" "$previous_link"
if ! gis_restart_valhalla \
    || ! validate_rollback_runtime
then
    gis_atomic_symlink "$current_target" "$current_link"
    gis_atomic_symlink "$previous_target" "$previous_link"
    gis_restart_valhalla || true
    gis_fail 'Rollback target failed checks; original current release was restored.'
fi
range_status="unavailable"
if $has_pmtiles; then
    if ! "${GIS_SCRIPT_DIR}/check-pmtiles-range.sh" "$public_pmtiles_url"; then
        gis_atomic_symlink "$current_target" "$current_link"
        gis_atomic_symlink "$previous_target" "$previous_link"
        gis_restart_valhalla || true
        gis_fail 'Rollback PMTiles failed Range checks; original current release was restored.'
    fi
    range_status="passed"
else
    php -r '
        $value=["healthy"=>false,"status_code"=>null,"accept_ranges"=>null,"content_range"=>null,"response_bytes"=>null,"checked_at"=>gmdate("c"),"message"=>"Rollback target predates the visual PMTiles map."];
        file_put_contents($argv[1],json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL,LOCK_EX);
    ' "${GIS_STATE_DIR}/.last-range-check.json.$$"
    mv -- "${GIS_STATE_DIR}/.last-range-check.json.$$" "${GIS_STATE_DIR}/last-range-check.json"
fi
export ROLLBACK_CURRENT="$(basename "$previous_target")" ROLLBACK_PREVIOUS="$(basename "$current_target")"
export ROLLBACK_RANGE_STATUS="$range_status"
php -r '
    $value=["status"=>"active","release"=>getenv("ROLLBACK_CURRENT"),"previous_release"=>getenv("ROLLBACK_PREVIOUS"),"activated_at"=>gmdate("c"),"activation_kind"=>"rollback","range_requests"=>getenv("ROLLBACK_RANGE_STATUS"),"production_smoke"=>"passed"];
    file_put_contents($argv[1],json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL,LOCK_EX);
' "${GIS_STATE_DIR}/.last-activation.json.$$"
mv -- "${GIS_STATE_DIR}/.last-activation.json.$$" "${GIS_STATE_DIR}/last-activation.json"
gis_log "Rollback completed: current=$(basename "$previous_target"), previous=$(basename "$current_target")."
