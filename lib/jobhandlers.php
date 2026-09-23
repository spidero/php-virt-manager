<?php

// background job implementations, executed by bin/cron.php (see lib/jobs.php)

const JOB_HANDLERS = [
    'clone' => 'job_clone',
];

// params: source (machine name), name (clone name)
function job_clone(array $params, $con) {
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
