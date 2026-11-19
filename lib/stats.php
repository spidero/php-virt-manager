<?php

// live statistics: raw counters per domain from libvirt; rates (CPU %, MB/s)
// are computed by the browser from two consecutive samples

// counters of one domain from a libvirt_connect_get_all_domain_stats() entry
function stats_counters(array $raw) {
    $sum = function ($prefix, $field) use ($raw) {
        $total = 0;
        for ($i = 0; $i < (int)($raw[$prefix.'.count'] ?? 0); $i++) {
            $total += (int)($raw[$prefix.'.'.$i.'.'.$field] ?? 0);
        }
        return $total;
    };
    return [
        'state'     => (int)($raw['state.state'] ?? 0),
        'cpu_ns'    => (int)($raw['cpu.time'] ?? 0),
        'vcpus'     => max(1, (int)($raw['vcpu.current'] ?? 1)),
        // resident memory of the QEMU process and the memory assigned to the guest, in KiB
        'mem_rss'   => (int)($raw['balloon.rss'] ?? 0),
        'mem_total' => (int)($raw['balloon.current'] ?? 0),
        'disk_rd'   => $sum('block', 'rd.bytes'),
        'disk_wr'   => $sum('block', 'wr.bytes'),
        'net_rx'    => $sum('net', 'rx.bytes'),
        'net_tx'    => $sum('net', 'tx.bytes'),
    ];
}

// rates between two samples, as shown by the charts (also used by tests)
function stats_rates(array $prev, array $cur, $seconds) {
    if ($seconds <= 0) {
        return null;
    }
    $delta = fn($key) => max(0, $cur[$key] - $prev[$key]) / $seconds;
    return [
        'cpu'      => round(min(100, $delta('cpu_ns') / 1e9 / $cur['vcpus'] * 100), 1),
        'mem_mb'   => round($cur['mem_rss'] / 1024),
        'disk_rd'  => round($delta('disk_rd') / 1048576, 2),
        'disk_wr'  => round($delta('disk_wr') / 1048576, 2),
        'net_rx'   => round($delta('net_rx') / 1048576, 3),
        'net_tx'   => round($delta('net_tx') / 1048576, 3),
    ];
}

// host memory in bytes from libvirt_node_get_mem_stats() (values in KiB);
// "used" excludes page cache and buffers, like the "used" column of free(1)
function node_memory(array $stats) {
    $kib = fn($key) => (int)($stats[$key] ?? 0);
    $total = $kib('total');
    $available = isset($stats['available'])
        ? $kib('available')
        : $kib('free') + $kib('buffers') + $kib('cached');
    $available = min($total, $available);
    return [
        'total'     => $total * 1024,
        'used'      => ($total - $available) * 1024,
        'available' => $available * 1024,
        'percent'   => $total > 0 ? (int)round(($total - $available) / $total * 100) : 0,
    ];
}

// processor name: SMBIOS processor version from sysinfo (e.g. "Intel(R) Core(TM)
// i3-8100 CPU @ 3.60GHz"), otherwise vendor and CPU model from capabilities
function node_cpu_model($sysinfo_xml, $capabilities_xml) {
    $sysinfo = $sysinfo_xml ? @simplexml_load_string((string)$sysinfo_xml) : false;
    if ($sysinfo) {
        foreach ($sysinfo->xpath('/sysinfo/processor/entry[@name="version"]') ?: [] as $entry) {
            $name = trim(preg_replace('/\s+/', ' ', (string)$entry));
            // placeholders that some firmware puts into SMBIOS
            if ($name !== '' && !preg_match('/^(unknown|not specified|to be filled by o\.e\.m\.?|default string)$/i', $name)) {
                return $name;
            }
        }
    }
    $caps = $capabilities_xml ? @simplexml_load_string((string)$capabilities_xml) : false;
    if ($caps && isset($caps->host->cpu->model)) {
        return trim($caps->host->cpu->vendor.' '.$caps->host->cpu->model);
    }
    return '';
}
