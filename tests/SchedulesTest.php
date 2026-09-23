<?php

use PHPUnit\Framework\TestCase;

final class SchedulesTest extends TestCase
{
    private function at(string $time): int
    {
        return (int)strtotime($time);
    }

    private function schedule(array $fields): array
    {
        return $fields + ['id' => 5, 'frequency' => 'daily', 'hour' => 2, 'weekday' => 0, 'keep' => 3,
            'enabled' => 1, 'created_at' => $this->at('2026-09-01 12:00'), 'last_run' => null];
    }

    public function testHourlySlot(): void
    {
        $s = $this->schedule(['frequency' => 'hourly']);
        $this->assertSame($this->at('2026-09-23 14:00'), schedule_slot($s, $this->at('2026-09-23 14:37:12')));
    }

    public function testDailySlotBeforeAndAfterHour(): void
    {
        $s = $this->schedule(['hour' => 2]);
        $this->assertSame($this->at('2026-09-23 02:00'), schedule_slot($s, $this->at('2026-09-23 14:00')));
        $this->assertSame($this->at('2026-09-22 02:00'), schedule_slot($s, $this->at('2026-09-23 01:59')));
    }

    public function testWeeklySlot(): void
    {
        // 2026-09-23 is a Wednesday; weekday 1 = Monday
        $s = $this->schedule(['frequency' => 'weekly', 'weekday' => 1, 'hour' => 3]);
        $this->assertSame($this->at('2026-09-21 03:00'), schedule_slot($s, $this->at('2026-09-23 10:00')));
        // same weekday, before the hour: previous week
        $s = $this->schedule(['frequency' => 'weekly', 'weekday' => 3, 'hour' => 12]);
        $this->assertSame($this->at('2026-09-16 12:00'), schedule_slot($s, $this->at('2026-09-23 10:00')));
    }

    public function testDueOncePerSlot(): void
    {
        $s = $this->schedule(['hour' => 2]);
        $now = $this->at('2026-09-23 02:00:30');
        $this->assertTrue(schedule_due($s, $now));
        $s['last_run'] = $now;
        $this->assertFalse(schedule_due($s, $this->at('2026-09-23 23:00')));
        $this->assertTrue(schedule_due($s, $this->at('2026-09-24 02:01')));
    }

    public function testNewScheduleWaitsForFirstSlot(): void
    {
        $s = $this->schedule(['created_at' => $this->at('2026-09-23 10:00')]);
        $this->assertFalse(schedule_due($s, $this->at('2026-09-23 10:01')));
        $this->assertTrue(schedule_due($s, $this->at('2026-09-24 02:00')));
    }

    public function testDisabledIsNeverDue(): void
    {
        $this->assertFalse(schedule_due($this->schedule(['enabled' => 0]), $this->at('2026-09-30 02:00')));
    }

    public function testRetentionKeepsNewestOwnSnapshots(): void
    {
        $s = $this->schedule(['keep' => 2]);
        $names = ['manual', 'auto-5-20260920-020000', 'auto-5-20260922-020000', 'auto-6-20260901-020000',
            'auto-5-20260921-020000', 'auto-50-20260101-000000'];
        $this->assertSame(['auto-5-20260920-020000'], schedule_expired($s, $names));
        $this->assertSame('auto-5-20260923-020000', schedule_snapshot_name($s, $this->at('2026-09-23 02:00')));
    }

    public function testCrud(): void
    {
        $id = schedule_create('local', 'vm1', 'weekly', 4, 6, 10, 'tester');
        $this->assertSame('weekly', schedule_find($id)['frequency']);
        $this->assertCount(1, schedule_list('local', 'vm1'));
        schedule_set_enabled($id, false);
        $this->assertSame(0, (int)schedule_find($id)['enabled']);
        schedule_delete($id);
        $this->assertNull(schedule_find($id));
    }
}
