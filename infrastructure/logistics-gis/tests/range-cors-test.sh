#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'

if [[ "$(uname -s)" != "Linux" ]]; then
    printf 'range-cors-test: SKIP (native Linux test)\n'
    exit 0
fi

test_root="$(mktemp -d)"
cleanup() {
    rm -r -- "$test_root"
}
trap cleanup EXIT
fake_bin="${test_root}/bin"
gis_base="${test_root}/gis"
mkdir -p -- "$fake_bin" "${gis_base}/state"

tee "${fake_bin}/curl" >/dev/null <<'FAKE_CURL'
#!/usr/bin/env bash
set -Eeuo pipefail
output='/dev/null'
headers='/dev/null'
is_head=false
is_range=false
while (( $# > 0 )); do
    case "$1" in
        --output|--dump-header|--write-out|--header)
            option="$1"
            value="$2"
            shift 2
            case "$option" in
                --output) output="$value" ;;
                --dump-header) headers="$value" ;;
            esac
            ;;
        --range)
            is_range=true
            shift 2
            ;;
        --head)
            is_head=true
            shift
            ;;
        *)
            shift
            ;;
    esac
done
cors="${FAKE_CORS_ALLOW:-https://app.example.test}"
if $is_head; then
    printf 'HTTP/1.1 200 OK\r\nContent-Length: 32\r\nContent-Type: application/vnd.pmtiles\r\nAccess-Control-Allow-Origin: %s\r\n\r\n' "$cors" > "$headers"
    printf '200'
elif $is_range; then
    printf 'HTTP/1.1 206 Partial Content\r\nContent-Length: 16\r\nContent-Type: application/vnd.pmtiles\r\nAccept-Ranges: bytes\r\nContent-Range: bytes 0-15/32\r\nETag: "fixture"\r\nAccess-Control-Allow-Origin: %s\r\nAccess-Control-Expose-Headers: ETag, Content-Range,\t Accept-Ranges, Content-Length\r\n\r\n' "$cors" > "$headers"
    printf '0123456789abcdef' > "$output"
    printf '206'
else
    exit 2
fi
FAKE_CURL
chmod 0755 "${fake_bin}/curl"

repository="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
checker="${repository}/infrastructure/logistics-gis/scripts/check-pmtiles-range.sh"
PATH="${fake_bin}:$PATH" \
GIS_BASE_DIR="$gis_base" \
GIS_EXPECTED_CORS_ORIGIN='https://app.example.test' \
    "$checker" 'https://maps.example.test/logistics/releases/russia-20260801/russia.pmtiles'

STATE="${gis_base}/state/last-range-check.json" php -r '
    $v=json_decode((string)file_get_contents(getenv("STATE")),true,flags:JSON_THROW_ON_ERROR);
    if(($v["healthy"]??false)!==true||($v["status_code"]??0)!==206||($v["cors_allow_origin"]??null)!=="https://app.example.test")exit(1);
'

if PATH="${fake_bin}:$PATH" \
    FAKE_CORS_ALLOW='https://wrong.example.test' \
    GIS_BASE_DIR="$gis_base" \
    GIS_EXPECTED_CORS_ORIGIN='https://app.example.test' \
        "$checker" 'https://maps.example.test/logistics/releases/russia-20260801/russia.pmtiles' >/dev/null 2>&1
then
    printf 'Invalid CORS response was accepted.\n' >&2
    exit 1
fi

printf 'range-cors-test: PASS\n'
