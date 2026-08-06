#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'
umask 027

fail() {
    printf '[mail-backfill] ERROR: %s\n' "$*" >&2
    exit 1
}

[[ $# -eq 8 ]] || fail 'Expected target, SHA, action, limit, cursor, mailbox, since and until.'

target_dir_encoded="$1"
expected_sha="$2"
action="$3"
limit="$4"
cursor_encoded="$5"
mailbox_encoded="$6"
since_encoded="$7"
until_encoded="$8"

for required_command in base64 git id php realpath stat sudo systemctl; do
    command -v "$required_command" >/dev/null \
        || fail "Required server command is unavailable: ${required_command}."
done

decode_value() {
    printf '%s' "$1" | base64 --decode
}

target_dir="$(decode_value "$target_dir_encoded")" \
    || fail 'TARGET_DIR could not be decoded.'
cursor="$(decode_value "$cursor_encoded")" \
    || fail 'Cursor could not be decoded.'
mailbox="$(decode_value "$mailbox_encoded")" \
    || fail 'Mailbox could not be decoded.'
since="$(decode_value "$since_encoded")" \
    || fail 'Since value could not be decoded.'
until="$(decode_value "$until_encoded")" \
    || fail 'Until value could not be decoded.'

[[ "$expected_sha" =~ ^[0-9a-f]{40}$ ]] || fail 'Commit SHA is invalid.'
[[ "$action" == 'plan' || "$action" == 'apply' ]] || fail 'Action is invalid.'
[[ "$limit" =~ ^[0-9]+$ ]] && (( limit >= 1 && limit <= 100 )) \
    || fail 'Limit must be from 1 to 100.'
[[ -z "$cursor" || "$cursor" =~ ^[1-9][0-9]*$ ]] \
    || fail 'Cursor must be empty or a positive integer.'
[[ "$target_dir" == /* && "$target_dir" != '/' ]] \
    || fail 'TARGET_DIR must be an absolute non-root path.'
[[ -d "$target_dir" && -f "$target_dir/artisan" && -d "$target_dir/.git" ]] \
    || fail 'Laravel production directory is unavailable.'

target_dir="$(realpath -- "$target_dir")"
[[ "$target_dir" != '/' ]] || fail 'Resolved TARGET_DIR must not be root.'
application_owner="$(stat -c '%U' "$target_dir")"
[[ -n "$application_owner" && "$application_owner" != 'UNKNOWN' && "$application_owner" != 'root' ]] \
    || fail 'Production directory owner is unsafe.'
id "$application_owner" >/dev/null || fail 'Production application owner does not exist.'

cd "$target_dir"
deployed_sha="$(git rev-parse HEAD)"
[[ "$deployed_sha" == "$expected_sha" ]] \
    || fail "Production is on ${deployed_sha}, expected ${expected_sha}. Deploy this commit first."

sudo systemctl is-active --quiet pischeprom-mail-sync-worker \
    || fail 'The mail-sync worker is not active.'
sudo systemctl is-active --quiet pischeprom-price-lists-worker \
    || fail 'The price-list worker is not active.'

sudo -u "$application_owner" -- php artisan price-lists:production-preflight --schema --redis

artisan_arguments=(
    artisan
    price-lists:mail-backfill
    "--limit=${limit}"
)

[[ -z "$cursor" ]] || artisan_arguments+=("--cursor=${cursor}")
[[ -z "$mailbox" ]] || artisan_arguments+=("--mailbox=${mailbox}")
[[ -z "$since" ]] || artisan_arguments+=("--since=${since}")
[[ -z "$until" ]] || artisan_arguments+=("--until=${until}")
[[ "$action" == 'plan' ]] || artisan_arguments+=(--apply)

printf '[mail-backfill] Running %s for at most %s messages.\n' "$action" "$limit"
sudo -u "$application_owner" -- php "${artisan_arguments[@]}"
