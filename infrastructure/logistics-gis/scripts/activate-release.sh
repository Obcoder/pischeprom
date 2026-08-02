#!/usr/bin/env bash

set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

[[ $# -eq 1 ]] || gis_fail 'Usage: activate-release.sh russia-YYYYMMDD'
release="$1"
gis_validate_release "$release"
"${GIS_SCRIPT_DIR}/preflight.sh" --mode activate

for directory in "$GIS_RELEASES_DIR" "$GIS_LOCK_DIR" "$GIS_STATE_DIR"; do
    [[ -d "$directory" && -w "$directory" ]] || gis_fail "Required writable directory is missing: ${directory}"
    gis_assert_inside_base "$directory" >/dev/null
done
exec 9>"${GIS_LOCK_DIR}/gis-activate.lock"
flock -n 9 || gis_fail 'Another GIS activation/rollback is already running.'
target="${GIS_RELEASES_DIR}/${release}"
[[ -d "$target" && ! -L "$target" ]] || gis_fail 'Release target must be a real immutable directory.'
target="$(gis_assert_inside_base "$target")"
first_symlink="$(find "$target" -type l -print -quit)"
[[ -z "$first_symlink" ]] || gis_fail "Verified release contains a forbidden symlink: ${first_symlink}"
manifest="${target}/release-manifest.json"
[[ -s "$manifest" && -s "${target}/valhalla/valhalla.json" \
    && -s "${target}/valhalla/valhalla_tiles.tar" && -s "${target}/map/russia.pmtiles" ]] \
    || gis_fail 'Verified paired release is incomplete.'
release_status="$(MANIFEST="$manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true,flags:JSON_THROW_ON_ERROR);echo (string)($v["status"]??"");')"
[[ "$release_status" == "verified" ]] || gis_fail 'Only a verified inactive release can be activated.'
expected_listen="$(gis_validated_valhalla_listen)"
release_listen="$(CONFIG="${target}/valhalla/valhalla.json" php -r '$v=json_decode((string)file_get_contents(getenv("CONFIG")),true,flags:JSON_THROW_ON_ERROR);echo (string)($v["httpd"]["service"]["listen"]??"");')"
[[ "$release_listen" == "$expected_listen" ]] \
    || gis_fail 'Release Valhalla listen address does not match the audited runtime configuration.'
expected_sha="$(MANIFEST="$manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true);echo (string)($v["pmtiles"]["sha256"]??"");')"
[[ "$expected_sha" =~ ^[0-9a-f]{64}$ && "$(gis_sha256 "${target}/map/russia.pmtiles")" == "$expected_sha" ]] \
    || gis_fail 'Release PMTiles checksum mismatch.'
expected_graph_sha="$(MANIFEST="$manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true);echo (string)($v["valhalla"]["graph_sha256"]??"");')"
[[ "$expected_graph_sha" =~ ^[0-9a-f]{64}$ && "$(gis_sha256 "${target}/valhalla/valhalla_tiles.tar")" == "$expected_graph_sha" ]] \
    || gis_fail 'Release Valhalla graph checksum mismatch.'
expected_assets_sha="$(MANIFEST="$manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true);echo (string)($v["pmtiles"]["assets_manifest_sha256"]??"");')"
[[ "$expected_assets_sha" =~ ^[0-9a-f]{64}$ \
    && "$(gis_sha256 "${target}/map/assets/SHA256SUMS")" == "$expected_assets_sha" ]] \
    || gis_fail 'Release map assets manifest checksum mismatch.'
php "${GIS_SCRIPT_DIR}/validate-map-assets.php" "${target}/map/assets"
gis_low_priority pmtiles verify "${target}/map/russia.pmtiles"
if [[ "${GIS_REQUIRE_LOCAL_MAP_NGINX:-true}" == "true" ]]; then
    nginx -t
fi

