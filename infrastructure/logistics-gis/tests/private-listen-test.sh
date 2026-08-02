#!/usr/bin/env bash

set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/../scripts" && pwd)/common.sh"

VALHALLA_SERVICE_LISTEN='tcp://127.0.0.1:8002'
[[ "$(gis_validated_valhalla_listen)" == "$VALHALLA_SERVICE_LISTEN" ]]

VALHALLA_ALLOW_PRIVATE_NETWORK_LISTEN=true
for private_ip in 10.66.0.2 172.16.1.2 192.168.10.2 100.64.10.2; do
    VALHALLA_SERVICE_LISTEN="tcp://${private_ip}:8002"
    [[ "$(gis_validated_valhalla_listen)" == "$VALHALLA_SERVICE_LISTEN" ]]
done

VALHALLA_SERVICE_LISTEN='tcp://8.8.8.8:8002'
if (gis_validated_valhalla_listen >/dev/null 2>&1); then
    printf 'Public Valhalla bind was accepted.\n' >&2
    exit 1
fi

VALHALLA_ALLOW_PRIVATE_NETWORK_LISTEN=false
VALHALLA_ALLOW_WILDCARD_LISTEN=true
VALHALLA_SERVICE_LISTEN='tcp://0.0.0.0:8002'
[[ "$(gis_validated_valhalla_listen)" == "$VALHALLA_SERVICE_LISTEN" ]]

printf 'private-listen-test: PASS\n'
