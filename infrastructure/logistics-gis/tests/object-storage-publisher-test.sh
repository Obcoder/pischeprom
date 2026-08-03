#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'

if [[ "$(uname -s)" != "Linux" ]]; then
    printf 'object-storage-publisher-test: SKIP (native Linux test)\n'
    exit 0
fi

test_root="$(mktemp -d)"
cleanup() {
    rm -r -- "$test_root"
}
trap cleanup EXIT

repository="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
publisher="${repository}/infrastructure/logistics-gis/scripts/publish-map-assets.sh"
private_publisher="${repository}/infrastructure/logistics-gis/scripts/publish-valhalla-artifacts.sh"
fake_bin="${test_root}/bin"
gis_base="${test_root}/gis"
object_root="${test_root}/objects"
metadata_root="${test_root}/metadata"
release='russia-20260801'
bucket='map-bucket'
release_dir="${gis_base}/releases/${release}"
assets="${release_dir}/map/assets"
mkdir -p -- "$fake_bin" "${gis_base}/state" "${gis_base}/locks" \
    "${assets}/fonts/Noto Sans Regular" "${assets}/licenses" "${assets}/sprites" "$object_root" "$metadata_root"

printf '0123456789abcdef0123456789abcdef' > "${release_dir}/map/russia.pmtiles"
printf 'glyph-fixture\n' > "${assets}/fonts/Noto Sans Regular/0-255.pbf"
printf 'license-fixture\n' > "${assets}/licenses/font.txt"
printf '{}\n' > "${assets}/sprites/basic.json"
printf 'png-fixture\n' > "${assets}/sprites/basic.png"
(
    cd "$assets"
    sha256sum -- \
        'fonts/Noto Sans Regular/0-255.pbf' \
        'licenses/font.txt' \
        'sprites/basic.json' \
        'sprites/basic.png' \
        > SHA256SUMS
)
pmtiles_sha="$(sha256sum -- "${release_dir}/map/russia.pmtiles" | awk '{print $1}')"
printf '%s\n' \
    "{\"release\":\"${release}\",\"status\":\"verified\",\"pmtiles\":{\"size_bytes\":32,\"sha256\":\"${pmtiles_sha}\"}}" \
    > "${release_dir}/release-manifest.json"

tee "${fake_bin}/aws" >/dev/null <<'FAKE_AWS'
#!/usr/bin/env bash
set -Eeuo pipefail
while [[ "${1:-}" == '--endpoint-url' || "${1:-}" == '--region' ]]; do
    shift 2
done
service="${1:-}"
operation="${2:-}"
shift 2

parse_s3_uri() {
    local uri="$1"
    s3_bucket="${uri#s3://}"
    s3_bucket="${s3_bucket%%/*}"
    s3_key="${uri#s3://${s3_bucket}/}"
}

if [[ "$service" == 's3api' && "$operation" == 'list-objects-v2' ]]; then
    bucket=''
    prefix=''
    while (( $# > 0 )); do
        case "$1" in
            --bucket) bucket="$2"; shift 2 ;;
            --prefix) prefix="$2"; shift 2 ;;
            --output) shift 2 ;;
            *) shift ;;
        esac
    done
    FAKE_BUCKET="$bucket" FAKE_PREFIX="$prefix" php -r '
        $root=rtrim((string)getenv("FAKE_S3_ROOT"),"/")."/".getenv("FAKE_BUCKET");
        $prefix=(string)getenv("FAKE_PREFIX");$keys=[];
        if(is_dir($root)){
            $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
            foreach($iterator as $file){
                if(!$file->isFile()||$file->isLink())continue;
                $key=str_replace(DIRECTORY_SEPARATOR,"/",substr($file->getPathname(),strlen($root)+1));
                if(str_starts_with($key,$prefix))$keys[]=$key;
            }
        }
        sort($keys,SORT_STRING);
        $yc=getenv("FAKE_YC_JSON")==="true";
        echo json_encode($yc
            ?["contents"=>array_map(static fn($key)=>["key"=>$key],$keys)]
            :["Contents"=>array_map(static fn($key)=>["Key"=>$key],$keys)],JSON_THROW_ON_ERROR);
    '
    exit 0
