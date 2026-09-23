<?php

// noVNC console: each opened console gets a random token written to the
// data/tokens directory, read by websockify (--token-plugin TokenFile)

const CONSOLE_TOKEN_TTL = 3600;

function console_token_create($host, $port) {
    $dir = dirname(data_path('tokens/.keep'));
    // remove expired tokens
    foreach (glob($dir.'/*') ?: [] as $file) {
        if (filemtime($file) < time() - CONSOLE_TOKEN_TTL) {
            @unlink($file);
        }
    }
    $token = bin2hex(random_bytes(24));
    file_put_contents($dir.'/'.$token, $token.': '.$host.':'.(int)$port."\n");
    return $token;
}

// VNC address as seen from websockify; wildcard listen means localhost
function console_vnc_host($listen) {
    return ($listen === '' || $listen === '0.0.0.0' || $listen === '::') ? '127.0.0.1' : $listen;
}
