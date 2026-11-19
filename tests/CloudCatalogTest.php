<?php

use PHPUnit\Framework\TestCase;

final class CloudCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        db_query('DELETE FROM cloud_images');
        db_query('DELETE FROM settings');
    }

    private function catalogJson(array $images): string
    {
        return json_encode(['version' => 1, 'images' => $images]);
    }

    public function testBundledFileIsValid(): void
    {
        // the same file is served from GitHub for synchronization
        $parsed = cloud_catalog_parse(file_get_contents(CLOUD_CATALOG_FILE));
        $this->assertSame(0, $parsed['invalid']);
        $this->assertArrayHasKey('debian-13', $parsed['images']);
    }

    public function testParseSkipsInvalidEntries(): void
    {
        $parsed = cloud_catalog_parse($this->catalogJson([
            ['key' => 'ok-1', 'label' => 'OK', 'url' => 'https://example.org/ok.qcow2'],
            ['key' => 'plain-http', 'label' => 'HTTP', 'url' => 'http://example.org/x.qcow2'],
            ['key' => 'Bad Key', 'label' => 'x', 'url' => 'https://example.org/x.qcow2'],
            ['key' => 'no-label', 'label' => '', 'url' => 'https://example.org/x.qcow2'],
            ['key' => 'ok-1', 'label' => 'duplicate', 'url' => 'https://example.org/y.qcow2'],
            'not an object',
        ]));
        $this->assertSame(['ok-1'], array_keys($parsed['images']));
        $this->assertSame(5, $parsed['invalid']);
    }

    public function testParseRejectsUnusableFiles(): void
    {
        foreach (['', 'not json', '{"images": []}', '{"version": 2, "images": [{"key": "a"}]}', $this->catalogJson([])] as $json) {
            try {
                cloud_catalog_parse($json);
                $this->fail('accepted: '.$json);
            }
            catch (RuntimeException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testSeedFromBundledFile(): void
    {
        $catalog = cloud_catalog();
        $this->assertArrayHasKey('debian-13', $catalog);
        $this->assertStringStartsWith('https://', $catalog['debian-13']['url']);
        // seeded only once: a disabled entry stays disabled
        cloud_image_set_enabled('debian-13', false);
        $this->assertArrayNotHasKey('debian-13', cloud_catalog());
        $this->assertArrayHasKey('debian-13', cloud_catalog(true), 'disabled entries stay usable for downloaded images');
    }

    public function testMergeOnlyAddsAndUpdatesUnchanged(): void
    {
        cloud_catalog_seed();
        $this->assertSame([], cloud_image_save('my-os', 'My OS', 'https://example.org/my.qcow2', true));
        $this->assertSame([], cloud_image_save('debian-12', 'Debian 12 (mirror)', 'https://mirror.example.org/d12.qcow2', false));
        cloud_image_set_enabled('ubuntu-24.04', false);

        $result = cloud_catalog_merge([
            'new-os'       => ['label' => 'New OS', 'url' => 'https://example.org/new.qcow2'],
            'debian-13'    => ['label' => 'Debian 13 (new)', 'url' => 'https://example.org/d13.qcow2'],
            'debian-12'    => ['label' => 'Debian 12', 'url' => 'https://example.org/upstream-d12.qcow2'],
            'my-os'        => ['label' => 'Upstream', 'url' => 'https://example.org/upstream.qcow2'],
            'ubuntu-24.04' => ['label' => 'Ubuntu 24.04 LTS', 'url' => 'https://example.org/u24.img'],
        ]);
        $this->assertSame(['added' => 1, 'updated' => 2, 'kept' => 2], $result);

        $all = cloud_catalog(true);
        $this->assertSame('New OS', $all['new-os']['label']);
        $this->assertSame('Debian 13 (new)', $all['debian-13']['label']);
        $this->assertSame('https://mirror.example.org/d12.qcow2', $all['debian-12']['url'], 'edited in the panel');
        $this->assertSame('https://example.org/my.qcow2', $all['my-os']['url'], 'added in the panel');
        $this->assertSame('https://example.org/u24.img', $all['ubuntu-24.04']['url'], 'disabled entries are updated');
        $this->assertArrayNotHasKey('ubuntu-24.04', cloud_catalog(), '... and stay disabled');
        // nothing is removed: entries missing from the list stay
        $this->assertArrayHasKey('rocky-10', $all);

        // restore: the edited entry follows the list again
        $this->assertTrue(cloud_image_reset('debian-12'));
        cloud_catalog_merge(['debian-12' => ['label' => 'Debian 12', 'url' => 'https://example.org/upstream-d12.qcow2']]);
        $this->assertSame('https://example.org/upstream-d12.qcow2', cloud_catalog()['debian-12']['url']);
    }

    public function testSaveValidationAndDelete(): void
    {
        cloud_catalog_seed();
        $this->assertNotEmpty(cloud_image_save('debian-13', 'Dup', 'https://example.org/x.qcow2', true), 'existing key');
        $this->assertNotEmpty(cloud_image_save('missing', 'X', 'https://example.org/x.qcow2', false), 'unknown key');
        $this->assertNotEmpty(cloud_image_save('bad url', 'X', 'ftp://example.org/x', true));
        $this->assertCount(3, cloud_image_save('Bad', '', 'javascript:alert(1)', true));

        $this->assertFalse(cloud_image_delete('debian-13'), 'list entries can only be disabled');
        cloud_image_save('my-os', 'My OS', 'https://example.org/my.qcow2', true);
        $this->assertTrue(cloud_image_delete('my-os'));
        $this->assertArrayNotHasKey('my-os', cloud_catalog(true));
    }

    public function testSyncStateAndSchedule(): void
    {
        $GLOBALS['cloud_catalog_auto_sync'] = true;
        $this->assertTrue(cloud_catalog_sync_due(time()), 'never synchronized');

        $state = cloud_catalog_sync('http://example.org/insecure.json');
        $this->assertFalse($state['ok']);
        $this->assertStringContainsString('https://', $state['message']);
        $this->assertSame($state['message'], cloud_catalog_last_sync()['message']);

        // a failed attempt also waits a day
        $this->assertFalse(cloud_catalog_sync_due(time()));
        $this->assertTrue(cloud_catalog_sync_due(time() + CLOUD_CATALOG_SYNC_INTERVAL));
        $GLOBALS['cloud_catalog_auto_sync'] = false;
        $this->assertFalse(cloud_catalog_sync_due(time() + CLOUD_CATALOG_SYNC_INTERVAL));
        $GLOBALS['cloud_catalog_auto_sync'] = true;
    }
}
