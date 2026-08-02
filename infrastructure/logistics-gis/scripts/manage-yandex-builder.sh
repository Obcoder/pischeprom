#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'
umask 077

fail() {
    printf '[logistics-gis] ERROR: %s\n' "$*" >&2
    exit 1
}

log() {
    printf '[logistics-gis] %s\n' "$*"
}

for command_name in head php tr wc yc; do
    command -v "$command_name" >/dev/null 2>&1 \
        || fail "Required Yandex builder command is unavailable: ${command_name}"
done

action="${YC_GIS_COMPUTE_ACTION:-plan}"
[[ "$action" =~ ^(plan|apply|close-ssh|destroy)$ ]] \
    || fail 'YC_GIS_COMPUTE_ACTION must be plan, apply, close-ssh or destroy.'

required_variables=(
    YC_FOLDER_ID
    YC_GIS_IMAGE_ID
    YC_GIS_NETWORK_ID
    YC_GIS_SUBNET_ID
    YC_GIS_ZONE
)
for variable_name in "${required_variables[@]}"; do
    [[ -n "${!variable_name:-}" ]] \
        || fail "Required builder variable is missing: ${variable_name}"
done

for resource_id in \
    "$YC_FOLDER_ID" \
    "$YC_GIS_IMAGE_ID" \
    "$YC_GIS_NETWORK_ID" \
    "$YC_GIS_SUBNET_ID"
do
    [[ "$resource_id" =~ ^[a-z0-9]{20}$ ]] \
        || fail "Yandex resource ID has an unexpected format: ${resource_id}"
done
[[ "$YC_GIS_ZONE" =~ ^ru-central1-[a-z]$ ]] \
    || fail 'YC_GIS_ZONE has an unexpected format.'

vm_name='pischeprom-gis-builder'
disk_name='pischeprom-gis-builder-boot'
security_group_name='pischeprom-gis-builder-sg'
platform_id='standard-v3'
cores=8
memory_gib=16
memory_bytes=17179869184
core_fraction=100
disk_gib=160
disk_bytes=171798691840
disk_type='network-ssd'
managed_label='pischeprom-gis'

yc_common=(--folder-id "$YC_FOLDER_ID" --format json --no-user-output)

network_json="$(yc vpc network get "$YC_GIS_NETWORK_ID" --format json --no-user-output)" \
    || fail 'Unable to inspect the configured Yandex network.'
subnet_json="$(yc vpc subnet get "$YC_GIS_SUBNET_ID" --format json --no-user-output)" \
    || fail 'Unable to inspect the configured Yandex subnet.'
image_json="$(yc compute image get "$YC_GIS_IMAGE_ID" --format json --no-user-output)" \
    || fail 'Unable to inspect the configured Ubuntu image.'

NETWORK="$network_json" SUBNET="$subnet_json" IMAGE="$image_json" \
EXPECTED_FOLDER="$YC_FOLDER_ID" EXPECTED_NETWORK="$YC_GIS_NETWORK_ID" \
EXPECTED_SUBNET="$YC_GIS_SUBNET_ID" EXPECTED_ZONE="$YC_GIS_ZONE" \
EXPECTED_IMAGE="$YC_GIS_IMAGE_ID" php -r '
    $network=json_decode((string)getenv("NETWORK"),true,flags:JSON_THROW_ON_ERROR);
    $subnet=json_decode((string)getenv("SUBNET"),true,flags:JSON_THROW_ON_ERROR);
    $image=json_decode((string)getenv("IMAGE"),true,flags:JSON_THROW_ON_ERROR);
    if(!is_array($network)||!is_array($subnet)||!is_array($image))exit(1);
    $folder=getenv("EXPECTED_FOLDER");
    $valid=($network["id"]??null)===getenv("EXPECTED_NETWORK")
        &&($network["folder_id"]??$network["folderId"]??null)===$folder
        &&($subnet["id"]??null)===getenv("EXPECTED_SUBNET")
        &&($subnet["folder_id"]??$subnet["folderId"]??null)===$folder
        &&($subnet["network_id"]??$subnet["networkId"]??null)===getenv("EXPECTED_NETWORK")
        &&($subnet["zone_id"]??$subnet["zoneId"]??null)===getenv("EXPECTED_ZONE")
        &&($image["id"]??null)===getenv("EXPECTED_IMAGE")
        &&($image["family"]??null)==="ubuntu-2404-lts"
        &&($image["status"]??null)==="READY";
    exit($valid?0:1);
