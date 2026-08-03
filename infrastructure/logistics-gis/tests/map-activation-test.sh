#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'

if [[ "$(uname -s)" != 'Linux' ]]; then
    printf 'map-activation-test: SKIP (native Linux test)\n'
    exit 0
fi

repository="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
test_root="$(mktemp -d)"
trap 'rm -r -- "$test_root"' EXIT
scripts="${test_root}/scripts"
base="${test_root}/gis"
release='russia-20260801'
origin='https://app.example.test'
public_base='https://maps.example.test/logistics'
mkdir -p -- \
    "$scripts" \
    "${base}/sources" \
    "${base}/releases/${release}/map/assets" \
    "${base}/state" \
    "${base}/locks"

cp -- \
    "${repository}/infrastructure/logistics-gis/scripts/common.sh" \
    "${repository}/infrastructure/logistics-gis/scripts/activate-map-release.sh" \
    "$scripts/"

printf '%s\n' '#!/usr/bin/env bash' 'set -Eeuo pipefail' 'exit 0' > "${scripts}/preflight.sh"
printf '%s\n' '<?php exit(0);' > "${scripts}/validate-map-assets.php"
cat > "${scripts}/check-pmtiles-range.sh" <<'SCRIPT'
#!/usr/bin/env bash
set -Eeuo pipefail
test "$1" = "${GIS_PUBLIC_ASSET_BASE_URL}/releases/russia-20260801/russia.pmtiles"
ORIGIN="$GIS_EXPECTED_CORS_ORIGIN" PMTILES="${GIS_RELEASES_DIR}/russia-20260801/map/russia.pmtiles" OUTPUT="${GIS_STATE_DIR}/last-range-check.json" php -r '
    $value=[
        "healthy"=>true,
        "head_status_code"=>200,
        "content_length"=>filesize(getenv("PMTILES")),
        "status_code"=>206,
        "accept_ranges"=>"bytes",
        "content_range"=>"bytes 0-15/".filesize(getenv("PMTILES")),
        "cors_origin"=>getenv("ORIGIN"),
        "cors_allow_origin"=>getenv("ORIGIN"),
    ];
    file_put_contents(getenv("OUTPUT"),json_encode($value,JSON_THROW_ON_ERROR));
'
SCRIPT
chmod 0755 "${scripts}/preflight.sh" "${scripts}/check-pmtiles-range.sh"

fake_bin="${test_root}/bin"
mkdir -- "$fake_bin"
cat > "${fake_bin}/pmtiles" <<'SCRIPT'
#!/usr/bin/env bash
set -Eeuo pipefail
test "$1" = verify
test -s "$2"
SCRIPT
chmod 0755 "${fake_bin}/pmtiles"

pbf="${base}/sources/${release}.osm.pbf"
pmtiles="${base}/releases/${release}/map/russia.pmtiles"
printf 'verified pbf fixture\n' > "$pbf"
printf 'verified pmtiles fixture\n' > "$pmtiles"
pbf_size="$(stat -c '%s' -- "$pbf")"
pmtiles_size="$(stat -c '%s' -- "$pmtiles")"
pmtiles_sha="$(sha256sum -- "$pmtiles" | awk '{print $1}')"
printf '%s\n' \
    "{\"release\":\"${release}\",\"size_bytes\":${pbf_size},\"verified\":true}" \
    > "${base}/sources/${release}.manifest.json"
printf '%s\n' \
    "{\"release\":\"${release}\",\"status\":\"verified\",\"smoke_tests\":{\"status\":\"passed\"},\"pmtiles\":{\"size_bytes\":${pmtiles_size},\"sha256\":\"${pmtiles_sha}\"}}" \
    > "${base}/releases/${release}/release-manifest.json"
printf '%s\n' \
    "{\"status\":\"verified\",\"release\":\"${release}\",\"application_origin\":\"${origin}\",\"pmtiles\":{\"url\":\"${public_base}/releases/${release}/russia.pmtiles\",\"size_bytes\":${pmtiles_size},\"sha256\":\"${pmtiles_sha}\",\"range_requests\":\"passed\",\"cors\":\"passed\"}}" \
    > "${base}/state/last-map-publication.json"

PATH="${fake_bin}:${PATH}" \
GIS_BASE_DIR="$base" \
GIS_PUBLIC_ASSET_BASE_URL="$public_base" \
GIS_MAP_APPLICATION_ORIGIN="$origin" \
    "$scripts/activate-map-release.sh" "$release"

ACTIVATION="${base}/state/last-activation.json" SMOKE="${base}/state/last-production-smoke.json" php -r '
    $activation=json_decode((string)file_get_contents(getenv("ACTIVATION")),true,flags:JSON_THROW_ON_ERROR);
    $smoke=json_decode((string)file_get_contents(getenv("SMOKE")),true,flags:JSON_THROW_ON_ERROR);
    exit(($activation["status"]??null)==="active"
        &&($activation["release"]??null)==="russia-20260801"
        &&($activation["map_delivery"]??null)==="passed"
        &&($activation["routing_activation"]??null)==="independent"
        &&($smoke["status"]??null)==="passed"
        &&($smoke["kind"]??null)==="map-delivery"?0:1);
'
[[ ! -e "${base}/current" && ! -e "${base}/previous" ]]

# A retry preserves the original previous release instead of making the active
# release its own rollback marker.
ACTIVATION="${base}/state/last-activation.json" php -r '
    $v=json_decode((string)file_get_contents(getenv("ACTIVATION")),true,flags:JSON_THROW_ON_ERROR);
    $v["previous_release"]="russia-20260731";
    file_put_contents(getenv("ACTIVATION"),json_encode($v,JSON_THROW_ON_ERROR));
'
PATH="${fake_bin}:${PATH}" \
GIS_BASE_DIR="$base" \
GIS_PUBLIC_ASSET_BASE_URL="$public_base" \
GIS_MAP_APPLICATION_ORIGIN="$origin" \
    "$scripts/activate-map-release.sh" "$release"
ACTIVATION="${base}/state/last-activation.json" php -r '
    $v=json_decode((string)file_get_contents(getenv("ACTIVATION")),true,flags:JSON_THROW_ON_ERROR);
    exit(($v["previous_release"]??null)==="russia-20260731"?0:1);
'

printf 'map-activation-test: PASS\n'
