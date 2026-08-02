#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'
umask 022

fail() {
    printf '[logistics-gis] ERROR: %s\n' "$*" >&2
    exit 1
}

[[ "$(uname -s)" == 'Linux' && "$(uname -m)" == 'x86_64' ]] \
    || fail 'Pinned GIS toolchain installer supports Linux x86_64 only.'
[[ "${EUID}" -eq 0 ]] || fail 'Pinned GIS toolchain installation must run as root.'

for command_name in awk basename curl head install ln mktemp php python3 readlink sha256sum tar uname; do
    command -v "$command_name" >/dev/null 2>&1 \
        || fail "Required provisioning command is unavailable: ${command_name}"
done
python3 -m venv --help >/dev/null 2>&1 \
    || fail 'python3-venv is required for the pinned Valhalla wheel.'
python3 -c 'import sys; raise SystemExit(0 if sys.version_info >= (3, 12) else 1)' \
    || fail 'Python 3.12 or newer is required for the pinned Valhalla wheel.'

planetiler_version='0.10.2'
planetiler_sha256='f310bd0413e2e4512b27f4046d418664e8e1d3bf31603c2a70e23de06c167e4d'
pmtiles_version='1.31.2'
pmtiles_sha256='3ed7dbf4ec2e6dfe5e25b6f70d1ffc932729f93c86db353bf514dd71010a312f'
yc_version='1.22.0'
yc_sha256='54549af3441f691e362e27bc4197010658053d704ccd494e26534135bab34f33'
valhalla_version='3.6.3'
valhalla_wheel_sha256='e123720983ce570f340c3ef78289098bf647deba68d56143571384a5aa874868'
valhalla_commit='e2f017b16080f49203de245a211b09efab09cf72'
valhalla_extract_sha256='37daac2ff760552c79431b41653be6d457e7e30d051e400c29064208fce65ae4'

planetiler_root="/opt/planetiler/${planetiler_version}"
pmtiles_root="/opt/pmtiles/${pmtiles_version}"
yc_root="/opt/yandex-cloud-cli/${yc_version}"
valhalla_root="/opt/valhalla/${valhalla_version}"
work_root="$(mktemp -d /opt/.pischeprom-gis-toolchain.XXXXXXXX)"
created_valhalla=false
valhalla_ready=false
cleanup() {
    if $created_valhalla && ! $valhalla_ready && [[ -d "$valhalla_root" && ! -L "$valhalla_root" ]]; then
        rm -r -- "$valhalla_root"
    fi
    rm -r -- "$work_root"
}
trap cleanup EXIT

download_verified() {
    local url="$1"
    local output="$2"
    local expected_sha="$3"

    curl \
        --fail \
        --silent \
        --show-error \
        --location \
        --retry 4 \
        --retry-all-errors \
        --output "$output" \
        "$url"
    [[ "$(sha256sum -- "$output" | awk '{print $1}')" == "$expected_sha" ]] \
        || fail "Downloaded tool failed its pinned SHA-256: $(basename "$output")"
}

install_versioned_file() {
    local source="$1"
    local destination="$2"
    local expected_sha="$3"
    local mode="$4"

    if [[ -e "$destination" ]]; then
        [[ -f "$destination" && ! -L "$destination" \
            && "$(sha256sum -- "$destination" | awk '{print $1}')" == "$expected_sha" ]] \
            || fail "Existing pinned tool differs: ${destination}"
        return
    fi
    install -m "$mode" -- "$source" "$destination"
}

link_exact() {
    local target="$1"
    local link="$2"
    if [[ -L "$link" ]]; then
        [[ "$(readlink -f -- "$link")" == "$(readlink -f -- "$target")" ]] \
            || fail "Existing toolchain symlink points elsewhere: ${link}"
        return
    fi
    [[ ! -e "$link" ]] || fail "Refusing to replace an existing non-symlink tool: ${link}"
    ln -s -- "$target" "$link"
}

install -d -m 0755 -- \
    "$planetiler_root" \
    "$pmtiles_root" \
    "$yc_root" \
    /opt/valhalla \
    /usr/local/bin

planetiler_download="${work_root}/planetiler.jar"
download_verified \
    "https://github.com/onthegomap/planetiler/releases/download/v${planetiler_version}/planetiler.jar" \
    "$planetiler_download" \
    "$planetiler_sha256"
install_versioned_file \
    "$planetiler_download" \
    "${planetiler_root}/planetiler.jar" \
    "$planetiler_sha256" \
    0444
link_exact "${planetiler_root}/planetiler.jar" /opt/planetiler/planetiler.jar

pmtiles_download="${work_root}/pmtiles.tar.gz"
download_verified \
    "https://github.com/protomaps/go-pmtiles/releases/download/v${pmtiles_version}/go-pmtiles_${pmtiles_version}_Linux_x86_64.tar.gz" \
    "$pmtiles_download" \
    "$pmtiles_sha256"
pmtiles_extract="${work_root}/pmtiles-extract"
mkdir -- "$pmtiles_extract"
tar -xzf "$pmtiles_download" -C "$pmtiles_extract"
[[ -f "${pmtiles_extract}/pmtiles" && ! -L "${pmtiles_extract}/pmtiles" ]] \
    || fail 'Pinned PMTiles archive does not contain the expected binary.'