' || fail 'Configured network, subnet, zone and Ubuntu image do not match.'

instances_json="$(yc compute instance list "${yc_common[@]}")" \
    || fail 'Unable to list Yandex compute instances.'
disks_json="$(yc compute disk list "${yc_common[@]}")" \
    || fail 'Unable to list Yandex compute disks.'
security_groups_json="$(yc vpc security-group list "${yc_common[@]}")" \
    || fail 'Unable to list Yandex security groups.'

select_named() {
    local json="$1"
    local name="$2"
    JSON="$json" EXPECTED_NAME="$name" php -r '
        $items=json_decode((string)getenv("JSON"),true,flags:JSON_THROW_ON_ERROR);
        if(!is_array($items))exit(1);
        $matches=array_values(array_filter($items,static fn($item): bool =>
            is_array($item)&&($item["name"]??null)===getenv("EXPECTED_NAME")
        ));
        if(count($matches)>1)exit(2);
        echo json_encode($matches[0]??null,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    '
}

vm_json="$(select_named "$instances_json" "$vm_name")" \
    || fail 'Duplicate or malformed managed VM inventory.'
disk_json="$(select_named "$disks_json" "$disk_name")" \
    || fail 'Duplicate or malformed managed disk inventory.'
security_group_json="$(select_named "$security_groups_json" "$security_group_name")" \
    || fail 'Duplicate or malformed managed security-group inventory.'

is_null_json() {
    [[ "$1" == 'null' ]]
}

json_id() {
    JSON="$1" php -r '
        $value=json_decode((string)getenv("JSON"),true,flags:JSON_THROW_ON_ERROR);
        echo is_array($value)?($value["id"]??""):"";
    '
}

# List responses are sufficient for collision detection, but the validators
# deliberately use full resources so attached disks, interfaces and rules can
# never be accepted from an abbreviated inventory record.
if ! is_null_json "$vm_json"; then
    vm_id="$(json_id "$vm_json")"
    [[ "$vm_id" =~ ^[a-z0-9]{20}$ ]] || fail 'Managed VM ID is invalid.'
    vm_json="$(yc compute instance get "$vm_id" --format json --no-user-output)" \
        || fail 'Unable to inspect the complete managed VM.'
fi
if ! is_null_json "$disk_json"; then
    disk_id="$(json_id "$disk_json")"
    [[ "$disk_id" =~ ^[a-z0-9]{20}$ ]] || fail 'Managed disk ID is invalid.'
    disk_json="$(yc compute disk get "$disk_id" --format json --no-user-output)" \
        || fail 'Unable to inspect the complete managed boot disk.'
fi
if ! is_null_json "$security_group_json"; then
    security_group_id="$(json_id "$security_group_json")"
    [[ "$security_group_id" =~ ^[a-z0-9]{20}$ ]] || fail 'Managed security-group ID is invalid.'
    security_group_json="$(yc vpc security-group get "$security_group_id" --format json --no-user-output)" \
        || fail 'Unable to inspect the complete managed security group.'
fi

validate_managed_label() {
    local json="$1"
    local kind="$2"
    JSON="$json" EXPECTED_LABEL="$managed_label" php -r '
        $value=json_decode((string)getenv("JSON"),true,flags:JSON_THROW_ON_ERROR);
        $labels=is_array($value["labels"]??null)?$value["labels"]:[];
        $valid=($labels["managed-by"]??null)===getenv("EXPECTED_LABEL")
            &&($labels["lifecycle"]??null)==="ephemeral";
        exit($valid?0:1);
    ' || fail "Existing ${kind} with the reserved name is not managed by this workflow."
}

if ! is_null_json "$security_group_json"; then
    validate_managed_label "$security_group_json" 'security group'
    SECURITY_GROUP="$security_group_json" EXPECTED_NETWORK="$YC_GIS_NETWORK_ID" php -r '
        $value=json_decode((string)getenv("SECURITY_GROUP"),true,flags:JSON_THROW_ON_ERROR);
        exit((($value["network_id"]??$value["networkId"]??null)===getenv("EXPECTED_NETWORK"))?0:1);
    ' || fail 'Existing managed security group belongs to another network.'
    SECURITY_GROUP="$security_group_json" php -r '
        $value=json_decode((string)getenv("SECURITY_GROUP"),true,flags:JSON_THROW_ON_ERROR);
        $rules=is_array($value["rules"]??null)?$value["rules"]:[];
        $egress=0;
        $ssh=0;
        $unexpected=false;
        foreach($rules as $rule){
            if(!is_array($rule)){
                $unexpected=true;
                break;
            }
            $direction=strtoupper((string)($rule["direction"]??""));
            $protocol=strtoupper((string)($rule["protocol_name"]??$rule["protocolName"]??""));
            $protocolNumber=(string)($rule["protocol_number"]??$rule["protocolNumber"]??"");
            $cidrs=$rule["cidr_blocks"]["v4_cidr_blocks"]
                ??$rule["cidrBlocks"]["v4CidrBlocks"]??[];
            sort($cidrs);
            $ports=is_array($rule["ports"]??null)?$rule["ports"]:[];
            $from=(string)($ports["from_port"]??$ports["fromPort"]??"");
            $to=(string)($ports["to_port"]??$ports["toPort"]??"");
            $description=(string)($rule["description"]??"");
            $anyProtocol=$protocol===""||$protocol==="ANY"||$protocolNumber==="0";
            $allPorts=($from===""&&$to==="")||($from==="0"&&$to==="65535");
            $tcpProtocol=$protocol==="TCP"||$protocolNumber==="6";
            if($direction==="EGRESS"&&$anyProtocol&&$allPorts
                &&$cidrs===["0.0.0.0/0"]
                &&$description==="builder-egress"){
                $egress++;
                continue;
            }
            if($direction==="INGRESS"&&$tcpProtocol&&$from==="22"&&$to==="22"
                &&count($cidrs)===1
                &&preg_match("/^(?:[0-9]{1,3}\\.){3}[0-9]{1,3}\\/32$/",$cidrs[0])===1
                &&$description==="ephemeral-github-ssh"){
                $ssh++;
                continue;
            }
            $unexpected=true;
            break;
        }
        $valid=!$unexpected&&$egress===1&&$ssh<=1&&count($rules)===$egress+$ssh;
        if(!$valid){
            $summary=[];
            foreach($rules as $rule){
                $ports=is_array($rule["ports"]??null)?$rule["ports"]:[];
                $cidrs=$rule["cidr_blocks"]["v4_cidr_blocks"]
                    ??$rule["cidrBlocks"]["v4CidrBlocks"]??[];
                $targets=[];
                foreach(is_array($cidrs)?$cidrs:[] as $cidr){
                    $targets[]=$cidr==="0.0.0.0/0"?"all-ipv4"
                        :(is_string($cidr)&&str_ends_with($cidr,"/32")?"single-ipv4":"other");
                }
                $summary[]=[
                    "description"=>(string)($rule["description"]??""),
                    "direction"=>(string)($rule["direction"]??""),
                    "protocol_name"=>(string)($rule["protocol_name"]??$rule["protocolName"]??""),
                    "protocol_number"=>(string)($rule["protocol_number"]??$rule["protocolNumber"]??""),
                    "from"=>(string)($ports["from_port"]??$ports["fromPort"]??""),
                    "to"=>(string)($ports["to_port"]??$ports["toPort"]??""),
                    "targets"=>$targets,
                ];
            }
            fwrite(STDERR,"[logistics-gis] Sanitized firewall mismatch: ".json_encode($summary,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
        }
        exit($valid?0:1);
    ' || fail 'Existing managed security group contains an unexpected firewall rule.'
fi

if ! is_null_json "$vm_json"; then
    validate_managed_label "$vm_json" 'VM'
    VM="$vm_json" EXPECTED_FOLDER="$YC_FOLDER_ID" EXPECTED_ZONE="$YC_GIS_ZONE" \
    EXPECTED_PLATFORM="$platform_id" EXPECTED_SUBNET="$YC_GIS_SUBNET_ID" \
    EXPECTED_MEMORY="$memory_bytes" EXPECTED_CORES="$cores" \
    EXPECTED_FRACTION="$core_fraction" php -r '
        $v=json_decode((string)getenv("VM"),true,flags:JSON_THROW_ON_ERROR);
        $resources=is_array($v["resources"]??null)?$v["resources"]:[];
        $interfaces=$v["network_interfaces"]??$v["networkInterfaces"]??[];
        $interface=is_array($interfaces)&&count($interfaces)===1?$interfaces[0]:[];
        $preemptible=(bool)($v["scheduling_policy"]["preemptible"]??$v["schedulingPolicy"]["preemptible"]??false);
        $serviceAccount=$v["service_account_id"]??$v["serviceAccountId"]??null;
        $valid=($v["folder_id"]??$v["folderId"]??null)===getenv("EXPECTED_FOLDER")
            &&($v["zone_id"]??$v["zoneId"]??null)===getenv("EXPECTED_ZONE")
            &&($v["platform_id"]??$v["platformId"]??null)===getenv("EXPECTED_PLATFORM")
            &&(int)($resources["memory"]??0)===(int)getenv("EXPECTED_MEMORY")
            &&(int)($resources["cores"]??0)===(int)getenv("EXPECTED_CORES")
            &&(int)($resources["core_fraction"]??$resources["coreFraction"]??0)===(int)getenv("EXPECTED_FRACTION")
            &&($interface["subnet_id"]??$interface["subnetId"]??null)===getenv("EXPECTED_SUBNET")
            &&!$preemptible
            &&($serviceAccount===null||$serviceAccount==="");
        exit($valid?0:1);
    ' || fail 'Existing managed VM does not match the approved regular 8 vCPU / 16 GiB specification.'
fi

if ! is_null_json "$disk_json"; then
    DISK="$disk_json" EXPECTED_FOLDER="$YC_FOLDER_ID" EXPECTED_ZONE="$YC_GIS_ZONE" \
    EXPECTED_TYPE="$disk_type" EXPECTED_SIZE="$disk_bytes" php -r '
        $v=json_decode((string)getenv("DISK"),true,flags:JSON_THROW_ON_ERROR);
        $valid=($v["folder_id"]??$v["folderId"]??null)===getenv("EXPECTED_FOLDER")
            &&($v["zone_id"]??$v["zoneId"]??null)===getenv("EXPECTED_ZONE")
            &&($v["type_id"]??$v["typeId"]??null)===getenv("EXPECTED_TYPE")
            &&(int)($v["size"]??0)===(int)getenv("EXPECTED_SIZE");
        exit($valid?0:1);
    ' || fail 'Existing managed-name disk does not match the approved 160 GiB network SSD.'
fi

if is_null_json "$vm_json" && ! is_null_json "$disk_json" && [[ "$action" != 'destroy' ]]; then
    fail 'A managed-name boot disk exists without the managed VM; inspect it before continuing.'
fi

if ! is_null_json "$vm_json"; then
    ! is_null_json "$disk_json" \
        || fail 'The managed VM exists without its exact managed-name boot disk.'
    ! is_null_json "$security_group_json" \
        || fail 'The managed VM exists without its dedicated managed security group.'
    disk_id="$(json_id "$disk_json")"
    security_group_id="$(json_id "$security_group_json")"
    VM="$vm_json" EXPECTED_DISK="$disk_id" EXPECTED_SECURITY_GROUP="$security_group_id" php -r '
        $v=json_decode((string)getenv("VM"),true,flags:JSON_THROW_ON_ERROR);
        $boot=is_array($v["boot_disk"]??$v["bootDisk"]??null)
            ?($v["boot_disk"]??$v["bootDisk"]):[];
        $interfaces=$v["network_interfaces"]??$v["networkInterfaces"]??[];
        $interface=is_array($interfaces)&&count($interfaces)===1?$interfaces[0]:[];
        $groups=$interface["security_group_ids"]??$interface["securityGroupIds"]??[];
        sort($groups);
        $primary=$interface["primary_v4_address"]??$interface["primaryV4Address"]??[];
        $nat=$primary["one_to_one_nat"]??$primary["oneToOneNat"]??[];
        $private=(string)($primary["address"]??"");
        $public=(string)($nat["address"]??"");
        $ipVersion=strtoupper((string)($nat["ip_version"]??$nat["ipVersion"]??""));
        $valid=($boot["disk_id"]??$boot["diskId"]??null)===getenv("EXPECTED_DISK")
            &&(bool)($boot["auto_delete"]??$boot["autoDelete"]??false)
            &&$groups===[getenv("EXPECTED_SECURITY_GROUP")]
            &&filter_var($private,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)!==false
            &&filter_var($public,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)!==false
            &&$ipVersion==="IPV4";
        exit($valid?0:1);
    ' || fail 'Managed VM disk, public IPv4 or security-group attachment differs from the approved topology.'
fi

vm_summary() {
    if is_null_json "$vm_json"; then
        printf 'absent'
        return
    fi
    VM="$vm_json" php -r '
        $v=json_decode((string)getenv("VM"),true,flags:JSON_THROW_ON_ERROR);
        printf("id=%s status=%s",$v["id"]??"unknown",$v["status"]??"unknown");
    '
}

log "Plan: VM ${vm_name} ($(vm_summary)); regular ${platform_id}, ${cores} vCPU @ ${core_fraction}%, ${memory_gib} GiB RAM."
log "Plan: auto-delete boot disk ${disk_name}, ${disk_gib} GiB ${disk_type}, Ubuntu image ${YC_GIS_IMAGE_ID}."
log "Plan: existing network/subnet only, ${YC_GIS_ZONE}/${YC_GIS_SUBNET_ID}; dynamic public IPv4; no service account."
log "Plan: dedicated security group ${security_group_name}; outbound IPv4 allowed, SSH is opened only to the active runner /32 and closed after provisioning."
log 'No custom DNS, CDN, snapshot, reserved public address or preemptible scheduling is requested.'

if [[ "$action" == 'plan' ]]; then
    log 'Plan completed without mutating cloud state.'
    exit 0
fi

update_security_group_rules() {
    local security_group_id="$1"
    local ssh_cidr="${2:-}"
    local command=(
        yc vpc security-group update "$security_group_id"
        --rule 'description=builder-egress,direction=egress,protocol=any,from-port=0,to-port=65535,v4-cidrs=[0.0.0.0/0]'
        --format json
        --no-user-output
    )
    if [[ -n "$ssh_cidr" ]]; then
        command+=(--rule "description=ephemeral-github-ssh,direction=ingress,protocol=tcp,port=22,v4-cidrs=[${ssh_cidr}]")
    fi
    "${command[@]}" >/dev/null
}

if [[ "$action" == 'close-ssh' ]]; then
    if is_null_json "$security_group_json"; then
        log 'Managed security group is absent; public SSH ingress is already closed.'
        exit 0
    fi
    security_group_id="$(SECURITY_GROUP="$security_group_json" php -r '$v=json_decode((string)getenv("SECURITY_GROUP"),true,flags:JSON_THROW_ON_ERROR);echo $v["id"]??"";')"
    [[ "$security_group_id" =~ ^[a-z0-9]{20}$ ]] || fail 'Managed security-group ID is invalid.'
    update_security_group_rules "$security_group_id"
    log 'Managed security group now has egress only; public SSH ingress is closed.'
    exit 0
fi

if [[ "$action" == 'apply' ]]; then
    [[ "${YC_GIS_COMPUTE_CONFIRMATION:-}" == 'CREATE_GIS_BUILDER' ]] \
        || fail 'Apply requires exact confirmation CREATE_GIS_BUILDER.'
    [[ "${YC_GIS_SSH_CIDR:-}" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}/32$ ]] \
        || fail 'Apply requires a single validated runner IPv4 /32 in YC_GIS_SSH_CIDR.'
    ssh_ipv4="${YC_GIS_SSH_CIDR%/32}"
    SSH_IPV4="$ssh_ipv4" php -r '
        exit(filter_var((string)getenv("SSH_IPV4"),FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)!==false?0:1);
    ' || fail 'YC_GIS_SSH_CIDR contains an invalid IPv4 address.'
    public_key_file="${YC_GIS_SSH_PUBLIC_KEY_FILE:-}"
    [[ "$public_key_file" == /* && -s "$public_key_file" && ! -L "$public_key_file" ]] \
        || fail 'Apply requires a regular absolute YC_GIS_SSH_PUBLIC_KEY_FILE.'
    [[ "$(wc -l < "$public_key_file" | tr -d '[:space:]')" == '1' \
        && "$(head -n1 "$public_key_file")" =~ ^ssh-ed25519\ [A-Za-z0-9+/=]+(\ .*)?$ ]] \
        || fail 'Builder SSH public key must be one valid Ed25519 line.'

    if is_null_json "$security_group_json"; then
        security_group_json="$(
            yc vpc security-group create \
                --folder-id "$YC_FOLDER_ID" \
                --network-id "$YC_GIS_NETWORK_ID" \
                --name "$security_group_name" \
                --description 'Ephemeral SSH ingress for the temporary Pischeprom GIS builder' \
                --labels "managed-by=${managed_label},lifecycle=ephemeral" \
                --rule 'description=builder-egress,direction=egress,protocol=any,from-port=0,to-port=65535,v4-cidrs=[0.0.0.0/0]' \
                --rule "description=ephemeral-github-ssh,direction=ingress,protocol=tcp,port=22,v4-cidrs=[${YC_GIS_SSH_CIDR}]" \
                --format json \
                --no-user-output
        )" || fail 'Unable to create the dedicated builder security group.'
    fi
    security_group_id="$(SECURITY_GROUP="$security_group_json" php -r '$v=json_decode((string)getenv("SECURITY_GROUP"),true,flags:JSON_THROW_ON_ERROR);echo $v["id"]??"";')"
    [[ "$security_group_id" =~ ^[a-z0-9]{20}$ ]] || fail 'Created security-group ID is invalid.'
    update_security_group_rules "$security_group_id" "$YC_GIS_SSH_CIDR"

    if ! is_null_json "$vm_json"; then
        vm_status="$(VM="$vm_json" php -r '$v=json_decode((string)getenv("VM"),true,flags:JSON_THROW_ON_ERROR);echo $v["status"]??"";')"
        case "$vm_status" in
            RUNNING)
                ;;
            STOPPED)
                vm_id="$(json_id "$vm_json")"
                yc compute instance start "$vm_id" --no-user-output \
                    || fail 'Unable to start the existing managed GIS builder VM.'
                vm_json="$(yc compute instance get "$vm_id" --format json --no-user-output)" \
                    || fail 'Unable to inspect the started managed GIS builder VM.'
                ;;
            *)
                fail "Existing managed GIS builder has unsupported status ${vm_status:-unknown}."
                ;;
        esac
    fi

    if is_null_json "$vm_json"; then
        vm_json="$(
            yc compute instance create \
                --folder-id "$YC_FOLDER_ID" \
                --name "$vm_name" \
                --hostname "$vm_name" \
                --description 'Temporary regular VM for the full-Russia Pischeprom GIS build' \
                --labels "managed-by=${managed_label},lifecycle=ephemeral" \
                --zone "$YC_GIS_ZONE" \
                --platform "$platform_id" \
                --cores "$cores" \
                --core-fraction "$core_fraction" \
                --memory "${memory_gib}GB" \
                --create-boot-disk "name=${disk_name},type=${disk_type},size=${disk_gib}GB,image-id=${YC_GIS_IMAGE_ID},auto-delete=true" \
                --network-interface "subnet-id=${YC_GIS_SUBNET_ID},ipv4-address=auto,nat-ip-version=ipv4,security-group-ids=${security_group_id}" \
                --ssh-key "$public_key_file" \
                --format json \
                --no-user-output
        )" || fail 'Unable to create the temporary GIS builder VM.'
    fi

    VM="$vm_json" php -r '
        $v=json_decode((string)getenv("VM"),true,flags:JSON_THROW_ON_ERROR);
        $interfaces=$v["network_interfaces"]??$v["networkInterfaces"]??[];
        $interface=is_array($interfaces)?($interfaces[0]??[]):[];
        $primary=$interface["primary_v4_address"]??$interface["primaryV4Address"]??[];
        $nat=$primary["one_to_one_nat"]??$primary["oneToOneNat"]??[];
        printf("[logistics-gis] Builder ready: id=%s status=%s internal_ipv4=%s public_ipv4=%s\n",
            $v["id"]??"unknown",$v["status"]??"unknown",
            $primary["address"]??"unknown",$nat["address"]??"unknown");
    '
    log 'The VM is now billable. Provision it, close SSH ingress, and delete the VM plus disk after immutable artifacts are exported.'
    exit 0
fi

[[ "${YC_GIS_COMPUTE_CONFIRMATION:-}" == 'DESTROY_GIS_BUILDER' ]] \
    || fail 'Destroy requires exact confirmation DESTROY_GIS_BUILDER.'

if ! is_null_json "$vm_json"; then
    vm_id="$(VM="$vm_json" php -r '$v=json_decode((string)getenv("VM"),true,flags:JSON_THROW_ON_ERROR);echo $v["id"]??"";')"
    [[ "$vm_id" =~ ^[a-z0-9]{20}$ ]] || fail 'Managed VM ID is invalid.'
    yc compute instance delete "$vm_id" --no-user-output
    vm_json='null'
    log "Deleted managed VM ${vm_id}; its boot disk was configured auto-delete."
fi

remaining_disks="$(yc compute disk list "${yc_common[@]}")" \
    || fail 'Unable to verify disks after VM deletion.'
disk_json="$(select_named "$remaining_disks" "$disk_name")" \
    || fail 'Duplicate or malformed post-delete disk inventory.'
if ! is_null_json "$disk_json"; then
    disk_id="$(json_id "$disk_json")"
    [[ "$disk_id" =~ ^[a-z0-9]{20}$ ]] || fail 'Residual managed disk ID is invalid.'
    disk_json="$(yc compute disk get "$disk_id" --format json --no-user-output)" \
        || fail 'Unable to inspect the complete residual managed disk.'
    DISK="$disk_json" EXPECTED_FOLDER="$YC_FOLDER_ID" EXPECTED_ZONE="$YC_GIS_ZONE" \
    EXPECTED_TYPE="$disk_type" EXPECTED_SIZE="$disk_bytes" php -r '
        $v=json_decode((string)getenv("DISK"),true,flags:JSON_THROW_ON_ERROR);
        $valid=($v["folder_id"]??$v["folderId"]??null)===getenv("EXPECTED_FOLDER")
            &&($v["name"]??null)==="pischeprom-gis-builder-boot"
            &&($v["zone_id"]??$v["zoneId"]??null)===getenv("EXPECTED_ZONE")
            &&($v["type_id"]??$v["typeId"]??null)===getenv("EXPECTED_TYPE")
            &&(int)($v["size"]??0)===(int)getenv("EXPECTED_SIZE");
        exit($valid?0:1);
    ' || fail 'Post-delete managed-name disk differs; refusing to delete it.'
    yc compute disk delete "$disk_id" --no-user-output
    log "Deleted exact residual managed boot disk ${disk_id}."
fi

if ! is_null_json "$security_group_json"; then
    security_group_id="$(SECURITY_GROUP="$security_group_json" php -r '$v=json_decode((string)getenv("SECURITY_GROUP"),true,flags:JSON_THROW_ON_ERROR);echo $v["id"]??"";')"
    [[ "$security_group_id" =~ ^[a-z0-9]{20}$ ]] || fail 'Managed security-group ID is invalid.'
    yc vpc security-group delete "$security_group_id" --no-user-output
    log "Deleted managed security group ${security_group_id}."
fi

log 'Temporary GIS builder, its managed boot disk and dedicated security group are absent.'
