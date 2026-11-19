<?php

use PHPUnit\Framework\TestCase;

final class UpdateTest extends TestCase
{
    public function testRepositoryVersionFileIsValid(): void
    {
        // the panels read this file from GitHub; CI checks it against release tags
        $latest = update_parse(file_get_contents(dirname(__DIR__).'/version.json'));
        $this->assertNotNull($latest);
        $this->assertStringStartsWith(APP_REPO_URL.'/releases/tag/v', $latest['url']);
    }

    public function testParse(): void
    {
        $this->assertSame(['version' => '2.1.0', 'url' => APP_REPO_URL.'/releases/tag/v2.1.0'],
            update_parse('{"version": "2.1.0", "url": "'.APP_REPO_URL.'/releases/tag/v2.1.0"}'));
        // a link elsewhere is replaced by the releases page
        $this->assertSame(APP_REPO_URL.'/releases', update_parse('{"version": "2.1.0", "url": "https://evil.example/"}')['url']);
        foreach (['', 'nope', '{"version": "latest"}', '{"version": "2.1"}', '[]'] as $bad) {
            $this->assertNull(update_parse($bad), $bad);
        }
    }

    public function testAvailable(): void
    {
        $last = ['ok' => true, 'version' => '2.1.0', 'url' => 'u'];
        $this->assertSame(['version' => '2.1.0', 'url' => 'u'], update_available(['version' => '2.0.0'], $last));
        $this->assertNull(update_available(['version' => '2.1.0'], $last), 'same version');
        $this->assertNull(update_available(['version' => '2.2.0'], $last), 'newer than the release');
        $this->assertNull(update_available(['version' => 'master'], $last), 'development builds');
        $this->assertNull(update_available(['version' => '2.0.0'], ['ok' => false, 'message' => 'x']), 'failed check');
        $this->assertNull(update_available(['version' => '2.0.0'], null), 'never checked');
    }

    public function testCheckSchedule(): void
    {
        db_query("DELETE FROM settings WHERE name = 'update_check'");
        $GLOBALS['update_check'] = true;
        $this->assertTrue(update_check_due(time()));
        setting_set('update_check', json_encode(['ok' => true, 'version' => '2.0.0', 'url' => 'u', 'time' => time()]));
        $this->assertFalse(update_check_due(time()));
        $this->assertTrue(update_check_due(time() + UPDATE_CHECK_INTERVAL));
        $GLOBALS['update_check'] = false;
        $this->assertFalse(update_check_due(time() + UPDATE_CHECK_INTERVAL));
        $GLOBALS['update_check'] = true;
        db_query("DELETE FROM settings WHERE name = 'update_check'");
    }
}
