#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'
umask 027

log() {
    printf '[gis-map-deploy] %s\n' "$*"
}

fail() {
    printf '[gis-map-deploy] ERROR: %s\n' "$*" >&2
    exit 1
}

[[ $# -eq 6 ]] \
    || fail 'Expected TARGET_DIR_BASE64, COMMIT_SHA, BUNDLE_DIR, RELEASE, PUBLIC_BASE and APP_ORIGIN.'
target_dir_encoded="$1"
commit_sha="$2"
bundle_dir="$3"
release="$4"
public_base="$5"
application_origin="$6"

[[ "$commit_sha" =~ ^[0-9a-f]{40}$ ]] || fail 'Commit SHA is invalid.'
[[ "$release" =~ ^russia-[0-9]{8}$ ]] || fail 'Release name is invalid.'
[[ "$bundle_dir" == /* && -d "$bundle_dir" && ! -L "$bundle_dir" ]] \
    || fail 'State bundle must be an absolute real directory.'

for command_name in base64 chmod chown cmp cp date find flock git id install mv php realpath rm stat sudo; do
    command -v "$command_name" >/dev/null 2>&1 \
        || fail "Required server command is unavailable: ${command_name}."
done

target_dir="$(printf '%s' "$target_dir_encoded" | base64 --decode)" \
    || fail 'TARGET_DIR could not be decoded.'
[[ "$target_dir" == /* && "$target_dir" != '/' && -d "$target_dir" && ! -L "$target_dir" ]] \
    || fail 'TARGET_DIR must be an existing absolute non-root real directory.'
target_dir="$(realpath -- "$target_dir")"
bundle_dir="$(realpath -- "$bundle_dir")"
[[ "$bundle_dir" == /tmp/pischeprom-gis-map.*/* ]] \
    || fail 'State bundle must stay inside the dedicated deployment temporary directory.'
[[ -f "$target_dir/artisan" && -f "$target_dir/.env" && -d "$target_dir/.git" ]] \
    || fail 'Production Laravel application is incomplete.'
[[ "$(git -C "$target_dir" rev-parse HEAD)" == "$commit_sha" ]] \
    || fail 'Production application is not running the workflow commit.'

application_owner="$(stat -c '%U' -- "$target_dir")"
runtime_group='www-data'
[[ -n "$application_owner" && "$application_owner" != 'UNKNOWN' && "$application_owner" != 'root' ]] \
    || fail 'TARGET_DIR must be owned by a non-root application user.'
id "$application_owner" >/dev/null || fail 'Application owner does not exist.'
id "$runtime_group" >/dev/null || fail 'Runtime group does not exist.'

url_values="$(PUBLIC_BASE="$public_base" APP_ORIGIN="$application_origin" php -r '
    $base=parse_url(getenv("PUBLIC_BASE"));$origin=parse_url(getenv("APP_ORIGIN"));
    $valid=static fn($v): bool => is_array($v)&&strtolower((string)($v["scheme"]??""))==="https"
        &&is_string($v["host"]??null)&&$v["host"]!==""&&!isset($v["user"])&&!isset($v["pass"])
        &&!isset($v["query"])&&!isset($v["fragment"]);
    if(!$valid($base)||!$valid($origin)||str_ends_with((string)($base["path"]??""),"/")
        ||!in_array($origin["path"]??"",["","/"],true))exit(1);
    $assetOrigin="https://".strtolower($base["host"]);
    if(isset($base["port"]))$assetOrigin.=":".(int)$base["port"];
    $appOrigin="https://".strtolower($origin["host"]);
    if(isset($origin["port"]))$appOrigin.=":".(int)$origin["port"];
    echo rtrim(getenv("PUBLIC_BASE"),"/"),PHP_EOL,$assetOrigin,PHP_EOL,$appOrigin,PHP_EOL;
')" || fail 'Map public base or application origin is invalid.'
mapfile -t url_value_lines <<< "$url_values"
public_base="${url_value_lines[0]:-}"
asset_origin="${url_value_lines[1]:-}"
application_origin="${url_value_lines[2]:-}"

PUBLICATION="${bundle_dir}/last-map-publication.json" ACTIVATION="${bundle_dir}/last-activation.json" \
RELEASE="$release" PUBLIC_BASE="$public_base" APP_ORIGIN="$application_origin" php -r '
    $publication=json_decode((string)file_get_contents(getenv("PUBLICATION")),true,flags:JSON_THROW_ON_ERROR);
    $activation=json_decode((string)file_get_contents(getenv("ACTIVATION")),true,flags:JSON_THROW_ON_ERROR);
    $valid=($publication["status"]??null)==="verified"&&($publication["release"]??null)===getenv("RELEASE")
        &&($publication["public_base_url"]??null)===getenv("PUBLIC_BASE")
        &&($publication["application_origin"]??null)===getenv("APP_ORIGIN")
        &&($activation["status"]??null)==="active"&&($activation["release"]??null)===getenv("RELEASE")
        &&($activation["map_delivery"]??null)==="passed"&&($activation["routing_activation"]??null)==="independent";
    exit($valid?0:1);
' || fail 'State bundle does not match the requested persistent-map activation.'

authorization_safe="$(ENV_FILE="$target_dir/.env" php -r '
    $seen=false;$safe=true;
    foreach(preg_split("/\R/",(string)file_get_contents(getenv("ENV_FILE")))?:[] as $line){
        if(preg_match("/^\s*(?!#)(?:export\s+)?LOGISTICS_AUTHORIZATION_ENABLED\s*=\s*(.*?)\s*$/i",$line,$m)!==1)continue;
        $seen=true;$value=trim($m[1]);
        if(strlen($value)>=2&&(($value[0]==="\""&&str_ends_with($value,"\""))||($value[0]==="\047"&&str_ends_with($value,"\047"))))$value=substr($value,1,-1);
        if(strtolower(trim($value))!=="false")$safe=false;
    }
    echo $safe?"yes":"no";
')"
[[ "$authorization_safe" == 'yes' ]] \
    || fail 'LOGISTICS_AUTHORIZATION_ENABLED is not safely false; deployment was stopped.'

exec 9>"${target_dir}/.git/pischeprom-gis-map-deploy.lock"
flock -n 9 || fail 'Another map-state deployment is already running.'

state_base='/srv/pischeprom-gis-state'
if [[ ! -e "$state_base" ]]; then
    sudo install -d -m 2750 -o "$application_owner" -g "$runtime_group" -- "$state_base"
fi
[[ -d "$state_base" && ! -L "$state_base" && "$(realpath -- "$state_base")" == "$state_base" ]] \
    || fail 'Application GIS state base is unsafe.'
sudo chown "$application_owner:$runtime_group" -- "$state_base"
sudo chmod 2750 -- "$state_base"

# The bundle contains only checksummed public metadata. Give the application
# owner temporary read access, install it immutably, then normalize state ACLs.
sudo chown -R "$application_owner:$runtime_group" -- "$bundle_dir"
sudo find "$bundle_dir" -type d -exec chmod 0750 {} +
sudo find "$bundle_dir" -type f -exec chmod 0640 {} +
sudo -u "$application_owner" -- \
    "$target_dir/infrastructure/logistics-gis/scripts/install-application-state.sh" \
    "$bundle_dir" "$state_base"
sudo chown -R "$application_owner:$runtime_group" -- "$state_base"
sudo find "$state_base" -type d -exec chmod 2750 {} +
sudo find "$state_base" -type f -exec chmod 0640 {} +

backup_dir="${target_dir}/storage/app/private/deploy-backups"
install -d -m 0700 -- "$backup_dir"
backup="${backup_dir}/.env-before-gis-map-${release}-$(date -u +%Y%m%dT%H%M%SZ)"
cp -p -- "$target_dir/.env" "$backup"
chmod 0600 -- "$backup"

env_stage="${target_dir}/.env.gis-map.next.$$"
cleanup() {
    rm -f -- "$env_stage"
}
trap cleanup EXIT
ENV_SOURCE="$target_dir/.env" ENV_TARGET="$env_stage" PUBLIC_BASE="$public_base" ASSET_ORIGIN="$asset_origin" php -r '
    $updates=[
        "LOGISTICS_MAP_ENABLED"=>"true",
        "LOGISTICS_MAP_STYLE_URL"=>"/api/logistics/map/style",
        "LOGISTICS_MAP_ASSET_ORIGINS"=>getenv("ASSET_ORIGIN"),
        "LOGISTICS_MAP_PMTILES_URL"=>getenv("PUBLIC_BASE")."/releases/{release}/russia.pmtiles",
        "LOGISTICS_MAP_GLYPHS_URL"=>getenv("PUBLIC_BASE")."/releases/{release}/assets/fonts/{fontstack}/{range}.pbf",
        "LOGISTICS_MAP_SPRITE_URL"=>getenv("PUBLIC_BASE")."/releases/{release}/assets/sprites/basic",
        "LOGISTICS_GIS_RELEASE_MANIFEST"=>"/srv/pischeprom-gis-state/current/release-manifest.json",
        "LOGISTICS_GIS_PREFLIGHT_STATUS"=>"/srv/pischeprom-gis-state/current/last-preflight.json",
        "LOGISTICS_GIS_RANGE_STATUS"=>"/srv/pischeprom-gis-state/current/last-range-check.json",
        "LOGISTICS_GIS_ACTIVATION_STATUS"=>"/srv/pischeprom-gis-state/current/last-activation.json",
        "LOGISTICS_GIS_PRODUCTION_SMOKE_STATUS"=>"/srv/pischeprom-gis-state/current/last-production-smoke.json",
        "LOGISTICS_GIS_MAP_PUBLICATION_STATUS"=>"/srv/pischeprom-gis-state/current/last-map-publication.json",
    ];
    $seen=[];$output=[];
    foreach(preg_split("/\R/",rtrim((string)file_get_contents(getenv("ENV_SOURCE")),"\r\n"))?:[] as $line){
        if(preg_match("/^\s*(?!#)(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=/",$line,$m)===1&&array_key_exists($m[1],$updates)){
            if(isset($seen[$m[1]]))continue;
            $output[]=$m[1]."=".$updates[$m[1]];$seen[$m[1]]=true;
        }else{$output[]=$line;}
    }
    foreach($updates as $key=>$value){if(!isset($seen[$key]))$output[]=$key."=".$value;}
    file_put_contents(getenv("ENV_TARGET"),implode(PHP_EOL,$output).PHP_EOL,LOCK_EX);
'
sudo chmod --reference="$target_dir/.env" "$env_stage"
sudo chown --reference="$target_dir/.env" "$env_stage"
mv -f -- "$env_stage" "$target_dir/.env"
env_switched=true

restore_env() {
    exit_code=$?
    trap - EXIT
    rm -f -- "$env_stage"
    if (( exit_code != 0 )) && [[ "${env_switched:-false}" == 'true' ]]; then
        cp -p -- "$backup" "$target_dir/.env"
        (cd "$target_dir" && php artisan config:cache >/dev/null 2>&1) || true
        printf '[gis-map-deploy] Restored .env from %s after failed activation.\n' "$backup" >&2
    fi
    exit "$exit_code"
}
trap restore_env EXIT

cd "$target_dir"
php artisan config:cache
EXPECTED_RELEASE="$release" EXPECTED_PUBLIC_BASE="$public_base" php -r '
    require "vendor/autoload.php";
    $app=require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if(config("logistics.authorization_enabled")!==false)exit(1);
    $maps=$app->make(App\Services\Logistics\Map\MapConfigurationService::class);
    $configuration=$maps->configuration();
    $style=$maps->style();
    $release=$configuration["release"]??[];
    $source=$style["sources"]["logistics-basemap"]["url"]??"";
    $valid=($configuration["enabled"]??false)===true
        &&($configuration["delivery"]??null)==="object_storage_cdn"
        &&($release["status"]??null)==="active"
        &&($release["release"]??null)===getenv("EXPECTED_RELEASE")
        &&is_string($source)&&str_starts_with($source,"pmtiles://".getenv("EXPECTED_PUBLIC_BASE")."/releases/");
    if(!$valid)exit(1);
    echo "Persistent map configuration verified: ",($release["release"]??"unknown"),PHP_EOL;
'

trap - EXIT
log "Persistent map ${release} is installed and enabled; LOGISTICS_AUTHORIZATION_ENABLED remains false."
log "Recoverable pre-change .env backup: ${backup}"
