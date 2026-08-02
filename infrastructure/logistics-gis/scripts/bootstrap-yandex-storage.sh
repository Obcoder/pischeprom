#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'

log() {
    printf '[yandex-storage] %s\n' "$*"
}

fail() {
    printf '[yandex-storage] ERROR: %s\n' "$*" >&2
    exit 1
}

require_env() {
    local name="$1"
    [[ -n "${!name:-}" ]] || fail "Required environment variable ${name} is missing."
}

for required_name in \
    YC_IAM_TOKEN \
    YC_FOLDER_ID \
    YC_MAP_BUCKET \
    YC_GIS_PRIVATE_BUCKET \
    LOGISTICS_APP_ORIGIN \
    YC_STORAGE_ACTION
do
    require_env "$required_name"
done

for required_command in awk curl dd jq sha256sum; do
    command -v "$required_command" >/dev/null \
        || fail "Required command ${required_command} is not installed."
done

[[ "$YC_FOLDER_ID" =~ ^[a-z0-9]{20}$ ]] || fail 'YC_FOLDER_ID has an unexpected format.'
[[ "$YC_STORAGE_ACTION" == 'plan' || "$YC_STORAGE_ACTION" == 'apply' ]] \
    || fail 'YC_STORAGE_ACTION must be plan or apply.'