fi

if [[ "$service" == 's3api' && "$operation" == 'head-object' ]]; then
    bucket=''
    key=''
    while (( $# > 0 )); do
        case "$1" in
            --bucket) bucket="$2"; shift 2 ;;
            --key) key="$2"; shift 2 ;;
            --output) shift 2 ;;
            *) shift ;;
        esac
    done
    object="${FAKE_S3_ROOT}/${bucket}/${key}"
    metadata="${FAKE_S3_METADATA_ROOT}/${bucket}/${key}"
    [[ -f "$object" && -f "$metadata" ]]
    mapfile -t values < "$metadata"
    content_type="${values[0]}"
    cache_control="${values[1]}"
    sha="${values[2]}"
    release="${values[3]}"
    CONTENT_LENGTH="$(stat -c '%s' -- "$object")" CONTENT_TYPE="$content_type" \
    CACHE_CONTROL="$cache_control" OBJECT_SHA="$sha" OBJECT_RELEASE="$release" php -r '
        $yc=getenv("FAKE_YC_JSON")==="true";
        echo json_encode($yc?[
            "content_length"=>(int)getenv("CONTENT_LENGTH"),
            "content_type"=>getenv("CONTENT_TYPE"),
            "cache_control"=>getenv("CACHE_CONTROL"),
            "metadata"=>["sha256"=>getenv("OBJECT_SHA"),"release"=>getenv("OBJECT_RELEASE")],
        ]:[
            "ContentLength"=>(int)getenv("CONTENT_LENGTH"),
            "ContentType"=>getenv("CONTENT_TYPE"),
            "CacheControl"=>getenv("CACHE_CONTROL"),
            "Metadata"=>["sha256"=>getenv("OBJECT_SHA"),"release"=>getenv("OBJECT_RELEASE")],
        ],JSON_THROW_ON_ERROR);
    '
    exit 0
fi

if [[ "$service" == 's3' && "$operation" == 'cp' ]]; then
    source="$1"
    destination="$2"
    shift 2
    content_type=''
    cache_control=''
    object_metadata=''
    while (( $# > 0 )); do
        case "$1" in
            --content-type) content_type="$2"; shift 2 ;;
            --cache-control) cache_control="$2"; shift 2 ;;
            --metadata) object_metadata="$2"; shift 2 ;;
            *) shift ;;
        esac
    done
    if [[ "$source" == s3://* ]]; then
        parse_s3_uri "$source"
        cp -- "${FAKE_S3_ROOT}/${s3_bucket}/${s3_key}" "$destination"
        exit 0
    fi
    [[ "${FAKE_FAIL_UPLOAD:-false}" != 'true' ]] || exit 91
    parse_s3_uri "$destination"
    object="${FAKE_S3_ROOT}/${s3_bucket}/${s3_key}"
    metadata="${FAKE_S3_METADATA_ROOT}/${s3_bucket}/${s3_key}"
    mkdir -p -- "$(dirname "$object")" "$(dirname "$metadata")"
    cp -- "$source" "$object"
    sha="${object_metadata#sha256=}"
    sha="${sha%%,*}"
    release="${object_metadata##*release=}"
    printf '%s\n%s\n%s\n%s\n' "$content_type" "$cache_control" "$sha" "$release" > "$metadata"
    exit 0
fi

printf 'Unsupported fake AWS call: %s %s\n' "$service" "$operation" >&2
exit 64
FAKE_AWS

tee "${fake_bin}/yc" >/dev/null <<'FAKE_YC'
#!/usr/bin/env bash
set -Eeuo pipefail
[[ "${1:-}" == 'storage' ]] || exit 64
shift
fake_aws="$(dirname "$0")/aws"
if [[ "${1:-}" == 's3api' ]]; then
    operation="${2:-}"
    shift 2
    [[ "$operation" != 'list-objects' ]] || operation='list-objects-v2'
    translated=()
    while (( $# > 0 )); do
        if [[ "$1" == '--format' ]]; then
            translated+=(--output "$2")
            shift 2
        else
            translated+=("$1")
            shift
        fi
    done
    FAKE_YC_JSON=true exec "$fake_aws" s3api "$operation" "${translated[@]}"
fi
if [[ "${1:-}" == 's3' && "${2:-}" == 'cp' ]]; then
    shift 2
    exec "$fake_aws" s3 cp "$@"
fi
exit 64
FAKE_YC

tee "${fake_bin}/curl" >/dev/null <<'FAKE_CURL'
#!/usr/bin/env bash
set -Eeuo pipefail
output=''
headers=''
url=''
is_head=false
is_range=false
while (( $# > 0 )); do
    case "$1" in
        --output) output="$2"; shift 2 ;;
        --dump-header) headers="$2"; shift 2 ;;
        --write-out|--header|--retry|--max-time) shift 2 ;;
        --range) is_range=true; shift 2 ;;
        --head) is_head=true; shift ;;
        http://*|https://*) url="$1"; shift ;;
        *) shift ;;
    esac
