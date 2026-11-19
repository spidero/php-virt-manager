<?php

// libvirt virtual networks: new network XML, IPv4 subnet arithmetic, DHCP
// static hosts; live changes go through "virsh net-update" (libvirt-php has
// no virNetworkUpdate), which changes the running network without a restart

const NETWORK_MODES = ['nat' => 'NAT', 'route' => 'routed', 'isolated' => 'isolated'];
const NETWORK_MAC_PATTERN = '/^[0-9a-f]{2}(:[0-9a-f]{2}){5}$/';
const NETWORK_HOSTNAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9.-]{0,62}$/';

// "192.168.50.0/24" -> network, prefix, netmask, gateway (first address),
// default DHCP range (second to last usable address); null when invalid
function network_cidr($cidr) {
    if (!preg_match('#^(\d{1,3}(?:\.\d{1,3}){3})/(\d{1,2})$#', trim((string)$cidr), $m)) {
        return null;
    }
    $ip = ip2long($m[1]);
    $prefix = (int)$m[2];
    if ($ip === false || $prefix < 8 || $prefix > 29) {
        return null;
    }
    $mask = (0xFFFFFFFF << (32 - $prefix)) & 0xFFFFFFFF;
    $network = $ip & $mask;
    $broadcast = $network | (~$mask & 0xFFFFFFFF);
    return [
        'network'    => long2ip($network),
        'prefix'     => $prefix,
        'netmask'    => long2ip($mask),
        'gateway'    => long2ip($network + 1),
        'dhcp_start' => long2ip($network + 2),
        'dhcp_end'   => long2ip($broadcast - 1),
        'first'      => $network,
        'last'       => $broadcast,
    ];
}

// usable host address of the subnet (not the network, broadcast or gateway address)
function network_ip_usable($ip, array $cidr) {
    $long = ip2long((string)$ip);
    return $long !== false && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        && $long > $cidr['first'] + 1 && $long < $cidr['last'];
}

// true when the subnets "a.b.c.d/p" overlap
function network_overlaps($cidr_a, $cidr_b) {
    $a = network_cidr($cidr_a);
    $b = network_cidr($cidr_b);
    return $a && $b && $a['first'] <= $b['last'] && $b['first'] <= $a['last'];
}

function network_new_xml($name, $mode, array $cidr, $dhcp) {
    $forward = $mode === 'isolated' ? '' : "<forward mode='".xml_escape($mode)."'/>";
    $dhcp_xml = $dhcp ? "<dhcp><range start='".$cidr['dhcp_start']."' end='".$cidr['dhcp_end']."'/></dhcp>" : '';
    // no <bridge>: libvirt picks a free virbrN
    return '<network><name>'.xml_escape($name).'</name>'.$forward
        ."<ip address='".$cidr['gateway']."' netmask='".$cidr['netmask']."'>".$dhcp_xml.'</ip></network>';
}

// IPv4 settings of a network definition: [cidr, gateway, dhcp range or null, static hosts]
function network_ipv4(SimpleXMLElement $xml) {
    foreach ($xml->ip as $ip) {
        $family = (string)($ip['family'] ?? 'ipv4');
        if ($family !== 'ipv4' && $family !== '') {
            continue;
        }
        $address = (string)$ip['address'];
        $prefix = isset($ip['prefix']) ? (int)$ip['prefix']
            : (isset($ip['netmask']) ? substr_count(decbin((int)ip2long((string)$ip['netmask'])), '1') : 24);
        $range = isset($ip->dhcp->range) ? ['start' => (string)$ip->dhcp->range['start'], 'end' => (string)$ip->dhcp->range['end']] : null;
        $hosts = [];
        foreach ($ip->dhcp->host ?? [] as $host) {
            $hosts[] = ['mac' => strtolower((string)$host['mac']), 'ip' => (string)$host['ip'], 'name' => (string)$host['name']];
        }
        $cidr = network_cidr($address.'/'.$prefix);
        return ['cidr' => $cidr ? $cidr['network'].'/'.$prefix : '', 'gateway' => $address, 'range' => $range, 'hosts' => $hosts];
    }
    return null;
}

