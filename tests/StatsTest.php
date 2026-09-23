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
}