pmtiles_binary_sha="$(sha256sum -- "${pmtiles_extract}/pmtiles" | awk '{print $1}')"
install_versioned_file \
    "${pmtiles_extract}/pmtiles" \
    "${pmtiles_root}/pmtiles" \
    "$pmtiles_binary_sha" \
    0555
link_exact "${pmtiles_root}/pmtiles" /usr/local/bin/pmtiles

yc_download="${work_root}/yc"
download_verified \
    "https://storage.yandexcloud.net/yandexcloud-yc/release/${yc_version}/linux/amd64/yc" \
    "$yc_download" \
    "$yc_sha256"
install_versioned_file \
    "$yc_download" \
    "${yc_root}/yc" \
    "$yc_sha256" \
    0555
link_exact "${yc_root}/yc" /usr/local/bin/yc

valhalla_wheel="${work_root}/pyvalhalla-3.6.3-cp312-abi3-manylinux_2_28_x86_64.whl"
download_verified \
    'https://files.pythonhosted.org/packages/d3/cc/78891758cd34dfdc9c8924a92cb1a9f48d594acc03d2b62d68d9933abaaf/pyvalhalla-3.6.3-cp312-abi3-manylinux_2_28_x86_64.whl' \
    "$valhalla_wheel" \
    "$valhalla_wheel_sha256"
if [[ ! -d "$valhalla_root" ]]; then
    created_valhalla=true
    python3 -m venv "$valhalla_root"
    "$valhalla_root/bin/python3" -m pip install \
        --disable-pip-version-check \
        --no-deps \
        --no-index \
        "$valhalla_wheel" >/dev/null
fi
[[ -x "${valhalla_root}/bin/python3" ]] || fail 'Pinned Valhalla virtual environment is incomplete.'
installed_valhalla_version="$("${valhalla_root}/bin/python3" -m valhalla --version)"
[[ "$installed_valhalla_version" == "$valhalla_version" ]] \
    || fail 'Installed pyvalhalla version does not match the pinned release.'
valhalla_bin_dir="$("${valhalla_root}/bin/python3" -m valhalla print_bin_path)"
[[ "$valhalla_bin_dir" == "${valhalla_root}/"* && -d "$valhalla_bin_dir" ]] \
    || fail 'pyvalhalla returned an unsafe executable directory.'

extract_download="${work_root}/valhalla_build_extract"
download_verified \
    "https://raw.githubusercontent.com/valhalla/valhalla/${valhalla_commit}/scripts/valhalla_build_extract" \
    "$extract_download" \
    "$valhalla_extract_sha256"
install_versioned_file \
    "$extract_download" \
    "${valhalla_root}/bin/valhalla_build_extract" \
    "$valhalla_extract_sha256" \
    0555

build_config_module="$(
    "${valhalla_root}/bin/python3" -c '
import pathlib
import valhalla
print(pathlib.Path(valhalla.__file__).resolve().with_name("valhalla_build_config.py"))
'
)"
[[ "$build_config_module" == "${valhalla_root}/"* \
    && -f "$build_config_module" \
    && ! -L "$build_config_module" ]] \
    || fail 'Pinned pyvalhalla package is missing its configuration builder.'
build_config_wrapper="${work_root}/valhalla_build_config"
printf '%s\n' \
    '#!/usr/bin/env bash' \
    "exec '${valhalla_root}/bin/python3' '${build_config_module}' \"\$@\"" \
    > "$build_config_wrapper"
build_config_sha="$(sha256sum -- "$build_config_wrapper" | awk '{print $1}')"
install_versioned_file \
    "$build_config_wrapper" \
    "${valhalla_root}/bin/valhalla_build_config" \
    "$build_config_sha" \
    0555

for command_name in valhalla_build_admins valhalla_build_tiles valhalla_service; do
    [[ -x "${valhalla_bin_dir}/${command_name}" ]] \
        || fail "Pinned pyvalhalla wheel is missing ${command_name}."
    link_exact "${valhalla_bin_dir}/${command_name}" "/usr/local/bin/${command_name}"
done
link_exact "${valhalla_root}/bin/valhalla_build_config" /usr/local/bin/valhalla_build_config
link_exact "${valhalla_root}/bin/valhalla_build_extract" /usr/local/bin/valhalla_build_extract

[[ "$(/usr/local/bin/pmtiles version 2>/dev/null | head -n1)" == *"${pmtiles_version}"* ]] \
    || fail 'Installed PMTiles CLI version check failed.'
[[ "$(/usr/local/bin/yc version 2>&1 | head -n1)" == *"${yc_version}"* ]] \
    || fail 'Installed Yandex Cloud CLI version check failed.'
[[ "$(/usr/local/bin/valhalla_service --version 2>&1 | head -n1)" == *"${valhalla_version}"* ]] \
    || fail 'Installed Valhalla version check failed.'

valhalla_ready=true
trap - EXIT
rm -r -- "$work_root"
printf '[logistics-gis] Installed pinned GIS toolchain: Planetiler %s, PMTiles %s, Valhalla %s, YC CLI %s.\n' \
    "$planetiler_version" "$pmtiles_version" "$valhalla_version" "$yc_version"
