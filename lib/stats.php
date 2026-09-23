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
