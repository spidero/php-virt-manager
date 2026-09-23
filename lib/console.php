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
