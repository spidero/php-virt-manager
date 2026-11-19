<?php

use PHPUnit\Framework\TestCase;

final class StatsTest extends TestCase
{
    private function raw(int $cpu_ns, int $rd, int $rx): array
    {
        return [
            'state.state' => 1, 'cpu.time' => $cpu_ns, 'vcpu.current' => 2,
            'balloon.rss' => 204800, 'balloon.current' => 1048576,
            'block.count' => 2, 'block.0.rd.bytes' => $rd, 'block.0.wr.bytes' => 0,
            'block.1.rd.bytes' => $rd, 'block.1.wr.bytes' => 1048576,
            'net.count' => 1, 'net.0.rx.bytes' => $rx, 'net.0.tx.bytes' => 0,
        ];
    }

    public function testCountersSumDevices(): void
    {
        $c = stats_counters($this->raw(1000, 100, 50));
        $this->assertSame(200, $c['disk_rd'], 'both block devices');
        $this->assertSame(1048576, $c['disk_wr']);
        $this->assertSame(50, $c['net_rx']);
        $this->assertSame(2, $c['vcpus']);
    }

    public function testCountersOfStoppedMachine(): void
    {
        $c = stats_counters(['state.state' => 5]);
        $this->assertSame(0, $c['cpu_ns']);
        $this->assertSame(1, $c['vcpus'], 'never zero, used as divisor');
    }

    public function testRates(): void
    {
        $prev = stats_counters($this->raw(0, 0, 0));
        // 5 s, one vCPU fully busy of two = 50 %, 10 MiB read in total, 5 MiB received
        $cur = stats_counters($this->raw(5 * 1000000000, 5 * 1048576, 5 * 1048576));
        $r = stats_rates($prev, $cur, 5);
        $this->assertSame(50.0, $r['cpu']);
        $this->assertSame(2.0, $r['disk_rd']);
        $this->assertSame(1.0, $r['net_rx']);
        $this->assertEquals(200, $r['mem_mb']);
        $this->assertNull(stats_rates($prev, $cur, 0));
    }

    public function testCounterResetDoesNotGoNegative(): void
    {
        $r = stats_rates(stats_counters($this->raw(9000, 900, 900)), stats_counters($this->raw(0, 0, 0)), 5);
        $this->assertSame(0.0, $r['cpu']);
        $this->assertSame(0.0, $r['disk_rd']);
    }

    public function testNodeMemory(): void
    {
        // KiB, as returned by libvirt_node_get_mem_stats()
        $m = node_memory(['total' => 32 * 1048576, 'free' => 3 * 1048576, 'available' => 12 * 1048576, 'buffers' => 1, 'cached' => 1]);
        $this->assertSame(32 * 1073741824, $m['total']);
        $this->assertSame(20 * 1073741824, $m['used']);
        $this->assertSame(12 * 1073741824, $m['available']);
        $this->assertSame(63, $m['percent']);
        // without "available": free + buffers + cached
        $m = node_memory(['total' => 1000, 'free' => 100, 'buffers' => 50, 'cached' => 250]);
        $this->assertSame(600 * 1024, $m['used']);
        $this->assertSame(60, $m['percent']);
        $this->assertSame(0, node_memory([])['percent']);
    }

    public function testNodeCpuModel(): void
    {
        $sysinfo = '<sysinfo type="smbios"><processor><entry name="manufacturer">Intel(R) Corporation</entry>'
            .'<entry name="version">Intel(R) Core(TM) i3-8100 CPU @ 3.60GHz</entry></processor></sysinfo>';
        $caps = '<capabilities><host><cpu><arch>x86_64</arch><model>Skylake-Client-v3</model><vendor>Intel</vendor></cpu></host></capabilities>';
        $this->assertSame('Intel(R) Core(TM) i3-8100 CPU @ 3.60GHz', node_cpu_model($sysinfo, $caps));
        // firmware placeholder or no sysinfo: CPU model from capabilities
        $placeholder = '<sysinfo type="smbios"><processor><entry name="version">To Be Filled By O.E.M.</entry></processor></sysinfo>';
        $this->assertSame('Intel Skylake-Client-v3', node_cpu_model($placeholder, $caps));
        $this->assertSame('Intel Skylake-Client-v3', node_cpu_model(false, $caps));
        $this->assertSame('', node_cpu_model(false, false));
        $this->assertSame('', node_cpu_model('not xml', ''));
    }

    public function testMemoryUsedPrefersGuestStatistics(): void
    {
        $c = ['mem_rss' => 1000, 'mem_total' => 2048, 'mem_avail' => 1900, 'mem_usable' => 1500];
        $this->assertSame([400, 1900, 'guest'], stats_memory_used($c));
        // no balloon statistics from the guest: QEMU resident memory, at most the assigned memory
        $c = ['mem_rss' => 3000, 'mem_total' => 2048, 'mem_avail' => 0, 'mem_usable' => 0];
        $this->assertSame([2048, 2048, 'host'], stats_memory_used($c));
    }

    public function testCountersIncludeGuestMemory(): void
    {
        $c = stats_counters(['balloon.available' => '991168', 'balloon.usable' => '792944']);
        $this->assertSame(991168, $c['mem_avail']);
        $this->assertSame(792944, $c['mem_usable']);
        $this->assertSame(0, stats_counters([])['mem_avail']);
    }
}