[[ "$LOGISTICS_APP_ORIGIN" =~ ^https://[a-zA-Z0-9.-]+(:[0-9]+)?$ ]] \
    || fail 'LOGISTICS_APP_ORIGIN must be one HTTPS origin without credentials, path, query or fragment.'

validate_bucket_name() {
    local name="$1"
    [[ ${#name} -ge 3 && ${#name} -le 63 ]] \
        || fail "Bucket name ${name} must contain 3-63 characters."
    [[ "$name" =~ ^[a-z0-9]([a-z0-9-]*[a-z0-9])$ ]] \
        || fail "Bucket name ${name} must be dotless and contain only lowercase ASCII letters, digits and hyphens."
    [[ ! "$name" =~ -- ]] || fail "Bucket name ${name} must not contain adjacent hyphens."
}

validate_bucket_name "$YC_MAP_BUCKET"
validate_bucket_name "$YC_GIS_PRIVATE_BUCKET"
[[ "$YC_MAP_BUCKET" != "$YC_GIS_PRIVATE_BUCKET" ]] || fail 'Public and private bucket names must differ.'

if [[ "$YC_STORAGE_ACTION" == 'apply' ]]; then
    [[ "${YC_STORAGE_CONFIRMATION:-}" == 'CREATE_YANDEX_STORAGE' ]] \
        || fail 'Apply requires YC_STORAGE_CONFIRMATION=CREATE_YANDEX_STORAGE.'
fi

map_max_bytes="${YC_MAP_BUCKET_MAX_BYTES:-53687091200}"
private_max_bytes="${YC_PRIVATE_BUCKET_MAX_BYTES:-107374182400}"
[[ "$map_max_bytes" =~ ^[1-9][0-9]*$ ]] || fail 'YC_MAP_BUCKET_MAX_BYTES must be a positive integer.'
[[ "$private_max_bytes" =~ ^[1-9][0-9]*$ ]] || fail 'YC_PRIVATE_BUCKET_MAX_BYTES must be a positive integer.'

storage_api='https://storage.api.cloud.yandex.net/storage/v1'
s3_api='https://storage.yandexcloud.net'
fixture_key='logistics/preflight/range-fixture-v1.bin'

work_dir="$(mktemp -d)"
cleanup() {
    rm -r -- "$work_dir"
}
trap cleanup EXIT

api_request() {
    local label="$1"
    local method="$2"
    local url="$3"
    local output_file="$4"
    shift 4

    local http_code
    http_code="$(
        curl \
            --silent \
            --show-error \
            --retry 3 \
            --retry-all-errors \
            --request "$method" \
            --header "Authorization: Bearer ${YC_IAM_TOKEN}" \
            --output "$output_file" \
            --write-out '%{http_code}' \
            "$@" \
            "$url"
    )"

    if [[ ! "$http_code" =~ ^2[0-9]{2}$ ]]; then
        local api_message
        api_message="$(
            jq -r \
                '.error.message // .message // .error_description // .error // "No API error message"' \
                "$output_file" 2>/dev/null || printf '%s' 'Unreadable API response'
        )"
        api_message="${api_message//$'\n'/ }"
        fail "${label} failed with HTTP ${http_code}: ${api_message}"
    fi
}

operation_succeeded() {
    local label="$1"
    local operation_file="$2"
    if ! jq -e '.done == true and (.error == null)' "$operation_file" >/dev/null; then
        local operation_message
        operation_message="$(jq -r '.error.message // "Operation did not finish synchronously"' "$operation_file")"
        fail "${label} failed: ${operation_message}"
    fi
}

list_file="$work_dir/buckets.json"
api_request \
    'List Object Storage buckets' \
    GET \
    "${storage_api}/buckets" \
    "$list_file" \
    --get \
    --data-urlencode "folderId=${YC_FOLDER_ID}" \
    --data-urlencode 'pageSize=100'

bucket_exists_in_folder() {
    local name="$1"
    jq -e --arg name "$name" '(.buckets // []) | any(.name == $name)' "$list_file" >/dev/null
}

get_bucket() {
    local name="$1"
    local output_file="$2"
    api_request \
        "Get bucket ${name}" \
        GET \
        "${storage_api}/buckets/${name}" \
        "$output_file"
}

create_bucket() {
    local name="$1"
    local max_bytes="$2"
    local public_read="$3"
    local purpose="$4"
    local payload_file="$work_dir/create-${name}.json"
    local operation_file="$work_dir/create-${name}-operation.json"

    jq -n \
        --arg name "$name" \
        --arg folder_id "$YC_FOLDER_ID" \
        --arg max_size "$max_bytes" \
        --arg purpose "$purpose" \
        --argjson public_read "$public_read" \
        '{
            name: $name,
            folderId: $folder_id,
            defaultStorageClass: "STANDARD",
            maxSize: $max_size,
            anonymousAccessFlags: {
                read: $public_read,
                list: false,
                configRead: false
            },
            tags: [
                {key: "project", value: "pischeprom"},
                {key: "purpose", value: $purpose}
            ]
        }' > "$payload_file"

    api_request \
        "Create bucket ${name}" \
        POST \
        "${storage_api}/buckets" \
        "$operation_file" \
        --header 'Content-Type: application/json' \
        --data-binary "@${payload_file}"
    operation_succeeded "Create bucket ${name}" "$operation_file"
    log "Created bucket ${name}."
}

reconcile_public_bucket() {
    local payload_file="$work_dir/update-${YC_MAP_BUCKET}.json"
    local operation_file="$work_dir/update-${YC_MAP_BUCKET}-operation.json"

    jq -n \
        --arg max_size "$map_max_bytes" \
        --arg origin "$LOGISTICS_APP_ORIGIN" \
        '{
            updateMask: "anonymousAccessFlags,defaultStorageClass,maxSize,cors",
            anonymousAccessFlags: {
                read: true,
                list: false,
                configRead: false
            },
            defaultStorageClass: "STANDARD",
            maxSize: $max_size,
            cors: [
                {
                    id: "pischeprom-logistics-map-read",
                    allowedMethods: ["METHOD_GET", "METHOD_HEAD"],
                    allowedHeaders: ["Range", "If-None-Match"],
                    allowedOrigins: [$origin],
                    exposeHeaders: ["Accept-Ranges", "Content-Length", "Content-Range", "ETag"],
                    maxAgeSeconds: "86400"
                }
            ]
        }' > "$payload_file"

    api_request \
        "Reconcile public bucket ${YC_MAP_BUCKET}" \
        PATCH \
        "${storage_api}/buckets/${YC_MAP_BUCKET}" \
        "$operation_file" \
        --header 'Content-Type: application/json' \
        --data-binary "@${payload_file}"
    operation_succeeded "Reconcile public bucket ${YC_MAP_BUCKET}" "$operation_file"
}

reconcile_private_bucket() {
    local payload_file="$work_dir/update-${YC_GIS_PRIVATE_BUCKET}.json"
    local operation_file="$work_dir/update-${YC_GIS_PRIVATE_BUCKET}-operation.json"

    jq -n \
        --arg max_size "$private_max_bytes" \
        '{
            updateMask: "anonymousAccessFlags,defaultStorageClass,maxSize,cors",
            anonymousAccessFlags: {
                read: false,
                list: false,
                configRead: false
            },
            defaultStorageClass: "STANDARD",
            maxSize: $max_size,
            cors: []
        }' > "$payload_file"

    api_request \
        "Reconcile private bucket ${YC_GIS_PRIVATE_BUCKET}" \
        PATCH \
        "${storage_api}/buckets/${YC_GIS_PRIVATE_BUCKET}" \
        "$operation_file" \
        --header 'Content-Type: application/json' \
        --data-binary "@${payload_file}"
    operation_succeeded "Reconcile private bucket ${YC_GIS_PRIVATE_BUCKET}" "$operation_file"
}

