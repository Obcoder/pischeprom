#!/usr/bin/env bash

set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"
trap 'preflight_status=$?; trap - ERR; printf "[logistics-gis] ERROR: preflight aborted unexpectedly at line %s (exit %s).\n" "$LINENO" "$preflight_status" >&2; exit "$preflight_status"' ERR

mode="full"
json_output=false
fixture=""
while (( $# > 0 )); do
    case "$1" in
        --mode)
            [[ $# -ge 2 ]] || gis_fail '--mode requires a value.'
            mode="$2"
            shift 2
            ;;
        --json)
            json_output=true
            shift
            ;;
        --fixture)
            [[ $# -ge 2 ]] || gis_fail '--fixture requires a JSON file.'
            fixture="$2"
            shift 2
            ;;
        *) gis_fail "Unknown preflight argument: $1" ;;
    esac
done
[[ "$mode" =~ ^(download|valhalla|planetiler|verify|activate|full)$ ]] \
    || gis_fail 'Mode must be download, valhalla, planetiler, verify, activate or full.'

calculator="${GIS_SCRIPT_DIR}/calculate-preflight.php"
[[ -r "$calculator" ]] || gis_fail 'Preflight calculator is missing.'

if [[ -n "$fixture" ]]; then
    [[ -r "$fixture" ]] || gis_fail 'Fixture is not readable.'
    result_json="$(php "$calculator" < "$fixture")"
else
    checked_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    failures=()
    warnings=()
    notices=()
    missing_commands=()

    if [[ "$(uname -s 2>/dev/null || true)" != "Linux" ]]; then
        failures+=('GIS preflight must run on the target Linux host; local results are not production evidence.')
    fi

    required=(awk basename cp curl date df dirname du find findmnt flock free head id ln md5sum mkdir mktemp mv nproc php ps realpath rm sed seq sha256sum sleep sort stat tee tr uname)
    case "$mode" in
        valhalla)
            required+=(ionice nice spatialite spatialite_tool unzip valhalla_build_admins valhalla_build_config valhalla_build_extract valhalla_build_tiles valhalla_service)
            ;;
        planetiler)
            required+=(ionice java nice pmtiles)
            ;;
        verify)
            required+=(ionice nice pmtiles valhalla_service)
            ;;
        activate)
            required+=(ionice nice pmtiles)
            if [[ "${GIS_REQUIRE_LOCAL_MAP_NGINX:-true}" == "true" ]]; then
                required+=(nginx)
            fi
            ;;
        full)
            required+=(ionice java nice pmtiles spatialite spatialite_tool unzip valhalla_build_admins valhalla_build_config valhalla_build_extract valhalla_build_tiles valhalla_service)
            if [[ "${GIS_REQUIRE_OBJECT_STORAGE_PUBLICATION:-false}" == "true" ]]; then
                publication_cli="${GIS_OBJECT_STORAGE_CLI:-aws}"
                if [[ "$publication_cli" == 'aws' || "$publication_cli" == 'yc' ]]; then
                    required+=("$publication_cli")
                else
                    failures+=('GIS_OBJECT_STORAGE_CLI должен быть aws или yc.')
                fi
            fi
            if [[ "${GIS_REQUIRE_LOCAL_MAP_NGINX:-true}" == "true" ]]; then
                required+=(nginx)
            fi
            ;;
    esac
    if [[ "$mode" =~ ^(valhalla|planetiler|full)$ && ! -x /usr/bin/time ]]; then
        failures+=('GNU /usr/bin/time отсутствует; без него нельзя зафиксировать пиковое потребление ресурсов сборки.')
    fi
    for command_name in "${required[@]}"; do
        if ! command -v "$command_name" >/dev/null 2>&1; then
            missing_commands+=("$command_name")
        fi
    done
    if (( ${#missing_commands[@]} > 0 )); then
        failures+=("Отсутствуют обязательные программы: $(IFS=', '; printf '%s' "${missing_commands[*]}").")
    fi
    if [[ "$mode" == "activate" \
        || ( "$mode" == "full" && "${GIS_REQUIRE_CURRENT_VALHALLA_HEALTH:-true}" != "false" ) ]]
    then
        if [[ -n "${VALHALLA_SYSTEMD_UNIT:-}" ]]; then
            command -v systemctl >/dev/null 2>&1 \
                || failures+=('Для заданного VALHALLA_SYSTEMD_UNIT отсутствует systemctl.')
        elif [[ "${VALHALLA_RESTART_HELPER:-}" != /* \
            || ! -x "${VALHALLA_RESTART_HELPER:-}" \
            || -L "${VALHALLA_RESTART_HELPER:-}" ]]; then
            failures+=('Не задан проверенный способ restart существующего Valhalla: VALHALLA_SYSTEMD_UNIT или абсолютный исполняемый VALHALLA_RESTART_HELPER.')
        fi
    fi
    if [[ "$mode" =~ ^(valhalla|activate|full)$ ]]; then
        configured_listen="${VALHALLA_SERVICE_LISTEN:-tcp://127.0.0.1:8002}"
        if [[ "$configured_listen" =~ ^tcp://127\.0\.0\.1:([0-9]{1,5})$ ]]; then
            configured_port="${BASH_REMATCH[1]}"
        elif [[ "$configured_listen" =~ ^tcp://0\.0\.0\.0:([0-9]{1,5})$ \
            && "${VALHALLA_ALLOW_WILDCARD_LISTEN:-false}" == "true" ]]
        then
            configured_port="${BASH_REMATCH[1]}"
            notices+=('Valhalla wildcard bind явно разрешён; внешний firewall/publish должен оставлять порт private/loopback.')
        elif [[ "$configured_listen" =~ ^tcp://([0-9.]+):([0-9]{1,5})$ \
            && "${VALHALLA_ALLOW_PRIVATE_NETWORK_LISTEN:-false}" == "true" ]]
        then
            configured_host="${BASH_REMATCH[1]}"
            configured_port="${BASH_REMATCH[2]}"
            if [[ "$(gis_is_private_ipv4 "$configured_host")" == "yes" ]]; then
                notices+=('Valhalla использует явно разрешённый private/VPN bind; firewall должен допускать только application VPS.')
            else
                configured_port=0
                failures+=('VALHALLA private bind не входит в RFC1918 или 100.64.0.0/10.')
            fi
        else
            configured_port=0
            failures+=('VALHALLA_SERVICE_LISTEN не является безопасным явно разрешённым TCP bind.')
        fi
        if (( configured_port < 1 || configured_port > 65535 )); then
            failures+=('VALHALLA_SERVICE_LISTEN содержит недопустимый TCP port.')
        fi
    fi

    os_name="$(. /etc/os-release 2>/dev/null && printf '%s %s' "${NAME:-Linux}" "${VERSION_ID:-unknown}" || uname -s)"
    kernel="$(uname -r 2>/dev/null || true)"
    architecture="$(uname -m 2>/dev/null || true)"
    cpu_model="$(awk -F: '/model name/{sub(/^[[:space:]]+/, "", $2); print $2; exit}' /proc/cpuinfo 2>/dev/null || true)"
    logical_cores="$(nproc 2>/dev/null || printf '1')"
    physical_cores="$( (lscpu -p=core,socket 2>/dev/null || true) | awk '!/^#/ {seen[$1 FS $2]=1} END {print length(seen)}')"
    [[ "$physical_cores" =~ ^[0-9]+$ && "$physical_cores" -gt 0 ]] || physical_cores="$logical_cores"
    if [[ -r /proc/loadavg ]]; then
        IFS=' ' read -r load_one load_five load_fifteen _ < /proc/loadavg
    else
        load_one=0
        load_five=0
        load_fifteen=0
    fi

    mem_total_kb="$(awk '/MemTotal/{print $2}' /proc/meminfo 2>/dev/null || printf '0')"
    mem_available_kb="$(awk '/MemAvailable/{print $2}' /proc/meminfo 2>/dev/null || printf '0')"
    swap_total_kb="$(awk '/SwapTotal/{print $2}' /proc/meminfo 2>/dev/null || printf '0')"
    swap_free_kb="$(awk '/SwapFree/{print $2}' /proc/meminfo 2>/dev/null || printf '0')"
    mem_total_bytes="$(( ${mem_total_kb:-0} * 1024 ))"
    mem_available_bytes="$(( ${mem_available_kb:-0} * 1024 ))"
    mem_used_bytes="$(( mem_total_bytes > mem_available_bytes ? mem_total_bytes - mem_available_bytes : 0 ))"
    swap_total_bytes="$(( ${swap_total_kb:-0} * 1024 ))"
    swap_available_bytes="$(( ${swap_free_kb:-0} * 1024 ))"

    disk_probe="$GIS_BASE_DIR"
    while [[ ! -e "$disk_probe" && "$disk_probe" != "/" ]]; do
        disk_probe="$(dirname "$disk_probe")"
    done
    disk_free="$( (df -B1 --output=avail "$disk_probe" 2>/dev/null | awk 'NR==2 {print $1}') || true)"
    # GNU df treats --inodes (-i) and --output as mutually exclusive. Asking
    # for the inode-specific field is sufficient and keeps the probe truthful.
    free_inodes="$( (df --output=iavail "$disk_probe" 2>/dev/null | awk 'NR==2 {print $1}') || true)"
    filesystem="$(findmnt -n -o FSTYPE --target "$disk_probe" 2>/dev/null || true)"
    mount_source="$(findmnt -n -o SOURCE --target "$disk_probe" 2>/dev/null || true)"
    storage_kind="unknown"
    if command -v lsblk >/dev/null 2>&1 && [[ -n "$mount_source" ]]; then
        rotational="$(lsblk -ndo ROTA "$mount_source" 2>/dev/null | head -n1 | tr -d '[:space:]' || true)"
        case "$rotational" in
            0) storage_kind="SSD/NVMe" ;;
            1) storage_kind="HDD" ;;
        esac
    fi
    [[ "$storage_kind" != "unknown" ]] || notices+=('Тип накопителя определить не удалось; для GIS-сборки рекомендуется SSD/NVMe.')
    staging_writable=false
    if [[ -d "$GIS_STAGING_DIR" && -w "$GIS_STAGING_DIR" ]]; then
        staging_writable=true
    fi

    remote_headers=""
    remote_size=0
    remote_modified=""
    remote_checksum=""
    remote_effective_url=""
    remote_osm_timestamp=""
    if command -v curl >/dev/null 2>&1 && [[ "$mode" =~ ^(download|full)$ ]]; then
        remote_probe="$(curl --fail --silent --show-error --location --max-time 30 --range 0-0 --dump-header - --output /dev/null --write-out $'\nGIS_EFFECTIVE_URL=%{url_effective}\n' "$GIS_PBF_URL" 2>/dev/null || true)"
        remote_effective_url="$(printf '%s\n' "$remote_probe" | awk -F= '/^GIS_EFFECTIVE_URL=/{value=substr($0,index($0,"=")+1)} END{print value}')"
        remote_headers="$(printf '%s\n' "$remote_probe" | sed '/^GIS_EFFECTIVE_URL=/d')"
        remote_size="$(printf '%s\n' "$remote_headers" | awk '
            {line=tolower($0)}
            line ~ /^content-range:/ {sub(/\r$/, ""); split($0, parts, "/"); value=parts[length(parts)]}
            line ~ /^content-length:/ && value == "" {sub(/\r$/, ""); value=$2}
            END {gsub(/[^0-9]/, "", value); if (value == "") print 0; else print value}
        ')"
        remote_modified="$(printf '%s\n' "$remote_headers" | awk '{line=tolower($0)} line ~ /^last-modified:/ {sub(/^[^:]+:[[:space:]]*/, ""); sub(/\r$/, ""); value=$0} END {print value}')"
        remote_checksum="$(curl --fail --silent --show-error --location --max-time 30 "$GIS_PBF_MD5_URL" 2>/dev/null | awk 'NR==1 {print tolower($1)}' || true)"
        remote_osm_timestamp="$(curl --fail --silent --show-error --location --max-time 30 "$GIS_PBF_INDEX_URL" 2>/dev/null | php -r '$html=stream_get_contents(STDIN);if(preg_match("/russia-latest\\.osm\\.pbf.*?contains\\s+all\\s+OSM\\s+data\\s+up\\s+to\\s+([0-9T:+-]+Z)/si",$html,$m))echo $m[1];' || true)"
    fi
    if [[ "$mode" =~ ^(download|full)$ ]]; then
        [[ "$remote_effective_url" =~ ^https://download\.geofabrik\.de/russia-[0-9]{6}\.osm\.pbf$ ]] \
            || failures+=('Latest Russia extract не разрешился в immutable Geofabrik URL.')
        [[ "$remote_checksum" =~ ^[0-9a-f]{32}$ ]] \
            || failures+=('Не удалось получить корректный опубликованный MD5 полного Russia PBF.')
        [[ "$remote_osm_timestamp" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}T ]] \
            || failures+=('Не удалось получить точный timestamp OSM-данных из официального индекса Geofabrik.')
    fi

    current_pbf=""
    if [[ -n "${GIS_PREFLIGHT_PBF_PATH:-}" ]]; then
        current_pbf="$(gis_realpath_existing "$GIS_PREFLIGHT_PBF_PATH")"
        gis_assert_inside_base "$current_pbf" >/dev/null
        [[ -f "$current_pbf" ]] || failures+=('Выбранный для preflight PBF не является обычным файлом.')
    elif [[ -d "$GIS_SOURCE_DIR" ]]; then
        current_pbf="$(
            find "$GIS_SOURCE_DIR" -maxdepth 1 -type f -name 'russia-[0-9]*.osm.pbf' -print 2>/dev/null \
                | sort \
                | tail -n1 \
                || true
        )"
    fi
    current_pbf_size=0
    if [[ -n "$current_pbf" ]]; then
        current_pbf_size="$(gis_file_size "$current_pbf")"
    fi
    active_graph_size="$(gis_directory_size "${GIS_BASE_DIR}/current/valhalla")"
    previous_graph_size="$(gis_directory_size "${GIS_BASE_DIR}/previous/valhalla")"
    active_pmtiles_size="$(gis_file_size "${GIS_BASE_DIR}/current/map/russia.pmtiles")"

    valhalla_healthy=false
    valhalla_version=""
    if command -v curl >/dev/null 2>&1; then
        status_json="$(curl --fail --silent --show-error --max-time 5 "$VALHALLA_STATUS_URL" 2>/dev/null || true)"
        if [[ -n "$status_json" ]]; then
            valhalla_healthy=true
            valhalla_version="$(STATUS_JSON="$status_json" php -r '$v=json_decode((string)getenv("STATUS_JSON"),true);echo is_string($v["version"]??null)?$v["version"]:"";' 2>/dev/null || true)"
        fi
    fi

    java_major=0
    java_version=""
    if command -v java >/dev/null 2>&1; then
        java_version="$(java -version 2>&1 | head -n1)"
        java_major="$(printf '%s' "$java_version" | sed -E 's/.*version "([0-9]+).*/\1/' | sed -E 's/^1\.//')"
        [[ "$java_major" =~ ^[0-9]+$ ]] || java_major=0
    fi
    planetiler_jar="${PLANETILER_JAR:-/opt/planetiler/planetiler.jar}"
    if [[ "$mode" =~ ^(planetiler|full)$ && ! -s "$planetiler_jar" ]]; then
        failures+=('Закреплённый Planetiler JAR отсутствует или пуст.')
    fi
    planetiler_version="${PLANETILER_VERSION:-}"
    expected_planetiler_sha="${PLANETILER_JAR_SHA256:-}"
    if [[ "$mode" =~ ^(planetiler|full)$ ]]; then
        [[ -n "$planetiler_version" ]] || failures+=('PLANETILER_VERSION не задан; сборка должна использовать явно закреплённый release.')
        if [[ ! "$expected_planetiler_sha" =~ ^[0-9a-fA-F]{64}$ ]]; then
            failures+=('PLANETILER_JAR_SHA256 не задан либо имеет неверный формат.')
        elif [[ -s "$planetiler_jar" ]]; then
            actual_planetiler_sha="$(gis_sha256 "$planetiler_jar")"
            expected_planetiler_sha_lower="$(printf '%s' "$expected_planetiler_sha" | tr '[:upper:]' '[:lower:]')"
            [[ "$actual_planetiler_sha" == "$expected_planetiler_sha_lower" ]] \
                || failures+=('Planetiler JAR не совпадает с закреплённым SHA-256.')
        fi
    fi
    pmtiles_version="$(pmtiles version 2>/dev/null | head -n1 || true)"
    valhalla_tools_version="$(valhalla_service --version 2>/dev/null | head -n1 || true)"
    expected_pmtiles_version="${GIS_PMTILES_CLI_VERSION:-}"
    expected_valhalla_version="${VALHALLA_EXPECTED_VERSION:-}"
    if [[ "$mode" =~ ^(planetiler|verify|activate|full)$ ]]; then
        [[ -n "$expected_pmtiles_version" ]] \
            || failures+=('GIS_PMTILES_CLI_VERSION не задан; PMTiles CLI должен быть закреплён.')
        [[ -z "$expected_pmtiles_version" || "$pmtiles_version" == *"$expected_pmtiles_version"* ]] \
            || failures+=('Установленная версия PMTiles CLI не совпадает с закреплённой.')
    fi
    if [[ "$mode" =~ ^(valhalla|verify|full)$ ]]; then
        [[ -n "$expected_valhalla_version" ]] \
            || failures+=('VALHALLA_EXPECTED_VERSION не задан; используемая Valhalla должна быть закреплена.')
        [[ -z "$expected_valhalla_version" || "$valhalla_tools_version" == *"$expected_valhalla_version"* ]] \
            || failures+=('Установленная версия Valhalla build tools не совпадает с закреплённой.')
    fi
    top_processes="$(ps -eo pid,user,%cpu,%mem,rss,comm --sort=-%cpu 2>/dev/null | head -n 16 || true)"

    export PF_MODE="$mode" PF_CHECKED_AT="$checked_at" PF_OS_NAME="$os_name" PF_KERNEL="$kernel" PF_ARCH="$architecture"
    export PF_CPU_MODEL="$cpu_model" PF_PHYSICAL="$physical_cores" PF_LOGICAL="$logical_cores"
    export PF_LOAD_ONE="$load_one" PF_LOAD_FIVE="$load_five" PF_LOAD_FIFTEEN="$load_fifteen"
    export PF_MEM_TOTAL="$mem_total_bytes" PF_MEM_AVAILABLE="$mem_available_bytes" PF_MEM_USED="$mem_used_bytes"
    export PF_SWAP_TOTAL="$swap_total_bytes" PF_SWAP_AVAILABLE="$swap_available_bytes"
    export PF_DISK_FREE="${disk_free:-0}" PF_FREE_INODES="${free_inodes:-0}" PF_FILESYSTEM="$filesystem" PF_STORAGE_KIND="$storage_kind" PF_STAGING_WRITABLE="$staging_writable"
    export PF_PBF_URL="$GIS_PBF_URL" PF_PBF_EFFECTIVE_URL="$remote_effective_url" PF_REMOTE_SIZE="${remote_size:-0}" PF_REMOTE_MODIFIED="$remote_modified" PF_REMOTE_OSM_TIMESTAMP="$remote_osm_timestamp" PF_REMOTE_MD5="$remote_checksum" PF_CURRENT_PBF_SIZE="$current_pbf_size"
    export PF_ACTIVE_GRAPH="$active_graph_size" PF_PREVIOUS_GRAPH="$previous_graph_size" PF_ACTIVE_PMTILES="$active_pmtiles_size"
    export PF_JAVA_MAJOR="$java_major" PF_JAVA_VERSION="$java_version" PF_PLANETILER_VERSION="$planetiler_version" PF_PMTILES_VERSION="$pmtiles_version" PF_VALHALLA_TOOLS_VERSION="$valhalla_tools_version"
    export PF_VALHALLA_HEALTHY="$valhalla_healthy" PF_VALHALLA_VERSION="$valhalla_version" PF_VALHALLA_CURRENT_REQUIRED="${GIS_REQUIRE_CURRENT_VALHALLA_HEALTH:-true}"
    export PF_FAILURES="$(printf '%s\n' "${failures[@]:-}")" PF_WARNINGS="$(printf '%s\n' "${warnings[@]:-}")" PF_NOTICES="$(printf '%s\n' "${notices[@]:-}")"
    export PF_APP_DISK_RESERVE="${GIS_APP_DISK_RESERVE_BYTES:-21474836480}" PF_APP_RAM_RESERVE="${GIS_APP_RAM_RESERVE_BYTES:-2147483648}"
    export PF_PLANETILER_DISK_MULTIPLIER="${GIS_PLANETILER_DISK_MULTIPLIER:-10}" PF_PLANETILER_RAM_MULTIPLIER="${GIS_PLANETILER_RAM_MULTIPLIER:-0.5}"
    export PF_VALHALLA_GRAPH_MULTIPLIER="${GIS_VALHALLA_GRAPH_MULTIPLIER:-3}" PF_VALHALLA_RAM_MULTIPLIER="${GIS_VALHALLA_RAM_MULTIPLIER:-0.75}" PF_PMTILES_MULTIPLIER="${GIS_PMTILES_MULTIPLIER:-1.5}"
    export PF_WARN_LOAD="${GIS_WARN_LOAD_PER_CORE:-0.7}" PF_FAIL_LOAD="${GIS_FAIL_LOAD_PER_CORE:-1.0}"

    input_json="$(php -r '
        $lines = static fn(string $name): array => array_values(array_filter(
            preg_split("/\R/", (string) getenv($name)) ?: [],
            static fn($value): bool => $value !== ""
        ));
        $integer = static fn(string $name): int => (int) getenv($name);
        $number = static fn(string $name): float => (float) getenv($name);
        echo json_encode([
            "mode" => getenv("PF_MODE"), "checked_at" => getenv("PF_CHECKED_AT"),
            "system" => ["distribution" => getenv("PF_OS_NAME"), "kernel" => getenv("PF_KERNEL"), "architecture" => getenv("PF_ARCH")],
            "cpu" => ["model" => getenv("PF_CPU_MODEL"), "physical_cores" => $integer("PF_PHYSICAL"), "logical_cores" => $integer("PF_LOGICAL"), "load_average" => ["one" => $number("PF_LOAD_ONE"), "five" => $number("PF_LOAD_FIVE"), "fifteen" => $number("PF_LOAD_FIFTEEN")]],
            "memory" => ["total_bytes" => $integer("PF_MEM_TOTAL"), "available_bytes" => $integer("PF_MEM_AVAILABLE"), "used_bytes" => $integer("PF_MEM_USED"), "swap_total_bytes" => $integer("PF_SWAP_TOTAL"), "swap_available_bytes" => $integer("PF_SWAP_AVAILABLE")],
            "disk" => ["free_bytes" => $integer("PF_DISK_FREE"), "free_inodes" => $integer("PF_FREE_INODES"), "filesystem" => getenv("PF_FILESYSTEM"), "storage_kind" => getenv("PF_STORAGE_KIND"), "writable" => getenv("PF_STAGING_WRITABLE") === "true"],
            "pbf" => ["source_url" => getenv("PF_PBF_URL"), "resolved_source_url" => getenv("PF_PBF_EFFECTIVE_URL"), "remote_size_bytes" => $integer("PF_REMOTE_SIZE"), "last_modified" => getenv("PF_REMOTE_MODIFIED"), "osm_data_timestamp" => getenv("PF_REMOTE_OSM_TIMESTAMP"), "published_md5" => getenv("PF_REMOTE_MD5"), "current_size_bytes" => $integer("PF_CURRENT_PBF_SIZE")],
            "existing" => ["active_graph_bytes" => $integer("PF_ACTIVE_GRAPH"), "previous_graph_bytes" => $integer("PF_PREVIOUS_GRAPH"), "active_pmtiles_bytes" => $integer("PF_ACTIVE_PMTILES")],
            "components" => ["java_major" => $integer("PF_JAVA_MAJOR"), "java_version" => getenv("PF_JAVA_VERSION"), "planetiler_version" => getenv("PF_PLANETILER_VERSION"), "pmtiles_version" => getenv("PF_PMTILES_VERSION"), "valhalla_tools_version" => getenv("PF_VALHALLA_TOOLS_VERSION")],
            "valhalla" => ["healthy" => getenv("PF_VALHALLA_HEALTHY") === "true", "version" => getenv("PF_VALHALLA_VERSION"), "current_required" => getenv("PF_VALHALLA_CURRENT_REQUIRED") !== "false"],
            "thresholds" => ["app_disk_reserve_bytes" => $integer("PF_APP_DISK_RESERVE"), "app_ram_reserve_bytes" => $integer("PF_APP_RAM_RESERVE"), "planetiler_disk_multiplier" => $number("PF_PLANETILER_DISK_MULTIPLIER"), "planetiler_ram_multiplier" => $number("PF_PLANETILER_RAM_MULTIPLIER"), "valhalla_graph_pbf_multiplier" => $number("PF_VALHALLA_GRAPH_MULTIPLIER"), "valhalla_ram_multiplier" => $number("PF_VALHALLA_RAM_MULTIPLIER"), "pmtiles_pbf_multiplier" => $number("PF_PMTILES_MULTIPLIER"), "warn_load_per_core" => $number("PF_WARN_LOAD"), "fail_load_per_core" => $number("PF_FAIL_LOAD")],
            "failures" => $lines("PF_FAILURES"), "warnings" => $lines("PF_WARNINGS"), "notices" => $lines("PF_NOTICES")
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    ')"
    result_json="$(printf '%s' "$input_json" | php "$calculator")"
fi

result="$(printf '%s' "$result_json" | php -r '$v=json_decode(stream_get_contents(STDIN),true,flags:JSON_THROW_ON_ERROR);echo $v["result"]??"FAIL";')"
exit_code="$(printf '%s' "$result_json" | php -r '$v=json_decode(stream_get_contents(STDIN),true,flags:JSON_THROW_ON_ERROR);echo (int)($v["exit_code"]??3);')"

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
log_path="${GIS_LOG_DIR}/preflight-${timestamp}-${mode}.log"
if [[ ! -d "$GIS_LOG_DIR" || ! -w "$GIS_LOG_DIR" ]]; then
    log_path="${TMPDIR:-/tmp}/pischeprom-gis-preflight-${timestamp}-${mode}.log"
fi
{
    printf 'Pischeprom GIS preflight\nmode=%s\nresult=%s\nbase=%s\n\n' "$mode" "$result" "$GIS_BASE_DIR"
    if [[ -n "${top_processes:-}" ]]; then
        printf 'Top processes:\n%s\n\n' "$top_processes"
    fi
    printf '%s\n' "$result_json"
} > "$log_path"

if [[ -d "$GIS_STATE_DIR" && -w "$GIS_STATE_DIR" ]]; then
    state_tmp="${GIS_STATE_DIR}/.last-preflight.json.$$"
    printf '%s\n' "$result_json" > "$state_tmp"
    mv -f -- "$state_tmp" "${GIS_STATE_DIR}/last-preflight.json"
fi

if $json_output; then
    printf '%s\n' "$result_json"
else
    printf 'GIS preflight %s (%s). Log: %s\n' "$result" "$mode" "$log_path"
    printf '%s' "$result_json" | php -r '
        $v=json_decode(stream_get_contents(STDIN),true,flags:JSON_THROW_ON_ERROR);
        printf("CPU: %s, logical=%d, load/core=%.2f\n", $v["cpu"]["model"]??"unknown", $v["cpu"]["logical_cores"]??0, $v["cpu"]["load_per_core"]??0);
        printf("RAM available/required: %d / %d bytes\n", $v["memory"]["available_bytes"]??0, $v["requirements"]["ram_required_bytes"]??0);
        printf("Disk free/required: %d / %d bytes; inodes=%d\n", $v["disk"]["free_bytes"]??0, $v["requirements"]["disk_required_bytes"]??0, $v["disk"]["free_inodes"]??0);
        foreach ($v["failures"]??[] as $message) fwrite(STDOUT, "FAIL: {$message}\n");
        foreach ($v["warnings"]??[] as $message) fwrite(STDOUT, "WARN: {$message}\n");
    '
fi

exit "$exit_code"
