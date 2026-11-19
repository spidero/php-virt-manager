<?php

// noVNC console: each opened console gets a random token written to the
// data/tokens directory, read by websockify (--token-plugin TokenFile)

const CONSOLE_TOKEN_TTL = 3600;

function console_tokens_dir() {
    return dirname(data_path('tokens/.keep'));
}

// removes expired tokens, returns their number
function console_tokens_cleanup() {
    $removed = 0;
    foreach (glob(console_tokens_dir().'/*') ?: [] as $file) {
        if (filemtime($file) < time() - CONSOLE_TOKEN_TTL && @unlink($file)) {
            $removed++;
        }
    }
    return $removed;
}

function console_token_create($host, $port) {
    $dir = console_tokens_dir();
    console_tokens_cleanup();
    $token = bin2hex(random_bytes(24));
    file_put_contents($dir.'/'.$token, $token.': '.$host.':'.(int)$port."\n");
    return $token;
}

// VNC address as seen from websockify; wildcard listen means localhost
function console_vnc_host($listen) {
    return ($listen === '' || $listen === '0.0.0.0' || $listen === '::') ? '127.0.0.1' : $listen;
}

// TCP ports of the browser text consoles (serial ports bound to 127.0.0.1)
const SERIAL_PORT_MIN = 7100;
const SERIAL_PORT_MAX = 7999;

// first port of the range not used by any machine of the connection
function console_serial_free_port($con) {
    $used = [];
    foreach (libvirt_list_domains($con) ?: [] as $name) {
        $res = libvirt_domain_lookup_by_name($con, $name);
        $xml = $res ? (string)libvirt_domain_get_xml_desc($res, null, VIR_DOMAIN_XML_INACTIVE) : '';
        if ($xml !== '') {
            $used[] = vm_serial_port($xml);
        }
    }
    for ($port = SERIAL_PORT_MIN; $port <= SERIAL_PORT_MAX; $port++) {
        if (!in_array($port, $used, true)) {
            return $port;
        }
    }
    throw new RuntimeException('No free text console port');
}
