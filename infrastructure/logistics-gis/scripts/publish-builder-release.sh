#!/usr/bin/env bash

set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

[[ $# -eq 2 ]] \
    || gis_fail 'Usage: publish-builder-release.sh russia-YYYYMMDD /absolute/new-state-bundle-directory'
release="$1"
output="$2"
gis_validate_release "$release"
[[ "$output" == /* && "$output" != '/' && ! -e "$output" ]] \
    || gis_fail 'Application-state output must be an absent absolute non-root path.'

pipeline_state="${GIS_STATE_DIR}/builder-pipeline.json"
[[ -s "$pipeline_state" && -f "$pipeline_state" && ! -L "$pipeline_state" ]] \
    || gis_fail 'Completed supervised builder state is required.'
PIPELINE="$pipeline_state" RELEASE="$release" php -r '
    $v=json_decode((string)file_get_contents(getenv("PIPELINE")),true,flags:JSON_THROW_ON_ERROR);
    exit(($v["status"]??null)==="completed"&&($v["stage"]??null)==="completed"
        &&($v["release"]??null)===getenv("RELEASE")&&($v["exit_code"]??null)===0?0:1);
' || gis_fail 'Supervised builder pipeline is not completed for the requested release.'

"${GIS_SCRIPT_DIR}/publish-map-assets.sh" "$release"
"${GIS_SCRIPT_DIR}/publish-valhalla-artifacts.sh" "$release"
"${GIS_SCRIPT_DIR}/activate-map-release.sh" "$release"
"${GIS_SCRIPT_DIR}/export-application-state.sh" "$release" "$output"

gis_log "Builder release ${release} is published and its persistent-map state is ready for application deployment."