done
[[ -n "$url" ]]
key="$(PUBLIC_URL="$url" php -r '$path=parse_url((string)getenv("PUBLIC_URL"),PHP_URL_PATH);echo ltrim(rawurldecode((string)$path),"/");')"
object="${FAKE_S3_ROOT}/${FAKE_S3_BUCKET}/${key}"
[[ -f "$object" ]]
size="$(stat -c '%s' -- "$object")"
origin="${FAKE_APPLICATION_ORIGIN}"
if $is_head; then
    if [[ "${FAKE_PRIVATE_OBJECT:-false}" == 'true' ]]; then
        printf '403'
        exit 0
    fi
    printf 'HTTP/1.1 200 OK\r\nContent-Length: %s\r\nContent-Type: application/vnd.pmtiles\r\nAccess-Control-Allow-Origin: %s\r\n\r\n' "$size" "$origin" > "$headers"
    printf '200'
    exit 0
fi
if $is_range; then
    printf 'HTTP/1.1 206 Partial Content\r\nContent-Length: 16\r\nContent-Type: application/vnd.pmtiles\r\nAccept-Ranges: bytes\r\nContent-Range: bytes 0-15/%s\r\nETag: "fixture"\r\nAccess-Control-Allow-Origin: %s\r\nAccess-Control-Expose-Headers: ETag, Content-Range, Accept-Ranges, Content-Length\r\n\r\n' "$size" "$origin" > "$headers"
    head -c 16 -- "$object" > "$output"
    printf '206'
    exit 0
fi
if [[ -n "$headers" ]]; then
    printf 'HTTP/1.1 200 OK\r\nContent-Length: %s\r\nAccess-Control-Allow-Origin: %s\r\n\r\n' "$size" "$origin" > "$headers"
fi
if [[ -n "$output" && "$output" != '/dev/null' ]]; then
    cp -- "$object" "$output"
else
    cat -- "$object"
fi
FAKE_CURL
chmod 0755 "${fake_bin}/aws" "${fake_bin}/curl" "${fake_bin}/yc"

run_publisher() {
    local storage_cli="${1:-aws}"
    PATH="${fake_bin}:$PATH" \
    FAKE_S3_ROOT="$object_root" \
    FAKE_S3_METADATA_ROOT="$metadata_root" \
    FAKE_S3_BUCKET="$bucket" \
    FAKE_APPLICATION_ORIGIN='https://app.example.test' \
    GIS_BASE_DIR="$gis_base" \
    GIS_OBJECT_STORAGE_BUCKET="$bucket" \
    GIS_OBJECT_STORAGE_CLI="$storage_cli" \
    GIS_OBJECT_STORAGE_PREFIX='logistics' \
    GIS_PUBLIC_ASSET_BASE_URL='https://maps.example.test/logistics' \
    GIS_MAP_APPLICATION_ORIGIN='https://app.example.test' \
    GIS_VERIFY_PUBLIC_PMTILES_SHA256=true \
    YC_IAM_TOKEN='short-lived-test-token' \
        "$publisher" "$release"
}