// virsh net-update: live (when the network runs) and persistent; '' or the error
function network_update($uri, $name, $command, $section, $xml, $active) {
    $cmd = ['virsh', '-q', '-c', $uri, 'net-update', $name, $command, $section, $xml, '--config'];
    if ($active) {
        $cmd[] = '--live';
    }
    [$status, $output] = cloud_run($cmd);
    return $status === 0 ? '' : trim($output);
}

// machines with an interface in the network: [mac => machine name]
function network_machine_macs($con, $network) {
    $macs = [];
    foreach (libvirt_list_domains($con) ?: [] as $name) {
        $res = libvirt_domain_lookup_by_name($con, $name);
        $xml = $res ? (string)libvirt_domain_get_xml_desc($res, null, VIR_DOMAIN_XML_INACTIVE) : '';
        foreach ($xml !== '' ? vm_nic_devices($xml) : [] as $mac => $nic) {
            if ($nic['type'] === 'network' && $nic['source'] === $network) {
                $macs[$mac] = $name;
            }
        }
    }
    return $macs;
}

// where a network interface is connected: "network:<libvirt network>",
// "bridge:<host bridge>" or "direct:<host interface>" (macvtap, bridge mode)
const NIC_IFACE_PATTERN = '/^[A-Za-z0-9_.:-]{1,15}$/';

// [type, name] of a target; a plain name is a libvirt network (older forms and API)
function nic_target_parse($target) {
    $target = (string)$target;
    if (preg_match('/^(network|bridge|direct):(.+)$/', $target, $m)) {
        return [$m[1], $m[2]];
    }
    return ['network', $target];
}

// host bridges and physical interfaces for the target lists; only for a local
// connection (the panel sees its own host's /sys/class/net); Docker bridges are left out
function nic_host_interfaces($uri, $sys = '/sys/class/net') {
    $list = ['bridge' => [], 'direct' => []];
    if (!connection_is_local($uri)) {
        return $list;
    }
    foreach (glob($sys.'/*') ?: [] as $path) {
        $name = basename($path);
        if (!preg_match(NIC_IFACE_PATTERN, $name) || $name === 'docker0' || preg_match('/^br-[0-9a-f]{12}$/', $name)) {
            continue;
        }
        if (is_dir($path.'/bridge')) {
            $list['bridge'][] = $name;
        }
        elseif (file_exists($path.'/device') && !is_dir($path.'/brport')) {
            $list['direct'][] = $name;
        }
    }
    sort($list['bridge']);
    sort($list['direct']);
    return $list;
}

// choices for interface selects: target => label
function nic_targets(array $networks, array $host) {
    $targets = [];
    foreach ($networks as $net) {
        $targets['network:'.$net] = $net;
    }
    foreach ($host['bridge'] as $bridge) {
        $targets['bridge:'.$bridge] = $bridge.' ('.t('host bridge').')';
    }
    foreach ($host['direct'] as $dev) {
        $targets['direct:'.$dev] = $dev.' ('.t('direct, macvtap').')';
    }
    return $targets;
}

// true when the target exists in the given choices (a plain network name counts as network:<name>)
function nic_target_valid($target, array $targets) {
    [$type, $name] = nic_target_parse($target);
    return isset($targets[$type.':'.$name]);
}

// <source>/<interface type> for a target
function nic_target_xml_parts($target) {
    [$type, $name] = nic_target_parse($target);
    $name = xml_escape($name);
    return match ($type) {
        'bridge' => ['bridge', "<source bridge='".$name."'/>"],
        'direct' => ['direct', "<source dev='".$name."' mode='bridge'/>"],
        default  => ['network', "<source network='".$name."'/>"],
    };
}

// "default" -> "network:default"; prefixed targets stay
function nic_target_normalize($target) {
    [$type, $name] = nic_target_parse($target);
    return $type.':'.$name;
}

// targets of a connection: libvirt networks, host bridges and interfaces (local only)
function nic_targets_for($con, $uri) {
    return nic_targets(libvirt_list_networks($con) ?: [], nic_host_interfaces($uri));
}

// default target of the wizards: the "default" network, else the first choice
function nic_target_default(array $targets) {
    return isset($targets['network:default']) ? 'network:default' : (string)(array_key_first($targets) ?? '');
}
