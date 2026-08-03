#!/usr/bin/env bash

set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

[[ $# -eq 1 ]] || gis_fail 'Usage: activate-map-release.sh russia-YYYYMMDD'
release="$1"
gis_validate_release "$release"
pbf="${GIS_SOURCE_DIR}/${release}.osm.pbf"
GIS_PREFLIGHT_PBF_PATH="$pbf" "${GIS_SCRIPT_DIR}/preflight.sh" --mode verify

for directory in "$GIS_RELEASES_DIR" "$GIS_LOCK_DIR" "$GIS_STATE_DIR"; do
    [[ -d "$directory" && -w "$directory" ]] \
        || gis_fail "Required writable directory is missing: ${directory}"
    gis_assert_inside_base "$directory" >/dev/null
done
exec 9>"${GIS_LOCK_DIR}/gis-map-activate.lock"
flock -n 9 || gis_fail 'Another persistent-map activation is already running.'

target="${GIS_RELEASES_DIR}/${release}"
[[ -d "$target" && ! -L "$target" ]] || gis_fail 'Verified release target is unavailable.'
target="$(gis_assert_inside_base "$target")"
manifest="${target}/release-manifest.json"
pmtiles="${target}/map/russia.pmtiles"
assets="${target}/map/assets"
[[ -s "$manifest" && ! -L "$manifest" && -s "$pmtiles" && ! -L "$pmtiles" ]] \
    || gis_fail 'Verified release manifest and PMTiles are required.'
[[ -z "$(find "$target" -type l -print -quit)" ]] \
    || gis_fail 'Verified release contains a forbidden symlink.'
php "${GIS_SCRIPT_DIR}/validate-map-assets.php" "$assets"
gis_low_priority pmtiles verify "$pmtiles"

map_values="$(MANIFEST="$manifest" php -r '
    $v=json_decode((string)file_get_contents(getenv("MANIFEST")),true,flags:JSON_THROW_ON_ERROR);
    $smoke=$v["smoke_tests"]??[];$pmtiles=$v["pmtiles"]??[];
    $valid=($v["status"]??null)==="verified"
        &&($v["release"]??null)===basename(dirname(getenv("MANIFEST")))
        &&($smoke["status"]??null)==="passed"
        &&is_string($pmtiles["sha256"]??null)&&preg_match("/^[0-9a-f]{64}$/",$pmtiles["sha256"])===1
        &&(int)($pmtiles["size_bytes"]??0)>0;
    if(!$valid)exit(1);
    echo $pmtiles["sha256"],PHP_EOL,(int)$pmtiles["size_bytes"],PHP_EOL;
')" || gis_fail 'Only a matching paired release with passed staging smoke may activate its persistent map.'
mapfile -t map_value_lines <<< "$map_values"
expected_sha="${map_value_lines[0]:-}"
expected_size="${map_value_lines[1]:-0}"
[[ "$(gis_sha256 "$pmtiles")" == "$expected_sha" && "$(gis_file_size "$pmtiles")" == "$expected_size" ]] \
    || gis_fail 'Local PMTiles no longer matches the verified paired release.'

public_base="${GIS_PUBLIC_ASSET_BASE_URL:-}"
application_origin="${GIS_MAP_APPLICATION_ORIGIN:-}"
url_settings_valid="$(PUBLIC_BASE="$public_base" APP_ORIGIN="$application_origin" php -r '
    $validOrigin=static function(string $value): bool {
        $parts=parse_url($value);
        return is_array($parts)&&strtolower((string)($parts["scheme"]??""))==="https"
            &&is_string($parts["host"]??null)&&$parts["host"]!==""
            &&!isset($parts["user"])&&!isset($parts["pass"])&&!isset($parts["query"])&&!isset($parts["fragment"])
            &&in_array($parts["path"]??"",["","/"],true);
    };
    $base=parse_url(getenv("PUBLIC_BASE"));
    $baseValid=is_array($base)&&strtolower((string)($base["scheme"]??""))==="https"
        &&is_string($base["host"]??null)&&$base["host"]!==""
        &&!isset($base["user"])&&!isset($base["pass"])&&!isset($base["query"])&&!isset($base["fragment"])
        &&!str_ends_with((string)($base["path"]??""),"/");
    echo $baseValid&&$validOrigin(getenv("APP_ORIGIN"))?"yes":"no";
')"
[[ "$url_settings_valid" == 'yes' ]] \
    || gis_fail 'Persistent map activation requires a clean public HTTPS base and exact HTTPS application origin.'
public_base="${public_base%/}"
public_pmtiles_url="${public_base}/releases/${release}/russia.pmtiles"

publication="${GIS_STATE_DIR}/last-map-publication.json"
[[ -s "$publication" && -f "$publication" && ! -L "$publication" ]] \
    || gis_fail 'Verified object-storage publication state is required.'
publication_matches="$(PUBLICATION="$publication" RELEASE="$release" URL="$public_pmtiles_url" ORIGIN="$application_origin" SHA="$expected_sha" SIZE="$expected_size" php -r '
    $v=json_decode((string)file_get_contents(getenv("PUBLICATION")),true,flags:JSON_THROW_ON_ERROR);$p=$v["pmtiles"]??[];
    $valid=($v["status"]??null)==="verified"&&($v["release"]??null)===getenv("RELEASE")
        &&($v["application_origin"]??null)===getenv("ORIGIN")&&($p["url"]??null)===getenv("URL")
        &&($p["sha256"]??null)===getenv("SHA")&&(int)($p["size_bytes"]??0)===(int)getenv("SIZE")
        &&($p["range_requests"]??null)==="passed"&&($p["cors"]??null)==="passed";
    echo $valid?"yes":"no";
')"
[[ "$publication_matches" == 'yes' ]] \
    || gis_fail 'Object-storage publication state does not match the verified release.'

GIS_EXPECTED_CORS_ORIGIN="$application_origin" \
    "${GIS_SCRIPT_DIR}/check-pmtiles-range.sh" "$public_pmtiles_url"
range_state="${GIS_STATE_DIR}/last-range-check.json"
range_matches="$(RANGE_STATE="$range_state" SIZE="$expected_size" ORIGIN="$application_origin" php -r '
    $v=json_decode((string)file_get_contents(getenv("RANGE_STATE")),true,flags:JSON_THROW_ON_ERROR);
    echo (($v["healthy"]??false)===true
        &&(int)($v["content_length"]??0)===(int)getenv("SIZE")
        &&($v["cors_origin"]??null)===getenv("ORIGIN")
        &&($v["cors_allow_origin"]??null)===getenv("ORIGIN"))?"yes":"no";
')"
[[ "$range_matches" == 'yes' ]] || gis_fail 'Public PMTiles Range/CORS state is inconsistent.'

activation="${GIS_STATE_DIR}/last-activation.json"
map_smoke="${GIS_STATE_DIR}/last-production-smoke.json"
for state_path in "$activation" "$map_smoke"; do
    [[ ! -e "$state_path" || ( -f "$state_path" && ! -L "$state_path" ) ]] \
        || gis_fail 'Persistent-map state target is unsafe.'
done
previous_release='none'
if [[ -s "$activation" ]]; then
    previous_release="$(ACTIVATION="$activation" NEW_RELEASE="$release" php -r '
        $v=json_decode((string)file_get_contents(getenv("ACTIVATION")),true,flags:JSON_THROW_ON_ERROR);
        $current=$v["release"]??"";$previous=$v["previous_release"]??"";
        if($current===getenv("NEW_RELEASE")){$current=$previous;}
        echo is_string($current)&&preg_match("/^russia-[0-9]{8}$/",$current)===1?$current:"none";
    ')"
fi

activation_part="${activation}.next.$$"
smoke_part="${map_smoke}.next.$$"
cleanup_state() { rm -f -- "$activation_part" "$smoke_part"; }
trap cleanup_state EXIT
ACTIVATION_RELEASE="$release" ACTIVATION_PREVIOUS="$previous_release" php -r '
    $value=[
        "status"=>"active","release"=>getenv("ACTIVATION_RELEASE"),
        "previous_release"=>getenv("ACTIVATION_PREVIOUS"),"activated_at"=>gmdate("c"),
        "map_delivery"=>"passed","routing_activation"=>"independent",
    ];
    file_put_contents($argv[1],json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL,LOCK_EX);
' "$activation_part"
SMOKE_RELEASE="$release" SMOKE_URL="$public_pmtiles_url" php -r '
    $value=[
        "status"=>"passed","kind"=>"map-delivery","coverage"=>"Russia",
        "release"=>getenv("SMOKE_RELEASE"),"checked_at"=>gmdate("c"),
        "results"=>[
            ["name"=>"PMTiles HTTPS Range","type"=>"range","status_code"=>206],
            ["name"=>"PMTiles application CORS","type"=>"cors","passed"=>true],
            ["name"=>"Immutable public map release","type"=>"publication","url"=>getenv("SMOKE_URL")],
        ],
    ];
    file_put_contents($argv[1],json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL,LOCK_EX);
' "$smoke_part"
mv -f -- "$smoke_part" "$map_smoke"
mv -f -- "$activation_part" "$activation"
trap - EXIT

gis_log "Persistent map release ${release} is active independently of the optional Valhalla runtime."
gis_log 'No Valhalla symlink, service, route cache or matrix value was changed.'
