<?php

// background job implementations, executed by bin/cron.php (see lib/jobs.php)

const JOB_HANDLERS = [
    'clone'          => 'job_clone',
    'image_download' => 'job_image_download',
    'cloud_create'   => 'job_cloud_create',
];

// returns a callback updating the job message at most every 2 seconds
function job_progress_reporter($job_id, $label) {
    $last = 0;
    return function ($done, $total) use ($job_id, $label, &$last) {
        if (time() - $last >= 2 && $total > 0) {
            $last = time();
            job_progress($job_id, sprintf('%s: %s / %s', $label, format_bytes($done), format_bytes($total)), $done * 100 / $total);
        }
    };
}

// params: image (catalog key), pool
function job_image_download(array $params, $con, $job_id, $uri) {
    $key = (string)$params['image'];
    $image = cloud_catalog()[$key] ?? null;
    $pool = libvirt_storagepool_lookup_by_name($con, (string)$params['pool']);
    if (!$image) {
        throw new RuntimeException('unknown image '.$key);
    }
    if (!$pool || !libvirt_storagepool_is_active($pool)) {
        throw new RuntimeException('storage pool not active');
    }
    if (in_array(cloud_volume_name($key), libvirt_storagepool_list_volumes($pool) ?: [], true)) {
        throw new RuntimeException('image already downloaded');
    }
    $file = data_path('tmp/image-'.$job_id.'.img');
    try {
        cloud_download($image['url'], $file, job_progress_reporter($job_id, 'download'));
        $size = (int)filesize($file);
        $vol = cloud_upload_file($con, $uri, $pool, cloud_volume_name($key), $file, job_progress_reporter($job_id, 'copying to the storage pool'));
        $format = (string)(simplexml_load_string((string)libvirt_storagevolume_get_xml_desc($vol, null))->target->format['type'] ?? '');
        if ($format !== 'qcow2') {
            libvirt_storagevolume_delete($vol, 0);
            throw new RuntimeException('the image is not in qcow2 format ('.$format.')');
        }
    }
    finally {
        @unlink($file);
    }
    return $image['label'].' saved to '.$params['pool'].' ('.format_bytes($size).')';
}

// params: name, image (base volume path), pool, memory, vcpus, disk, network,
// user, ssh_keys, password_hash, start
function job_cloud_create(array $params, $con, $job_id, $uri) {
    $name = (string)$params['name'];
    if (in_array($name, libvirt_list_domains($con) ?: [], true)) {
        throw new RuntimeException('machine '.$name.' already exists');
    }
    $base = libvirt_storagevolume_lookup_by_path($con, (string)$params['image']);
    $pool = libvirt_storagepool_lookup_by_name($con, (string)$params['pool']);
    if (!$base || !$pool) {
        throw new RuntimeException('base image or storage pool not found');
    }
    $created = [];
    $seed_dir = dirname(data_path('tmp/seed-'.$job_id.'/x'));
    try {
        job_progress($job_id, 'copying the base image');
        $disk_bytes = (int)$params['disk'] * 1024 ** 3;
        $base_bytes = (int)libvirt_storagevolume_get_info($base)['capacity'];
        $vol_xml = '<volume><name>'.xml_escape($name.'.qcow2').'</name>'
            ."<capacity unit='bytes'>".max($disk_bytes, $base_bytes).'</capacity>'
            ."<target><format type='qcow2'/></target></volume>";
        $disk = libvirt_storagevolume_create_xml_from($pool, $vol_xml, $base);
        if (!$disk) {
            throw new RuntimeException('copy of the base image failed: '.libvirt_get_last_error());
        }
        $created[] = $disk;
        // grow the disk when the copy kept the (smaller) size of the base image
        if ((int)libvirt_storagevolume_get_info($disk)['capacity'] < $disk_bytes
            && libvirt_storagevolume_resize($disk, $disk_bytes, 0) < 0) {
            throw new RuntimeException('resize failed: '.libvirt_get_last_error());
        }

        job_progress($job_id, 'building the cloud-init ISO');
        $user_data = cloud_user_data($name, (string)$params['user'], (array)$params['ssh_keys'], (string)$params['password_hash']);
        $iso = cloud_build_seed_iso($seed_dir, $user_data, cloud_meta_data($name));
        $seed = cloud_upload_file($con, $uri, $pool, $name.'-seed.iso', $iso);
        $created[] = $seed;

        $xml = domain_new_xml($name, (int)$params['memory'], (int)$params['vcpus'],
            (string)libvirt_storagevolume_get_path($disk), (string)libvirt_storagevolume_get_path($seed),
            (string)$params['network'], ['hd']);
        $res = libvirt_domain_define_xml($con, $xml);
        if (!$res) {
            throw new RuntimeException('define failed: '.libvirt_get_last_error());
        }
    }
    catch (Throwable $e) {
        foreach ($created as $vol) {
            @libvirt_storagevolume_delete($vol, 0);
        }
        throw $e;
    }
    finally {
        foreach (glob($seed_dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($seed_dir);
    }
    if (!empty($params['start']) && !libvirt_domain_create($res)) {
        return 'created, but start failed: '.libvirt_get_last_error();
    }
    return 'created from '.basename((string)$params['image']).(!empty($params['start']) ? ' and started' : '');
}

// params: source (machine name), name (clone name)
function job_clone(array $params, $con, $job_id, $uri) {
    $source = (string)$params['source'];
    $name = (string)$params['name'];
    $res = libvirt_domain_lookup_by_name($con, $source);
    if (!$res) {
        throw new RuntimeException('source machine not found');
    }
    if (libvirt_domain_is_active($res)) {
        throw new RuntimeException('source machine must be shut off');
    }
    if (in_array($name, libvirt_list_domains($con) ?: [], true)) {
        throw new RuntimeException('machine '.$name.' already exists');
    }
    $xml = (string)libvirt_domain_get_xml_desc($res, null, VIR_DOMAIN_XML_INACTIVE);

    $path_map = [];
    $created = [];
    try {
        foreach (vm_disk_paths($xml) as $target => $path) {
            $vol = libvirt_storagevolume_lookup_by_path($con, $path);
            if (!$vol) {
                throw new RuntimeException('disk '.$path.' is not a libvirt volume');
            }
            $pool = libvirt_storagepool_lookup_by_volume($vol);
            $info = libvirt_storagevolume_get_info($vol);
            $format = (string)(simplexml_load_string((string)libvirt_storagevolume_get_xml_desc($vol, null))->target->format['type'] ?? 'raw');
            $vol_xml = '<volume><name>'.xml_escape(vm_clone_volume_name($name, $target, $path)).'</name>'
                ."<capacity unit='bytes'>".(int)$info['capacity'].'</capacity>'
                ."<target><format type='".xml_escape($format)."'/></target></volume>";
            $new = libvirt_storagevolume_create_xml_from($pool, $vol_xml, $vol);
            if (!$new) {
                throw new RuntimeException('copy of '.$path.' failed: '.libvirt_get_last_error());
            }
            $created[] = $new;
            $path_map[$path] = (string)libvirt_storagevolume_get_path($new);
        }
        if (!libvirt_domain_define_xml($con, vm_xml_clone($xml, $name, $path_map))) {
            throw new RuntimeException('define failed: '.libvirt_get_last_error());
        }
    }
    catch (Throwable $e) {
        // do not leave copied disks behind
        foreach ($created as $vol) {
            @libvirt_storagevolume_delete($vol, 0);
        }
        throw $e;
    }
    return 'cloned from '.$source.', '.count($path_map).' disk(s) copied';
}
