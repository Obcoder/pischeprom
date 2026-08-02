#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'

repository="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
manager="${repository}/infrastructure/logistics-gis/scripts/manage-yandex-builder.sh"
work_dir="$(mktemp -d)"
trap 'rm -rf -- "$work_dir"' EXIT

fake_bin="${work_dir}/bin"
mkdir -- "$fake_bin"

cat > "${fake_bin}/yc" <<'FAKE_YC'
#!/usr/bin/env bash
set -Eeuo pipefail

folder='b1g00000000000000000'
network='enp00000000000000000'
subnet='e2l00000000000000000'
image='fd800000000000000000'
vm='fhm00000000000000000'
disk='fhm11111111111111111'
security_group='enp11111111111111111'
scenario="${TEST_SCENARIO:-absent}"

case "$1 $2 $3" in
    'vpc network get')
        printf '{"id":"%s","folder_id":"%s"}\n' "$network" "$folder"
        ;;
    'vpc subnet get')
        printf '{"id":"%s","folder_id":"%s","network_id":"%s","zone_id":"ru-central1-b"}\n' \
            "$subnet" "$folder" "$network"
        ;;
    'compute image get')
        printf '{"id":"%s","family":"ubuntu-2404-lts","status":"READY"}\n' "$image"
        ;;
    'compute instance list')
        if [[ "$scenario" == 'absent' ]]; then
            printf '[]\n'
        else
            printf '[{"id":"%s","name":"pischeprom-gis-builder"}]\n' "$vm"
        fi
        ;;
    'compute disk list')
        if [[ "$scenario" == 'absent' || "$scenario" == 'missing-disk' ]]; then
            printf '[]\n'
        else
            printf '[{"id":"%s","name":"pischeprom-gis-builder-boot"}]\n' "$disk"
        fi
        ;;
    'vpc security-group list')
        if [[ "$scenario" == 'absent' ]]; then
            printf '[]\n'
        else
            printf '[{"id":"%s","name":"pischeprom-gis-builder-sg"}]\n' "$security_group"
        fi
        ;;
    'compute instance get')
        printf '{"id":"%s","folder_id":"%s","name":"pischeprom-gis-builder","labels":{"managed-by":"pischeprom-gis","lifecycle":"ephemeral"},"zone_id":"ru-central1-b","platform_id":"standard-v3","resources":{"memory":"17179869184","cores":"8","core_fraction":"100"},"status":"RUNNING","scheduling_policy":{"preemptible":false},"boot_disk":{"auto_delete":true,"disk_id":"%s"},"network_interfaces":[{"subnet_id":"%s","security_group_ids":["%s"],"primary_v4_address":{"address":"10.129.0.10","one_to_one_nat":{"address":"84.1.2.3","ip_version":"IPV4"}}}]}\n' \
            "$vm" "$folder" "$disk" "$subnet" "$security_group"
        ;;
    'compute disk get')
        printf '{"id":"%s","folder_id":"%s","name":"pischeprom-gis-builder-boot","zone_id":"ru-central1-b","type_id":"network-ssd","size":"171798691840"}\n' \
            "$disk" "$folder"
        ;;
    'vpc security-group get')
        if [[ "$scenario" == 'unexpected-rule' ]]; then
            extra_rule=',{"description":"unexpected-public-api","direction":"INGRESS","protocol_name":"TCP","ports":{"from_port":"8002","to_port":"8002"},"cidr_blocks":{"v4_cidr_blocks":["0.0.0.0/0"]}}'
        else
            extra_rule=''
        fi
        printf '{"id":"%s","folder_id":"%s","name":"pischeprom-gis-builder-sg","network_id":"%s","labels":{"managed-by":"pischeprom-gis","lifecycle":"ephemeral"},"rules":[{"description":"builder-egress","direction":"EGRESS","protocol_name":"ANY","ports":{"from_port":"0","to_port":"65535"},"cidr_blocks":{"v4_cidr_blocks":["0.0.0.0/0"]}}%s]}\n' \
            "$security_group" "$folder" "$network" "$extra_rule"
        ;;
    *)
        printf 'Unexpected fake yc invocation: %s\n' "$*" >&2
        exit 64
        ;;
esac
FAKE_YC
chmod 0755 "${fake_bin}/yc"

common_env=(
    "PATH=${fake_bin}:${PATH}"
    'YC_FOLDER_ID=b1g00000000000000000'
    'YC_GIS_IMAGE_ID=fd800000000000000000'
    'YC_GIS_NETWORK_ID=enp00000000000000000'
    'YC_GIS_SUBNET_ID=e2l00000000000000000'
    'YC_GIS_ZONE=ru-central1-b'
    'YC_GIS_COMPUTE_ACTION=plan'
)

TEST_SCENARIO=absent env "${common_env[@]}" "$manager" > "${work_dir}/absent.log"
grep -F 'Plan completed without mutating cloud state.' "${work_dir}/absent.log" >/dev/null

TEST_SCENARIO=complete env "${common_env[@]}" "$manager" > "${work_dir}/complete.log"
grep -F 'status=RUNNING' "${work_dir}/complete.log" >/dev/null

if TEST_SCENARIO=unexpected-rule env "${common_env[@]}" "$manager" > /dev/null 2>&1; then
    printf 'Builder manager accepted an unexpected public firewall rule.\n' >&2
    exit 1
fi

if TEST_SCENARIO=missing-disk env "${common_env[@]}" "$manager" > /dev/null 2>&1; then
    printf 'Builder manager accepted a managed VM without its exact boot disk.\n' >&2
    exit 1
fi

printf 'Yandex GIS builder manager tests passed.\n'