gis_validate_valhalla_restart
public_pmtiles_url="${GIS_PUBLIC_PMTILES_URL:-}"
[[ "$public_pmtiles_url" =~ ^https:// ]] || gis_fail 'GIS_PUBLIC_PMTILES_URL must be the public HTTPS object/CDN release URL.'
check_public_pmtiles() {
    GIS_EXPECTED_CORS_ORIGIN="${GIS_MAP_APPLICATION_ORIGIN:-}" \
        "${GIS_SCRIPT_DIR}/check-pmtiles-range.sh" "$public_pmtiles_url"
}
if [[ "${GIS_REQUIRE_OBJECT_STORAGE_PUBLICATION:-false}" == "true" ]]; then
    publication_state="${GIS_STATE_DIR}/last-map-publication.json"
    [[ -s "$publication_state" && ! -L "$publication_state" ]] \
        || gis_fail 'Verified object-storage publication state is required before activation.'
    publication_matches="$(PUBLICATION="$publication_state" EXPECTED_RELEASE="$release" EXPECTED_URL="$public_pmtiles_url" EXPECTED_ORIGIN="${GIS_MAP_APPLICATION_ORIGIN:-}" EXPECTED_SHA="$expected_sha" EXPECTED_SIZE="$(gis_file_size "${target}/map/russia.pmtiles")" php -r '
        $v=json_decode((string)file_get_contents(getenv("PUBLICATION")),true,flags:JSON_THROW_ON_ERROR);
        $pmtiles=$v["pmtiles"]??[];
        echo (($v["status"]??null)==="verified"
            &&($v["release"]??null)===getenv("EXPECTED_RELEASE")
            &&($v["application_origin"]??null)===getenv("EXPECTED_ORIGIN")
            &&($pmtiles["url"]??null)===getenv("EXPECTED_URL")
            &&($pmtiles["sha256"]??null)===getenv("EXPECTED_SHA")
            &&(int)($pmtiles["size_bytes"]??0)===(int)getenv("EXPECTED_SIZE")
            &&($pmtiles["range_requests"]??null)==="passed"
            &&($pmtiles["cors"]??null)==="passed")?"yes":"no";
    ')"
    [[ "$publication_matches" == "yes" ]] \
        || gis_fail 'Object-storage publication state does not match the verified paired release.'
fi
laravel_dir="${GIS_LARAVEL_APP_DIR:-}"
stale_helper="${GIS_LARAVEL_STALE_MARK_HELPER:-}"
if [[ "$laravel_dir" == /* && -f "$laravel_dir/artisan" ]]; then
    stale_mode='local'
elif [[ "$stale_helper" == /* && -x "$stale_helper" && ! -L "$stale_helper" ]]; then
    stale_mode='helper'
else
    gis_fail 'Set GIS_LARAVEL_APP_DIR or an audited absolute GIS_LARAVEL_STALE_MARK_HELPER.'
fi
mark_old_routing_stale() {
    if [[ "$stale_mode" == "local" ]]; then
        (
            cd "$laravel_dir"
            php artisan logistics:routing-mark-stale --old-osm-version="$old_osm_version"
        )
    else
        "$stale_helper" "$old_osm_version"
    fi
}

current_link="${GIS_BASE_DIR}/current"
previous_link="${GIS_BASE_DIR}/previous"
activation_state="${GIS_STATE_DIR}/last-activation.json"
[[ ! -L "$activation_state" ]] || gis_fail 'Activation state path must not be a symlink.'
old_target=""
old_osm_version="${GIS_CURRENT_OSM_DATA_VERSION:-}"
original_previous_target=""
activation_state_part=""
prepare_activation_state() {
    local previous_release="$1" output="$2"
    ACTIVATION_RELEASE="$release" ACTIVATION_PREVIOUS="$previous_release" php -r '
        $value=["status"=>"active","release"=>getenv("ACTIVATION_RELEASE"),"previous_release"=>getenv("ACTIVATION_PREVIOUS"),"activated_at"=>gmdate("c"),"range_requests"=>"passed","production_smoke"=>"passed"];
        $json=json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
        if(file_put_contents($argv[1],$json,LOCK_EX)===false)exit(1);
    ' "$output"
}
[[ -L "$current_link" ]] \
    || gis_fail 'An audited current release symlink is required so activation always has a rollback target.'
old_target="$(realpath -- "$current_link")"
gis_assert_inside_base "$old_target" >/dev/null
[[ -d "$old_target" ]] || gis_fail 'Current release target is unavailable.'
old_manifest="$old_target/release-manifest.json"
[[ -s "$old_manifest" && ! -L "$old_manifest" ]] \
    || gis_fail 'Current rollback target needs a regular audited release-manifest.json.'
old_release_name="$(MANIFEST="$old_manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true,flags:JSON_THROW_ON_ERROR);echo is_string($v["release"]??null)?$v["release"]:"";')"
[[ "$old_release_name" == "$(basename "$old_target")" ]] \
    || gis_fail 'Current rollback manifest release does not match its directory.'
old_release_status="$(MANIFEST="$old_manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true,flags:JSON_THROW_ON_ERROR);echo is_string($v["status"]??null)?$v["status"]:"";')"
[[ "$old_release_status" == "verified" || "$old_release_status" == "legacy" ]] \
    || gis_fail 'Current rollback manifest must describe a verified or explicitly audited legacy release.'
if [[ "$old_target" == "$target" ]]; then
    "${GIS_SCRIPT_DIR}/smoke-test-release.sh" "$target" "${VALHALLA_STATUS_URL%/status}"
    check_public_pmtiles
    previous_name="none"
    if [[ -L "$previous_link" ]]; then
        previous_target="$(realpath -- "$previous_link")"
        gis_assert_inside_base "$previous_target" >/dev/null
        previous_name="$(basename "$previous_target")"
    fi
    activation_state_part="${activation_state}.next.$$"
    if ! prepare_activation_state "$previous_name" "$activation_state_part"; then
        [[ ! -f "$activation_state_part" ]] || rm -- "$activation_state_part"
        gis_fail 'Unable to prepare activation state.'
    fi
    mv -- "$activation_state_part" "$activation_state"
    gis_log "Release ${release} is already current and still passes production smoke checks."
    exit 0
fi
manifest_osm_version="$(MANIFEST="$old_manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true,flags:JSON_THROW_ON_ERROR);echo (string)($v["osm_data_version"]??"");')"
[[ -z "$manifest_osm_version" ]] || old_osm_version="$manifest_osm_version"
[[ "$old_osm_version" =~ ^[A-Za-z0-9._-]+$ ]] \
    || gis_fail 'Current release OSM version is unknown; set GIS_CURRENT_OSM_DATA_VERSION before activation.'
if [[ -L "$previous_link" ]]; then
    original_previous_target="$(realpath -- "$previous_link")"
    gis_assert_inside_base "$original_previous_target" >/dev/null
fi
gis_atomic_symlink "$old_target" "$previous_link"

rollback_on_error() {
    local status="$?"
    if (( status == 0 )); then return; fi
    trap - ERR
    printf '[logistics-gis] Activation failed; restoring previous current release.\n' >&2
    if [[ -n "$old_target" && -d "$old_target" ]]; then
        gis_atomic_symlink "$old_target" "$current_link"
        if [[ -n "$original_previous_target" && -d "$original_previous_target" ]]; then
            gis_atomic_symlink "$original_previous_target" "$previous_link"
        fi
        gis_restart_valhalla || true
        curl --fail --silent --max-time 10 "$VALHALLA_STATUS_URL" >/dev/null || true
    fi
    [[ -z "$activation_state_part" || ! -f "$activation_state_part" ]] || rm -- "$activation_state_part"
    exit "$status"
}
trap rollback_on_error ERR

gis_atomic_symlink "$target" "$current_link"
gis_restart_valhalla
"${GIS_SCRIPT_DIR}/smoke-test-release.sh" "$target" "${VALHALLA_STATUS_URL%/status}"
check_public_pmtiles

activation_state_part="${activation_state}.next.$$"
prepare_activation_state "$(basename "$old_target")" "$activation_state_part"
mark_old_routing_stale

mv -- "$activation_state_part" "$activation_state"
activation_state_part=""
trap - ERR
gis_log "Release ${release} is active; previous release $(basename "$old_target") was preserved."
gis_log 'Automatic full-matrix recalculation was not started; only prior automatic values were marked stale.'
