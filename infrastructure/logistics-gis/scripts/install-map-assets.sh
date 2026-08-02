#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'
umask 022

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
validator="${script_dir}/validate-map-assets.php"
destination="${1:-/opt/pischeprom-map-assets}"

fail() {
    printf '[logistics-gis] ERROR: %s\n' "$*" >&2
    exit 1
}

for command_name in base64 curl mkdir php; do
    command -v "$command_name" >/dev/null 2>&1 \
        || fail "Required command is unavailable: ${command_name}"
done

[[ "$destination" == /* && "$destination" != '/' ]] \
    || fail 'Map assets destination must be an absolute non-root path.'
[[ ! -L "$destination" ]] || fail 'Map assets destination must not be a symlink.'

maplibre_commit='ef4389e954d46e97cd9d3b0130881d9fb789ae2e'
openmaptiles_fonts_commit='0bcd6431ec82fbb74b3a5b697ce315ebf795ad8e'
empty_sprite_png='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='

verify_checksum() {
    local path="$1"
    local expected="$2"
    [[ -f "$path" && ! -L "$path" ]] || return 1
    [[ "$(php -r 'echo hash_file("sha256", $argv[1]);' "$path")" == "$expected" ]]
}

verify_complete() {
    local root="$1"
    verify_checksum "$root/fonts/Noto Sans Regular/0-255.pbf" \
        'ef1f38a3f1978591e846e9eaddf8a54f7047f546fc6aaed7872cc53151a5de78' \
        || return 1
    verify_checksum "$root/fonts/Noto Sans Regular/1024-1279.pbf" \
        '834adb7f3ea9944ea530214841ee098e5482e85180f14df85036939261e0ffbb' \
        || return 1
    verify_checksum "$root/fonts/Noto Sans Regular/1280-1535.pbf" \
        '0f4f3f76dd76836efbcdf3c35219449aa46defb1d3e8e0f9b51153a539e64454' \
        || return 1
    verify_checksum "$root/fonts/Noto Sans Regular/8192-8447.pbf" \
        'f061e5b4e42e5925228534d8822b423a029569263db09f5ce44573600d45295f' \
        || return 1
    verify_checksum "$root/licenses/MapLibre-Demo-Tiles.BSD-3-Clause.txt" \
        '5dd1cfc5b6f7bef1363ba3f79d7c11c04b12ea1782d8b8d0f3c4366b60640eb2' \
        || return 1
    verify_checksum "$root/licenses/Noto-Sans.OFL-1.1.txt" \
        '6a73f9541c2de74158c0e7cf6b0a58ef774f5a780bf191f2d7ec9cc53efe2bf2' \
        || return 1
    for sprite_json in basic.json basic@2x.json; do
        verify_checksum "$root/sprites/$sprite_json" \
            'ca3d163bab055381827226140568f3bef7eaac187cebd76878e0b63e9e442356' \
            || return 1
    done
    for sprite_png in basic.png basic@2x.png; do
        verify_checksum "$root/sprites/$sprite_png" \
            '431ced6916a2a21a156e38701afe55bbd7f88969fbbfc56d7fe099d47f265460' \
            || return 1
    done
    php "$validator" "$root" >/dev/null
}

if [[ -e "$destination" ]]; then
    [[ -d "$destination" ]] || fail 'Existing map assets destination is not a directory.'
    verify_complete "$destination" \
        || fail 'Existing map assets differ from the pinned immutable asset set; refusing to overwrite them.'
    chmod -R a-w "$destination"
    printf '[logistics-gis] Pinned map assets are already verified: %s\n' "$destination"
    exit 0
fi

parent="$(dirname "$destination")"
mkdir -p -- "$parent"
work_root="$(mktemp -d "${parent}/.pischeprom-map-assets.XXXXXXXX")"
stage="${work_root}/assets"
cleanup() {
    chmod -R u+w "$work_root" 2>/dev/null || true
    rm -r -- "$work_root"
}
trap cleanup EXIT

mkdir -p -- \
    "$stage/fonts/Noto Sans Regular" \
    "$stage/licenses" \
    "$stage/sprites"

download_verified() {
    local relative="$1"
    local url="$2"
    local checksum="$3"
    local output="${stage}/${relative}"

    curl \
        --fail \
        --silent \
        --show-error \
        --location \
        --retry 4 \
        --retry-all-errors \
        --output "${output}.part" \
        "$url"
    verify_checksum "${output}.part" "$checksum" \
        || fail "Downloaded map asset failed its pinned SHA-256: ${relative}"
    mv -- "${output}.part" "$output"
}

maplibre_raw="https://raw.githubusercontent.com/maplibre/demotiles/${maplibre_commit}"
for range in 0-255 1024-1279 1280-1535 8192-8447; do
    case "$range" in
        0-255) checksum='ef1f38a3f1978591e846e9eaddf8a54f7047f546fc6aaed7872cc53151a5de78' ;;
        1024-1279) checksum='834adb7f3ea9944ea530214841ee098e5482e85180f14df85036939261e0ffbb' ;;
        1280-1535) checksum='0f4f3f76dd76836efbcdf3c35219449aa46defb1d3e8e0f9b51153a539e64454' ;;
        8192-8447) checksum='f061e5b4e42e5925228534d8822b423a029569263db09f5ce44573600d45295f' ;;
        *) fail "Unsupported pinned glyph range: ${range}" ;;
    esac
    download_verified \
        "fonts/Noto Sans Regular/${range}.pbf" \
        "${maplibre_raw}/font/Noto%20Sans%20Regular/${range}.pbf" \
        "$checksum"
done

download_verified \
    'licenses/MapLibre-Demo-Tiles.BSD-3-Clause.txt' \
    "${maplibre_raw}/LICENSE" \
    '5dd1cfc5b6f7bef1363ba3f79d7c11c04b12ea1782d8b8d0f3c4366b60640eb2'
download_verified \
    'licenses/Noto-Sans.OFL-1.1.txt' \
    "https://raw.githubusercontent.com/openmaptiles/fonts/${openmaptiles_fonts_commit}/noto-sans/LICENSE" \
    '6a73f9541c2de74158c0e7cf6b0a58ef774f5a780bf191f2d7ec9cc53efe2bf2'

printf '{}\n' > "$stage/sprites/basic.json"
printf '{}\n' > "$stage/sprites/basic@2x.json"
printf '%s' "$empty_sprite_png" | base64 --decode > "$stage/sprites/basic.png"
printf '%s' "$empty_sprite_png" | base64 --decode > "$stage/sprites/basic@2x.png"

(
    cd "$stage"
    for asset in \
        'fonts/Noto Sans Regular/0-255.pbf' \
        'fonts/Noto Sans Regular/1024-1279.pbf' \
        'fonts/Noto Sans Regular/1280-1535.pbf' \
        'fonts/Noto Sans Regular/8192-8447.pbf' \
        'licenses/MapLibre-Demo-Tiles.BSD-3-Clause.txt' \
        'licenses/Noto-Sans.OFL-1.1.txt' \
        'sprites/basic.json' \
        'sprites/basic.png' \
        'sprites/basic@2x.json' \
        'sprites/basic@2x.png'
    do
        php -r 'printf("%s  %s\n", hash_file("sha256", $argv[1]), $argv[1]);' "$asset"
    done
) > "$stage/SHA256SUMS"

verify_complete "$stage" || fail 'Generated map assets failed final validation.'
mv -- "$stage" "$destination"
trap - EXIT
rm -r -- "$work_root"
chmod -R a-w "$destination"

printf '[logistics-gis] Installed checksum-verified immutable map assets: %s\n' "$destination"
printf '[logistics-gis] MapLibre demo commit: %s; OpenMapTiles fonts commit: %s\n' \
    "$maplibre_commit" "$openmaptiles_fonts_commit"
