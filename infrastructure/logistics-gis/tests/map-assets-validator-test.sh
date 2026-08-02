#!/usr/bin/env bash

set -Eeuo pipefail
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
validator="${script_dir}/../scripts/validate-map-assets.php"
fixture_dir="$(mktemp -d "${TMPDIR:-/tmp}/pischeprom-map-assets.XXXXXXXX")"
cleanup() { rm -r -- "$fixture_dir"; }
trap cleanup EXIT

mkdir -p -- "$fixture_dir/fonts/Noto Sans Regular" "$fixture_dir/licenses" "$fixture_dir/sprites"
printf 'glyph-fixture\n' > "$fixture_dir/fonts/Noto Sans Regular/0-255.pbf"
printf 'license-fixture\n' > "$fixture_dir/licenses/font.txt"
printf '{}\n' > "$fixture_dir/sprites/basic.json"
glyph_sha="$(php -r 'echo hash_file("sha256",$argv[1]);' "$fixture_dir/fonts/Noto Sans Regular/0-255.pbf")"
license_sha="$(php -r 'echo hash_file("sha256",$argv[1]);' "$fixture_dir/licenses/font.txt")"
sprite_sha="$(php -r 'echo hash_file("sha256",$argv[1]);' "$fixture_dir/sprites/basic.json")"
printf '%s  %s\n%s  %s\n%s  %s\n' \
    "$glyph_sha" 'fonts/Noto Sans Regular/0-255.pbf' \
    "$license_sha" 'licenses/font.txt' \
    "$sprite_sha" 'sprites/basic.json' \
    > "$fixture_dir/SHA256SUMS"

php "$validator" "$fixture_dir" >/dev/null
printf 'unlisted\n' > "$fixture_dir/sprites/unlisted.txt"
if php "$validator" "$fixture_dir" >/dev/null 2>&1; then
    printf 'validator accepted an unlisted public map asset\n' >&2
    exit 1
fi

printf 'map assets validator: complete manifest fixture passed\n'
