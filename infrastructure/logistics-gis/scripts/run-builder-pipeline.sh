#!/usr/bin/env bash

set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

[[ $# -eq 0 ]] || gis_fail 'The supervised builder pipeline accepts no arguments.'
for command_name in find flock php sort; do
    gis_require_command "$command_name"
done
for directory in "$GIS_SOURCE_DIR" "$GIS_STAGING_DIR" "$GIS_RELEASES_DIR" "$GIS_LOG_DIR" "$GIS_STATE_DIR" "$GIS_LOCK_DIR"; do
    [[ -d "$directory" && -w "$directory" ]] \
        || gis_fail "Required writable builder directory is missing: ${directory}"
    gis_assert_inside_base "$directory" >/dev/null
done

exec 8>"${GIS_LOCK_DIR}/builder-pipeline.lock"
flock -n 8 || gis_fail 'The supervised full-Russia builder pipeline is already running.'

state_file="${GIS_STATE_DIR}/builder-pipeline.json"
[[ ! -e "$state_file" || ( -f "$state_file" && ! -L "$state_file" ) ]] \
    || gis_fail 'Builder pipeline state is not a managed regular file.'
pipeline_started="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
pipeline_release=""
pipeline_stage="starting"
pipeline_terminal=false

write_state() {
    local status="$1"
    local stage="$2"
    local release="${3:-}"
    local exit_code="${4:-0}"
    local state_part="${state_file}.next.$$"

    [[ "$status" =~ ^(running|completed|failed|interrupted)$ \
        && "$stage" =~ ^[a-z0-9-]+$ \
        && ( -z "$release" || "$release" =~ ^russia-[0-9]{8}$ ) \
        && "$exit_code" =~ ^[0-9]+$ ]] \
        || gis_fail 'Refusing to write malformed builder pipeline state.'
    [[ ! -e "$state_part" ]] || gis_fail 'Temporary builder pipeline state already exists.'

    PIPELINE_STATUS="$status" PIPELINE_STAGE="$stage" PIPELINE_RELEASE="$release" \
    PIPELINE_STARTED="$pipeline_started" PIPELINE_EXIT_CODE="$exit_code" \
    php -r '
        $value=[
            "status"=>getenv("PIPELINE_STATUS"),
            "stage"=>getenv("PIPELINE_STAGE"),
            "release"=>getenv("PIPELINE_RELEASE")!==""?getenv("PIPELINE_RELEASE"):null,
            "started_at"=>getenv("PIPELINE_STARTED"),
            "updated_at"=>gmdate("c"),
            "exit_code"=>(int)getenv("PIPELINE_EXIT_CODE"),
        ];
        if(file_put_contents($argv[1],json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL,LOCK_EX)===false)exit(1);
    ' "$state_part"
    mv -f -- "$state_part" "$state_file"
}

pipeline_on_exit() {
    local status="$?"
    trap - EXIT ERR INT TERM
    if (( status != 0 )) && ! $pipeline_terminal; then
        write_state failed "$pipeline_stage" "$pipeline_release" "$status" || true
        printf '[logistics-gis] ERROR: supervised builder pipeline failed during %s (exit %s).\n' \
            "$pipeline_stage" "$status" >&2
    fi
    exit "$status"
}

pipeline_interrupted() {
    local status=130
    trap - EXIT ERR INT TERM
    write_state interrupted "$pipeline_stage" "$pipeline_release" "$status" || true
    pipeline_terminal=true
    printf '[logistics-gis] ERROR: supervised builder pipeline was interrupted during %s.\n' \
        "$pipeline_stage" >&2
    exit "$status"
}

trap pipeline_on_exit EXIT
trap pipeline_interrupted INT TERM
write_state running "$pipeline_stage"

pipeline_stage="preflight"
write_state running "$pipeline_stage"
"${GIS_SCRIPT_DIR}/preflight.sh" --mode full

pipeline_stage="download"
write_state running "$pipeline_stage"
"${GIS_SCRIPT_DIR}/download-russia-pbf.sh"

latest_manifest=""
while IFS= read -r candidate; do
    latest_manifest="$candidate"
done < <(find "$GIS_SOURCE_DIR" -maxdepth 1 -type f -name 'russia-[0-9]*.manifest.json' -print | LC_ALL=C sort)
[[ -n "$latest_manifest" && -s "$latest_manifest" && ! -L "$latest_manifest" ]] \
    || gis_fail 'The verified downloader did not leave a source manifest.'
pipeline_release="$(MANIFEST="$latest_manifest" php -r '
    $v=json_decode((string)file_get_contents(getenv("MANIFEST")),true,flags:JSON_THROW_ON_ERROR);
    if(($v["verified"]??false)!==true)exit(1);
    echo is_string($v["release"]??null)?$v["release"]:"";
')"
gis_validate_release "$pipeline_release"
pbf="${GIS_SOURCE_DIR}/${pipeline_release}.osm.pbf"
gis_verified_pbf_manifest "$pbf" >/dev/null

target="${GIS_RELEASES_DIR}/${pipeline_release}"
if [[ -e "$target" ]]; then
    [[ -d "$target" && ! -L "$target" && -s "${target}/release-manifest.json" ]] \
        || gis_fail 'Existing immutable release target is incomplete or unsafe.'
    target_status="$(MANIFEST="${target}/release-manifest.json" php -r '
        $v=json_decode((string)file_get_contents(getenv("MANIFEST")),true,flags:JSON_THROW_ON_ERROR);
        echo is_string($v["status"]??null)?$v["status"]:"";
    ')"
    if [[ "$target_status" == 'verified' ]]; then
        pipeline_stage="completed"
        write_state completed "$pipeline_stage" "$pipeline_release"
        pipeline_terminal=true
        gis_log "Verified paired release already exists: ${pipeline_release}; no rebuild was needed."
        exit 0
    fi
    [[ "$target_status" == 'failed' ]] \
        || gis_fail 'Existing immutable release target is neither verified nor eligible for a smoke retry.'
    pipeline_stage="finalize"
    write_state running "$pipeline_stage" "$pipeline_release"
    "${GIS_SCRIPT_DIR}/finalize-release.sh" --retry-smoke "$pipeline_release"
    pipeline_stage="completed"
    write_state completed "$pipeline_stage" "$pipeline_release"
    pipeline_terminal=true
    gis_log "Existing failed release passed its supervised checksum and smoke retry: ${pipeline_release}."
    exit 0
fi

pipeline_stage="valhalla"
write_state running "$pipeline_stage" "$pipeline_release"
"${GIS_SCRIPT_DIR}/build-valhalla.sh" "$pbf"

pipeline_stage="pmtiles"
write_state running "$pipeline_stage" "$pipeline_release"
"${GIS_SCRIPT_DIR}/build-pmtiles.sh" "$pbf"

pipeline_stage="finalize"
write_state running "$pipeline_stage" "$pipeline_release"
"${GIS_SCRIPT_DIR}/finalize-release.sh" "$pipeline_release"

pipeline_stage="completed"
write_state completed "$pipeline_stage" "$pipeline_release"
pipeline_terminal=true
trap - INT TERM
gis_log "Supervised full-Russia paired release is complete and inactive: ${pipeline_release}."
