<?php

use PHPUnit\Framework\TestCase;

final class TimezoneTest extends TestCase
{
    public function testSystemTimezoneIsValid(): void
    {
        $this->assertContains(system_timezone(), timezone_identifiers_list());
    }

    public function testTzVariableWins(): void
    {
        $previous = getenv('TZ');
        putenv('TZ=Asia/Tokyo');
        try {
            $this->assertSame('Asia/Tokyo', system_timezone());
            putenv('TZ=Not/AZone');
            $this->assertNotSame('Not/AZone', system_timezone());
        }
        finally {
            putenv($previous === false ? 'TZ' : 'TZ='.$previous);
        }
    }

    public function testCronStaleWhileJobRuns(): void
    {
        touch(data_path('cron.heartbeat'), time() - 3600);
        db_query("DELETE FROM jobs");
        $this->assertTrue(cron_stale());
        $id = job_create('clone', ['name' => 'x']);
        job_claim();
        $this->assertFalse(cron_stale(), 'a running job means the worker is busy');
        job_progress($id, 'working', 50);
        job_finish($id, true, 'ok');
        $this->assertFalse(cron_stale(), 'job_progress refreshed the heartbeat');
    }
}
