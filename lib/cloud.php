<?php

// cloud images and cloud-init (NoCloud seed ISO) for machines created from templates

const CLOUD_IMAGES = [
    'ubuntu-26.04' => ['label' => 'Ubuntu 26.04 LTS', 'url' => 'https://cloud-images.ubuntu.com/resolute/current/resolute-server-cloudimg-amd64.img'],
    'ubuntu-24.04' => ['label' => 'Ubuntu 24.04 LTS', 'url' => 'https://cloud-images.ubuntu.com/noble/current/noble-server-cloudimg-amd64.img'],
    'debian-13'    => ['label' => 'Debian 13', 'url' => 'https://cloud.debian.org/images/cloud/trixie/latest/debian-13-genericcloud-amd64.qcow2'],
    'debian-12'    => ['label' => 'Debian 12', 'url' => 'https://cloud.debian.org/images/cloud/bookworm/latest/debian-12-genericcloud-amd64.qcow2'],
    'almalinux-10' => ['label' => 'AlmaLinux 10', 'url' => 'https://repo.almalinux.org/almalinux/10/cloud/x86_64/images/AlmaLinux-10-GenericCloud-latest.x86_64.qcow2'],
    'rocky-10'     => ['label' => 'Rocky Linux 10', 'url' => 'https://dl.rockylinux.org/pub/rocky/10/images/x86_64/Rocky-10-GenericCloud-Base.latest.x86_64.qcow2'],
];

const CLOUD_USER_PATTERN = '/^[a-z_][a-z0-9_-]{0,31}$/';
const CLOUD_VOLUME_PREFIX = 'cloud-';

// catalog: built-in images plus $cloud_images from config.php (same format)
function cloud_catalog() {
    global $cloud_images;
    return array_merge(CLOUD_IMAGES, is_array($cloud_images ?? null) ? $cloud_images : []);
}

function cloud_volume_name($key) {
    return CLOUD_VOLUME_PREFIX.$key.'.qcow2';
}

// downloaded base images in active pools: key => [pool, path, size]
function cloud_downloaded($con) {
    $found = [];
    foreach (array_keys(storage_active_pools($con)) as $pool_name) {
        $pool = libvirt_storagepool_lookup_by_name($con, $pool_name);
        foreach (array_keys(cloud_catalog()) as $key) {
            if (isset($found[$key]) || !in_array(cloud_volume_name($key), libvirt_storagepool_list_volumes($pool) ?: [], true)) {
                continue;
            }
            $vol = libvirt_storagevolume_lookup_by_name($pool, cloud_volume_name($key));
            $info = libvirt_storagevolume_get_info($vol);
            $found[$key] = [
                'pool' => $pool_name,
                'path' => (string)libvirt_storagevolume_get_path($vol),
                'size' => format_bytes($info['capacity']),
            ];
        }
    }
    return $found;
}

// valid SSH public keys from the text (one per line)
function cloud_ssh_keys($text) {
    $keys = [];
    foreach (preg_split('/\R/', (string)$text) as $line) {
        $line = trim($line);
        if (preg_match('#^(ssh-(rsa|ed25519|dss)|ecdsa-sha2-nistp(256|384|521)|sk-(ssh-ed25519|ecdsa-sha2-nistp256)@openssh\.com) [A-Za-z0-9+/=]+( [^\r\n]*)?$#', $line)) {
            $keys[] = $line;
        }
    }
    return $keys;
}

// SHA-512 crypt hash accepted by cloud-init/useradd
function cloud_password_hash($password) {
    $chars = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $salt = '';
    for ($i = 0; $i < 16; $i++) {
        $salt .= $chars[random_int(0, 63)];
    }
    return crypt($password, '$6$'.$salt.'$');
}

// #cloud-config user-data; values are JSON-encoded, which is valid YAML
function cloud_user_data($hostname, $user, array $ssh_keys, $password_hash) {
    $account = [
        'name'   => $user,
        // no 'groups': a group missing in the distribution (wheel on Debian) makes useradd fail
        'sudo'   => 'ALL=(ALL) NOPASSWD:ALL',
        'shell'  => '/bin/bash',
    ];
    if ($ssh_keys) {
        $account['ssh_authorized_keys'] = array_values($ssh_keys);
    }
    if ($password_hash !== '') {
        $account['lock_passwd'] = false;
        $account['passwd'] = $password_hash;
    }
    $config = [
        'hostname'         => $hostname,
        'preserve_hostname'=> false,
        'users'            => [$account],
        'ssh_pwauth'       => $password_hash !== '',
        'packages'         => ['qemu-guest-agent'],
        'runcmd'           => [['systemctl', 'enable', '--now', 'qemu-guest-agent']],
    ];
    $yaml = "#cloud-config\n";
    foreach ($config as $key => $value) {
        $yaml .= $key.': '.json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }
    return $yaml;
}

