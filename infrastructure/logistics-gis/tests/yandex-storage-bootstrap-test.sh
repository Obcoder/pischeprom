#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'

test_root="$(mktemp -d)"
cleanup() {
    rm -r -- "$test_root"
}
trap cleanup EXIT

fake_bin="$test_root/bin"
mkdir -p -- "$fake_bin"

tee "$fake_bin/curl" >/dev/null <<'FAKE_CURL'
#!/usr/bin/env bash
set -Eeuo pipefail

output_file='/dev/null'
method='GET'
url=''
while (( $# > 0 )); do
    case "$1" in
        --output)
            output_file="$2"
            shift 2
            ;;
        --request)
            method="$2"
            shift 2
            ;;
        --header|--data-urlencode|--write-out)
            shift 2
            ;;
        --get|--silent|--show-error|--retry-all-errors)
            shift
            ;;
        --retry)
            shift 2
            ;;
        http*)
            url="$1"
            shift
            ;;
        *)
            shift
            ;;
    esac
done

printf '%s %s\n' "$method" "$url" >> "$FAKE_CURL_LOG"
if [[ "$method" == 'GET' && "$url" == 'https://storage.api.cloud.yandex.net/storage/v1/buckets' ]]; then
    printf '{"buckets":[]}' > "$output_file"
    printf '200'
    exit 0
fi

printf '{"message":"unexpected fake request"}' > "$output_file"
printf '500'
FAKE_CURL
chmod 0755 "$fake_bin/curl"

tee "$fake_bin/jq" >/dev/null <<'FAKE_JQ'
#!/usr/bin/env bash
set -Eeuo pipefail

# The plan fixture returns an empty bucket list, so both `any` checks are false.
if [[ "$*" == *'any(.name == $name)'* ]]; then
    exit 1
fi

printf 'Unexpected jq invocation in plan-only fixture: %s\n' "$*" >&2
exit 2
FAKE_JQ
chmod 0755 "$fake_bin/jq"

# bootstrap validates the complete apply toolchain even in plan mode.
for command_name in dd sha256sum; do
    if ! command -v "$command_name" >/dev/null; then
        tee "$fake_bin/$command_name" >/dev/null <<'FAKE_TOOL'
#!/usr/bin/env bash
exit 0
FAKE_TOOL
        chmod 0755 "$fake_bin/$command_name"
    fi
done

repository="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
bootstrap="$repository/infrastructure/logistics-gis/scripts/bootstrap-yandex-storage.sh"
curl_log="$test_root/curl.log"

output="$(
    PATH="$fake_bin:$PATH" \
    FAKE_CURL_LOG="$curl_log" \
    YC_IAM_TOKEN='masked-test-token' \
    YC_FOLDER_ID='b1g00000000000000000' \
    YC_MAP_BUCKET='pischeprom-map-test' \
    YC_GIS_PRIVATE_BUCKET='pischeprom-private-test' \
    LOGISTICS_APP_ORIGIN='https://app.example.test' \
    YC_STORAGE_ACTION='plan' \
        "$bootstrap"
)"

[[ "$output" == *'public map bucket pischeprom-map-test would be created'* ]]
[[ "$output" == *'private GIS bucket pischeprom-private-test would be created'* ]]
[[ "$output" == *'no cloud state was changed'* ]]
[[ "$(wc -l < "$curl_log" | tr -d '[:space:]')" == '1' ]]
[[ "$(< "$curl_log")" == 'GET https://storage.api.cloud.yandex.net/storage/v1/buckets' ]]

if PATH="$fake_bin:$PATH" \
    FAKE_CURL_LOG="$curl_log" \
    YC_IAM_TOKEN='masked-test-token' \
    YC_FOLDER_ID='b1g00000000000000000' \
    YC_MAP_BUCKET='pischeprom-map-test' \
    YC_GIS_PRIVATE_BUCKET='pischeprom-private-test' \
    LOGISTICS_APP_ORIGIN='https://app.example.test' \
    YC_STORAGE_ACTION='apply' \
        "$bootstrap" >/dev/null 2>&1
then
    printf 'Apply without the exact confirmation was accepted.\n' >&2
    exit 1
fi

printf 'yandex-storage-bootstrap-test: PASS\n'
