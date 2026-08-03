#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'

if [[ "$(uname -s)" != "Linux" ]]; then
    printf 'application-state-bundle-test: SKIP (native Linux test)\n'
    exit 0
fi

test_root="$(mktemp -d)"
cleanup() {
    rm -r -- "$test_root"
}
trap cleanup EXIT

repository="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
scripts="${repository}/infrastructure/logistics-gis/scripts"
gis_base="${test_root}/gis"
state_base="${test_root}/application-state"
bundle="${test_root}/bundle"
map_state_base="${test_root}/map-application-state"
map_bundle="${test_root}/map-bundle"
release='russia-20260801'
pmtiles_sha='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
mkdir -p -- "${gis_base}/releases/${release}" "${gis_base}/state" "${gis_base}/locks" "$state_base" "$map_state_base"
ln -s -- "${gis_base}/releases/${release}" "${gis_base}/current"

printf '%s\n' \
    "{\"release\":\"${release}\",\"status\":\"verified\",\"coverage\":\"Russia\",\"osm_data_version\":\"20260801\",\"pmtiles\":{\"size_bytes\":32,\"sha256\":\"${pmtiles_sha}\"}}" \
    > "${gis_base}/releases/${release}/release-manifest.json"
printf '%s\n' \
    "{\"release\":\"${release}\",\"status\":\"active\",\"production_smoke\":\"passed\",\"activated_at\":\"2026-08-02T12:00:00Z\"}" \
    > "${gis_base}/state/last-activation.json"
printf '%s\n' \
    '{"healthy":true,"content_length":32,"cors_origin":"https://app.example.test","cors_allow_origin":"https://app.example.test","cors_expose_headers":"Accept-Ranges, Content-Length, Content-Range, ETag"}' \
    > "${gis_base}/state/last-range-check.json"
printf '%s\n' \
    "{\"release\":\"${release}\",\"status\":\"passed\",\"kind\":\"production\"}" \
    > "${gis_base}/state/last-production-smoke.json"
printf '%s\n' \
    "{\"status\":\"verified\",\"release\":\"${release}\",\"application_origin\":\"https://app.example.test\",\"pmtiles\":{\"url\":\"https://maps.example.test/logistics/releases/${release}/russia.pmtiles\",\"size_bytes\":32,\"sha256\":\"${pmtiles_sha}\",\"range_requests\":\"passed\",\"cors\":\"passed\"}}" \
    > "${gis_base}/state/last-map-publication.json"
printf '%s\n' '{"result":"PASS","mode":"full"}' > "${gis_base}/state/last-preflight.json"

GIS_BASE_DIR="$gis_base" "$scripts/export-application-state.sh" "$release" "$bundle"
"$scripts/install-application-state.sh" "$bundle" "$state_base"
[[ "$(realpath -- "${state_base}/current")" == "${state_base}/releases/${release}" ]]
[[ -f "${state_base}/current/last-map-publication.json" ]]

# A verified retry is idempotent and does not rewrite or rotate the state.
"$scripts/install-application-state.sh" "$bundle" "$state_base"
[[ ! -e "${state_base}/previous" ]]

# A persistent-map activation exports schema 2 without requiring a Valhalla
# current symlink and installs independently on the application VPS.
rm -- "${gis_base}/current"
printf '%s\n' \
    "{\"release\":\"${release}\",\"status\":\"active\",\"map_delivery\":\"passed\",\"routing_activation\":\"independent\",\"activated_at\":\"2026-08-02T12:30:00Z\"}" \
    > "${gis_base}/state/last-activation.json"
printf '%s\n' \
    "{\"release\":\"${release}\",\"status\":\"passed\",\"kind\":\"map-delivery\"}" \
    > "${gis_base}/state/last-production-smoke.json"

GIS_BASE_DIR="$gis_base" "$scripts/export-application-state.sh" "$release" "$map_bundle"
BUNDLE="${map_bundle}/bundle.json" php -r '
    $v=json_decode((string)file_get_contents(getenv("BUNDLE")),true,flags:JSON_THROW_ON_ERROR);
    exit(($v["schema_version"]??null)===2&&($v["activation_scope"]??null)==="map_only"?0:1);
'
"$scripts/install-application-state.sh" "$map_bundle" "$map_state_base"
map_state_target="$(realpath -- "${map_state_base}/current")"
[[ "$map_state_target" == "${map_state_base}/releases/${release}-map-"* ]]
[[ -f "${map_state_target}/last-map-publication.json" ]]

# Schema/scope substitution is rejected even when its checksum is recomputed.
invalid_schema="${test_root}/invalid-schema"
cp -a -- "$map_bundle" "$invalid_schema"
BUNDLE="${invalid_schema}/bundle.json" php -r '
    $v=json_decode((string)file_get_contents(getenv("BUNDLE")),true,flags:JSON_THROW_ON_ERROR);
    $v["schema_version"]=1;
    file_put_contents(getenv("BUNDLE"),json_encode($v,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
'
(
    cd "$invalid_schema"
    sha256sum -- bundle.json release-manifest.json last-activation.json last-range-check.json \
        last-production-smoke.json last-map-publication.json > SHA256SUMS
    sha256sum -- last-preflight.json >> SHA256SUMS
)
if "$scripts/install-application-state.sh" "$invalid_schema" "$map_state_base" >/dev/null 2>&1; then
    printf 'Invalid schema/activation scope combination was accepted.\n' >&2
    exit 1
fi

tampered="${test_root}/tampered"
cp -a -- "$map_bundle" "$tampered"
printf ' ' >> "${tampered}/bundle.json"
if "$scripts/install-application-state.sh" "$tampered" "$state_base" >/dev/null 2>&1; then
    printf 'Tampered state bundle was accepted.\n' >&2
    exit 1
fi

unsafe="${test_root}/unsafe"
cp -a -- "$map_bundle" "$unsafe"
printf '%064d  ../outside\n' 0 > "${unsafe}/SHA256SUMS"
if "$scripts/install-application-state.sh" "$unsafe" "$state_base" >/dev/null 2>&1; then
    printf 'Unsafe checksum path was accepted.\n' >&2
    exit 1
fi

printf 'application-state-bundle-test: PASS\n'