run_publisher yc
publication="${object_root}/${bucket}/logistics/releases/${release}/map-publication.json"
[[ -s "$publication" && -s "${gis_base}/state/last-map-publication.json" ]]
PUBLICATION="$publication" EXPECTED_SHA="$pmtiles_sha" php -r '
    $v=json_decode((string)file_get_contents(getenv("PUBLICATION")),true,flags:JSON_THROW_ON_ERROR);
    if(($v["status"]??null)!=="verified"||($v["pmtiles"]["sha256"]??null)!==getenv("EXPECTED_SHA"))exit(1);
'

# A completed retry verifies all immutable objects and performs no upload.
FAKE_FAIL_UPLOAD=true run_publisher yc
FAKE_FAIL_UPLOAD=true run_publisher aws

unexpected="${object_root}/${bucket}/logistics/releases/${release}/unexpected.txt"
printf 'unexpected\n' > "$unexpected"
if run_publisher yc >/dev/null 2>&1; then
    printf 'Unexpected immutable object was accepted.\n' >&2
    exit 1
fi

private_bucket='private-bucket'
mkdir -p -- "${release_dir}/valhalla/tiles"
printf 'graph-fixture\n' > "${release_dir}/valhalla/valhalla_tiles.tar"
printf '{"mjolnir":{}}\n' > "${release_dir}/valhalla/valhalla.json"
printf '{"status":"built"}\n' > "${release_dir}/valhalla/component-manifest.json"
printf 'admins-fixture\n' > "${release_dir}/valhalla/tiles/admins.sqlite"
printf 'timezones-fixture\n' > "${release_dir}/valhalla/tiles/timezones.sqlite"
printf '{"status":"passed"}\n' > "${release_dir}/smoke-tests.json"
graph_sha="$(sha256sum -- "${release_dir}/valhalla/valhalla_tiles.tar" | awk '{print $1}')"
printf '%s\n' \
    "{\"release\":\"${release}\",\"status\":\"verified\",\"valhalla\":{\"graph_size_bytes\":128,\"graph_sha256\":\"${graph_sha}\"},\"pmtiles\":{\"size_bytes\":32,\"sha256\":\"${pmtiles_sha}\"}}" \
    > "${release_dir}/release-manifest.json"

run_private_publisher() {
    local storage_cli="${1:-yc}"
    PATH="${fake_bin}:$PATH" \
    FAKE_S3_ROOT="$object_root" \
    FAKE_S3_METADATA_ROOT="$metadata_root" \
    FAKE_S3_BUCKET="$private_bucket" \
    FAKE_PRIVATE_OBJECT=true \
    GIS_BASE_DIR="$gis_base" \
    GIS_OBJECT_STORAGE_CLI="$storage_cli" \
    GIS_OBJECT_STORAGE_ENDPOINT='https://storage.example.test' \
    GIS_OBJECT_STORAGE_REGION='test-region' \
    GIS_PRIVATE_OBJECT_STORAGE_BUCKET="$private_bucket" \
    GIS_PRIVATE_OBJECT_STORAGE_PREFIX='logistics-gis' \
    YC_IAM_TOKEN='short-lived-test-token' \
        "$private_publisher" "$release"
}

run_private_publisher yc
private_marker="${object_root}/${private_bucket}/logistics-gis/releases/${release}/private-publication.json"
[[ -s "$private_marker" && -s "${gis_base}/state/last-private-publication.json" ]]
MARKER="$private_marker" EXPECTED_SHA="$graph_sha" php -r '
    $v=json_decode((string)file_get_contents(getenv("MARKER")),true,flags:JSON_THROW_ON_ERROR);
    exit(($v["status"]??null)==="verified"&&($v["graph_sha256"]??null)===getenv("EXPECTED_SHA")?0:1);
'
FAKE_FAIL_UPLOAD=true run_private_publisher yc
FAKE_FAIL_UPLOAD=true run_private_publisher aws

printf 'object-storage-publisher-test: PASS\n'