function cloud_meta_data($hostname) {
    return 'instance-id: '.$hostname.'-'.bin2hex(random_bytes(4))."\n"
        .'local-hostname: '.$hostname."\n";
}

// builds the NoCloud seed ISO (volume label "cidata") and returns its path
function cloud_build_seed_iso($dir, $user_data, $meta_data) {
    if (!is_dir($dir) && !mkdir($dir, 0770, true)) {
        throw new RuntimeException('cannot create '.$dir);
    }
    file_put_contents($dir.'/user-data', $user_data);
    file_put_contents($dir.'/meta-data', $meta_data);
    $iso = $dir.'/seed.iso';
    if (is_executable('/usr/bin/xorriso')) {
        $cmd = ['/usr/bin/xorriso', '-as', 'mkisofs', '-quiet', '-output', $iso, '-volid', 'cidata', '-joliet', '-rock', $dir.'/user-data', $dir.'/meta-data'];
    }
    elseif (is_executable('/usr/bin/genisoimage')) {
        $cmd = ['/usr/bin/genisoimage', '-quiet', '-output', $iso, '-volid', 'cidata', '-joliet', '-rock', $dir.'/user-data', $dir.'/meta-data'];
    }
    else {
        throw new RuntimeException('xorriso or genisoimage is required to build the cloud-init ISO');
    }
    [$status, $output] = cloud_run($cmd);
    if ($status !== 0 || !is_file($iso)) {
        throw new RuntimeException('ISO build failed: '.trim($output));
    }
    return $iso;
}

// uploads a local file into a new volume with "virsh vol-upload" (the pool
// directory is not writable for the web server, and libvirt_stream_send() of
// libvirt-php 0.5.x sends wrong data because it does not dereference $data)
function cloud_upload_file($con, $uri, $pool, $vol_name, $file) {
    $size = filesize($file);
    $xml = '<volume><name>'.xml_escape($vol_name).'</name>'
        ."<capacity unit='bytes'>".$size.'</capacity>'
        ."<target><format type='raw'/></target></volume>";
    $vol = libvirt_storagevolume_create_xml($pool, $xml);
    if (!$vol) {
        throw new RuntimeException('cannot create volume '.$vol_name.': '.libvirt_get_last_error());
    }
    $cmd = ['virsh', '-q', '-c', $uri, 'vol-upload', '--vol', (string)libvirt_storagevolume_get_path($vol), '--file', $file];
    [$status, $output] = cloud_run($cmd);
    if ($status !== 0) {
        @libvirt_storagevolume_delete($vol, 0);
        throw new RuntimeException('upload of '.$vol_name.' failed: '.trim($output));
    }
    // let libvirt detect the real format (qcow2) of the uploaded image
    libvirt_storagepool_refresh($pool, 0);
    return libvirt_storagevolume_lookup_by_name($pool, $vol_name) ?: $vol;
}

// runs a command without a shell, returns [exit status, stdout+stderr]
function cloud_run(array $cmd) {
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        throw new RuntimeException('cannot run '.$cmd[0]);
    }
    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    return [proc_close($proc), (string)$output];
}

// downloads a URL to a file with curl, calling $progress($bytes, $total)
function cloud_download($url, $file, ?callable $progress = null) {
    $fh = fopen($file, 'wb');
    if (!$fh) {
        throw new RuntimeException('cannot write '.$file);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_FAILONERROR    => true,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_LOW_SPEED_LIMIT => 1024,
        CURLOPT_LOW_SPEED_TIME => 120,
        CURLOPT_NOPROGRESS     => $progress === null,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS | CURLPROTO_HTTP,
    ]);
    if ($progress) {
        curl_setopt($ch, CURLOPT_XFERINFOFUNCTION, function ($ch, $total, $now) use ($progress) {
            $progress($now, $total);
            return 0;
        });
    }
    $ok = curl_exec($ch);
    $error = curl_error($ch);
    fclose($fh);
    if (!$ok) {
        @unlink($file);
        throw new RuntimeException('download failed: '.$error);
    }
}