validate_public_bucket() {
    local bucket_file="$1"
    jq -e \
        --arg name "$YC_MAP_BUCKET" \
        --arg folder_id "$YC_FOLDER_ID" \
        --arg max_size "$map_max_bytes" \
        --arg origin "$LOGISTICS_APP_ORIGIN" \
        '
            .name == $name and
            .folderId == $folder_id and
            .defaultStorageClass == "STANDARD" and
            .maxSize == $max_size and
            .anonymousAccessFlags.read == true and
            .anonymousAccessFlags.list == false and
            (.anonymousAccessFlags.configRead // false) == false and
            ((.cors // []) | length) == 1 and
            .cors[0].id == "pischeprom-logistics-map-read" and
            (.cors[0].allowedOrigins == [$origin]) and
            ((.cors[0].allowedMethods | sort) == (["METHOD_GET", "METHOD_HEAD"] | sort)) and
            ((.cors[0].allowedHeaders | sort) == (["Range", "If-None-Match"] | sort)) and
            ((.cors[0].exposeHeaders | sort) == (["Accept-Ranges", "Content-Length", "Content-Range", "ETag"] | sort)) and
            .cors[0].maxAgeSeconds == "86400"
        ' "$bucket_file" >/dev/null
}

validate_private_bucket() {
    local bucket_file="$1"
    jq -e \
        --arg name "$YC_GIS_PRIVATE_BUCKET" \
        --arg folder_id "$YC_FOLDER_ID" \
        --arg max_size "$private_max_bytes" \
        '
            .name == $name and
            .folderId == $folder_id and
            .defaultStorageClass == "STANDARD" and
            .maxSize == $max_size and
            .anonymousAccessFlags.read == false and
            .anonymousAccessFlags.list == false and
            (.anonymousAccessFlags.configRead // false) == false and
            ((.cors // []) | length) == 0
        ' "$bucket_file" >/dev/null
}

public_exists=false
private_exists=false
bucket_exists_in_folder "$YC_MAP_BUCKET" && public_exists=true
bucket_exists_in_folder "$YC_GIS_PRIVATE_BUCKET" && private_exists=true

if [[ "$YC_STORAGE_ACTION" == 'plan' ]]; then
    if $public_exists; then
        public_file="$work_dir/public-current.json"
        get_bucket "$YC_MAP_BUCKET" "$public_file"
        if validate_public_bucket "$public_file"; then
            log "PLAN: public map bucket ${YC_MAP_BUCKET} already matches the desired configuration."
        else
            log "PLAN: public map bucket ${YC_MAP_BUCKET} exists and would be reconciled."
        fi
    else
        log "PLAN: public map bucket ${YC_MAP_BUCKET} would be created and configured."
    fi

    if $private_exists; then
        private_file="$work_dir/private-current.json"
        get_bucket "$YC_GIS_PRIVATE_BUCKET" "$private_file"
        if validate_private_bucket "$private_file"; then
            log "PLAN: private GIS bucket ${YC_GIS_PRIVATE_BUCKET} already matches the desired configuration."
        else
            log "PLAN: private GIS bucket ${YC_GIS_PRIVATE_BUCKET} exists and would be reconciled."
        fi
    else
        log "PLAN: private GIS bucket ${YC_GIS_PRIVATE_BUCKET} would be created and configured."
    fi

    log 'PLAN: no cloud state was changed.'
    exit 0
fi

if ! $public_exists; then
    create_bucket "$YC_MAP_BUCKET" "$map_max_bytes" true 'logistics-map-public'
fi
if ! $private_exists; then
    create_bucket "$YC_GIS_PRIVATE_BUCKET" "$private_max_bytes" false 'logistics-gis-private'
fi

reconcile_public_bucket
reconcile_private_bucket

public_file="$work_dir/public-final.json"
private_file="$work_dir/private-final.json"
get_bucket "$YC_MAP_BUCKET" "$public_file"
get_bucket "$YC_GIS_PRIVATE_BUCKET" "$private_file"
validate_public_bucket "$public_file" || fail 'Public map bucket configuration does not match the desired state.'
validate_private_bucket "$private_file" || fail 'Private GIS bucket configuration does not match the desired state.'
log 'Both bucket configurations match the desired state.'

fixture_file="$work_dir/range-fixture-v1.bin"
dd if=/dev/zero of="$fixture_file" bs=65536 count=1 status=none
fixture_sha256="$(sha256sum "$fixture_file" | awk '{print $1}')"
authenticated_url="${s3_api}/${YC_MAP_BUCKET}/${fixture_key}"
public_url="https://${YC_MAP_BUCKET}.storage.yandexcloud.net/${fixture_key}"

existing_headers="$work_dir/existing-object-headers.txt"
existing_status="$(
    curl \
        --silent \
        --show-error \
        --head \
        --header "Authorization: Bearer ${YC_IAM_TOKEN}" \
        --dump-header "$existing_headers" \
        --output /dev/null \
        --write-out '%{http_code}' \
        "$authenticated_url"
)"

if [[ "$existing_status" == '404' ]]; then
    upload_response="$work_dir/upload-response.txt"
    upload_status="$(
        curl \
            --silent \
            --show-error \
            --request PUT \
            --header "Authorization: Bearer ${YC_IAM_TOKEN}" \
            --header 'Content-Type: application/octet-stream' \
            --header 'Cache-Control: public, max-age=31536000, immutable' \
            --header "x-amz-meta-sha256: ${fixture_sha256}" \
            --upload-file "$fixture_file" \
            --output "$upload_response" \
            --write-out '%{http_code}' \
            "$authenticated_url"
    )"
    [[ "$upload_status" == '200' ]] || fail "Fixture upload failed with HTTP ${upload_status}."
    log "Uploaded immutable Range fixture ${fixture_key}."
elif [[ "$existing_status" == '200' ]]; then
    log "Fixture ${fixture_key} already exists; it will be verified without overwrite."
else
    fail "Authenticated fixture HEAD failed with HTTP ${existing_status}."
fi

public_body="$work_dir/public-object.bin"
public_headers="$work_dir/public-object-headers.txt"
public_status='000'
for attempt in 1 2 3 4 5 6 7 8 9 10; do
    public_status="$(
        curl \
            --silent \
            --show-error \
            --header "Origin: ${LOGISTICS_APP_ORIGIN}" \
            --dump-header "$public_headers" \
            --output "$public_body" \
            --write-out '%{http_code}' \
            "$public_url"
    )"
    [[ "$public_status" == '200' ]] && break
    sleep 1
done
[[ "$public_status" == '200' ]] || fail "Public fixture GET failed with HTTP ${public_status}."
[[ "$(sha256sum "$public_body" | awk '{print $1}')" == "$fixture_sha256" ]] \
    || fail 'Existing public fixture differs from the expected immutable content.'

header_value() {
    local header_name="$1"
    local header_file="$2"
    awk -v wanted="$header_name" '
        {
            key=$0
            sub(/:.*/, "", key)
            if (tolower(key) == tolower(wanted)) {
                sub(/^[^:]+:[[:space:]]*/, "")
                sub(/\r$/, "")
                value=$0
            }
        }
        END { print value }
    ' "$header_file"
}

[[ "$(header_value 'Access-Control-Allow-Origin' "$public_headers")" == "$LOGISTICS_APP_ORIGIN" ]] \
    || fail 'Public GET did not return the exact configured CORS origin.'

range_body="$work_dir/range-body.bin"
range_headers="$work_dir/range-headers.txt"
range_status="$(
    curl \
        --silent \
        --show-error \
        --header "Origin: ${LOGISTICS_APP_ORIGIN}" \
        --header 'Range: bytes=0-15' \
        --dump-header "$range_headers" \
        --output "$range_body" \
        --write-out '%{http_code}' \
        "$public_url"
)"
[[ "$range_status" == '206' ]] || fail "Range request returned HTTP ${range_status}, expected 206."
[[ "$(wc -c < "$range_body" | tr -d '[:space:]')" == '16' ]] \
    || fail 'Range response body is not exactly 16 bytes.'
[[ "$(header_value 'Accept-Ranges' "$range_headers" | tr '[:upper:]' '[:lower:]')" == 'bytes' ]] \
    || fail 'Range response is missing Accept-Ranges: bytes.'
[[ "$(header_value 'Content-Range' "$range_headers" | tr '[:upper:]' '[:lower:]')" == 'bytes 0-15/65536' ]] \
    || fail 'Range response has an unexpected Content-Range.'
[[ "$(header_value 'Access-Control-Allow-Origin' "$range_headers")" == "$LOGISTICS_APP_ORIGIN" ]] \
    || fail 'Range response did not return the exact configured CORS origin.'

expose_headers="$(
    header_value 'Access-Control-Expose-Headers' "$range_headers" \
        | tr '[:upper:]' '[:lower:]' \
        | tr -d '[:space:]'
)"
for exposed_header in accept-ranges content-length content-range etag; do
    [[ ",${expose_headers}," == *",${exposed_header},"* ]] \
        || fail "CORS does not expose ${exposed_header}."
done

public_list_status="$(
    curl \
        --silent \
        --show-error \
        --output /dev/null \
        --write-out '%{http_code}' \
        "https://${YC_MAP_BUCKET}.storage.yandexcloud.net/?list-type=2&max-keys=1"
)"
[[ "$public_list_status" == '403' ]] || fail "Public bucket listing returned HTTP ${public_list_status}, expected 403."

private_list_status="$(
    curl \
        --silent \
        --show-error \
        --output /dev/null \
        --write-out '%{http_code}' \
        "https://${YC_GIS_PRIVATE_BUCKET}.storage.yandexcloud.net/?list-type=2&max-keys=1"
)"
[[ "$private_list_status" == '403' ]] || fail "Private bucket listing returned HTTP ${private_list_status}, expected 403."

log "Public HTTPS, exact CORS, immutable content and Range/206 passed: ${public_url}"
log 'Anonymous listing is denied for both buckets.'

if [[ -n "${GITHUB_STEP_SUMMARY:-}" ]]; then
    {
        printf '### Yandex Object Storage foundation\n\n'
        printf -- '- Public map origin: `%s`\n' "https://${YC_MAP_BUCKET}.storage.yandexcloud.net"
        printf -- '- Private GIS bucket: `%s`\n' "$YC_GIS_PRIVATE_BUCKET"
        printf -- '- Fixture: public GET/CORS/Range 206 passed\n'
        printf -- '- Anonymous listing: denied for both buckets\n'
        printf -- '- VM resources: not created\n'
    } >> "$GITHUB_STEP_SUMMARY"
fi
