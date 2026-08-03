#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'

repository="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
work_dir="$(mktemp -d)"
trap 'rm -rf -- "$work_dir"' EXIT
scripts="${work_dir}/scripts"
base="${work_dir}/base"
events="${work_dir}/events"
fake_bin="${work_dir}/bin"
mkdir -- "$scripts" "$fake_bin" "$base" "$base/sources" "$base/staging" "$base/releases" "$base/logs" "$base/state" "$base/locks"
printf '%s\n' '#!/usr/bin/env bash' 'test "$1" = "-n"' 'test "$2" = "8"' > "$fake_bin/flock"
chmod 0755 "$fake_bin/flock"
if [[ "$(uname -s)" == 'Darwin' ]]; then
    printf '%s\n' \
        '#!/usr/bin/env bash' \
        'set -Eeuo pipefail' \
        'test "$1" = "-c"' \
        'test "$2" = "%s"' \
        'test "$3" = "--"' \
        'exec /usr/bin/stat -f "%z" "$4"' \
        > "$fake_bin/stat"
    chmod 0755 "$fake_bin/stat"
fi
cp -- \
    "${repository}/infrastructure/logistics-gis/scripts/common.sh" \
    "${repository}/infrastructure/logistics-gis/scripts/run-builder-pipeline.sh" \
    "$scripts/"

for stage in preflight download-russia-pbf build-valhalla build-pmtiles finalize-release; do
    script="${scripts}/${stage}.sh"
    printf '%s\n' '#!/usr/bin/env bash' 'set -Eeuo pipefail' \
        'source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"' \
        'printf "%s\n" "$(basename "$0")" >> "$PIPELINE_TEST_EVENTS"' > "$script"
    case "$stage" in
        download-russia-pbf)
            printf '%s\n' \
                'printf "fixture" > "$GIS_SOURCE_DIR/russia-20260801.osm.pbf"' \
                'GIS_SOURCE_DIR="$GIS_SOURCE_DIR" PBF="$GIS_SOURCE_DIR/russia-20260801.osm.pbf" php -r '\''$size=filesize(getenv("PBF"));$v=["release"=>"russia-20260801","size_bytes"=>$size,"verified"=>true];file_put_contents(getenv("GIS_SOURCE_DIR")."/russia-20260801.manifest.json",json_encode($v));'\''' \
                >> "$script"
            ;;
        finalize-release)
            printf '%s\n' \
                'if [[ "${1:-}" == "--retry-smoke" ]]; then' \
                '    test "$2" = "russia-20260801"' \
                '    test -d "$GIS_RELEASES_DIR/$2"' \
                '    printf '\''{"release":"russia-20260801","status":"verified"}\n'\'' > "$GIS_RELEASES_DIR/$2/release-manifest.json"' \
                'else' \
                '    test "$1" = "russia-20260801"' \
                '    mkdir -- "$GIS_RELEASES_DIR/$1"' \
                '    printf '\''{"release":"russia-20260801","status":"verified"}\n'\'' > "$GIS_RELEASES_DIR/$1/release-manifest.json"' \
                'fi' \
                >> "$script"
            ;;
        build-valhalla|build-pmtiles)
            printf '%s\n' 'test "$1" = "$GIS_SOURCE_DIR/russia-20260801.osm.pbf"' >> "$script"
            ;;
    esac
    chmod 0755 "$script"
done

PATH="${fake_bin}:${PATH}" PIPELINE_TEST_EVENTS="$events" GIS_BASE_DIR="$base" \
    "$scripts/run-builder-pipeline.sh"

expected_events="${work_dir}/expected-events"
printf '%s\n' \
    preflight.sh \
    download-russia-pbf.sh \
    build-valhalla.sh \
    build-pmtiles.sh \
    finalize-release.sh \
    > "$expected_events"
cmp --silent "$expected_events" "$events"
STATE="$base/state/builder-pipeline.json" php -r '
    $v=json_decode((string)file_get_contents(getenv("STATE")),true,flags:JSON_THROW_ON_ERROR);
    exit(($v["status"]??null)==="completed"
        &&($v["stage"]??null)==="completed"
        &&($v["release"]??null)==="russia-20260801"
        &&($v["exit_code"]??null)===0?0:1);
'

printf 'builder pipeline: detached sequence and completion state passed\n'

printf '{"release":"russia-20260801","status":"failed"}\n' \
    > "$base/releases/russia-20260801/release-manifest.json"
: > "$events"
PATH="${fake_bin}:${PATH}" PIPELINE_TEST_EVENTS="$events" GIS_BASE_DIR="$base" \
    "$scripts/run-builder-pipeline.sh"

printf '%s\n' \
    preflight.sh \
    download-russia-pbf.sh \
    finalize-release.sh \
    > "$expected_events"
cmp --silent "$expected_events" "$events"
STATE="$base/state/builder-pipeline.json" php -r '
    $v=json_decode((string)file_get_contents(getenv("STATE")),true,flags:JSON_THROW_ON_ERROR);
    exit(($v["status"]??null)==="completed"
        &&($v["stage"]??null)==="completed"
        &&($v["release"]??null)==="russia-20260801"
        &&($v["exit_code"]??null)===0?0:1);
'

grep -Fx 'RestrictAddressFamilies=AF_UNIX AF_INET AF_INET6 AF_NETLINK' \
    "${repository}/infrastructure/logistics-gis/systemd/pischeprom-gis-build.service.example" \
    >/dev/null
printf 'builder pipeline: failed-release smoke retry and libzmq address-family policy passed\n'
