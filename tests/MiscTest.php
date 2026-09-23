<?php

use PHPUnit\Framework\TestCase;

final class MiscTest extends TestCase
{
    public function testFormatBytes(): void
    {
        $this->assertSame('0 B', format_bytes(0));
        $this->assertSame('1.5 KB', format_bytes(1536));
        $this->assertSame('10 GB', format_bytes(10 * 1024 ** 3));
    }

    public function testActionLogRotation(): void
    {
        $path = data_path('actions.log');
        @unlink($path);
        for ($i = 0; $i < 3; $i++) {
            action_log('test', 'target'.$i, true, str_repeat('x', 100), 'tester');
        }
        $this->assertCount(3, action_log_read());
        $this->assertSame('target2', action_log_read()[0]['target'], 'newest first');

        $this->assertFalse(action_log_rotate(1024 * 1024, 2));
        $this->assertTrue(action_log_rotate(100, 2));
        $this->assertFileExists($path.'.1');
        $this->assertSame([], action_log_read());

        action_log('test', 'again', true, str_repeat('x', 100), 'tester');
        $this->assertTrue(action_log_rotate(100, 2));
        $this->assertFileExists($path.'.2');
        action_log('test', 'third', true, str_repeat('x', 100), 'tester');
        $this->assertTrue(action_log_rotate(100, 2));
        $this->assertFileDoesNotExist($path.'.3', 'only $keep files are kept');
    }

    public function testConnectionsFallbackAndLocality(): void
    {
        global $connections, $connection, $readonly;
        $connections = null;
        $connection = 'qemu:///system';
        $readonly = 1;
        $this->assertSame(['default' => ['uri' => 'qemu:///system', 'label' => 'qemu:///system', 'readonly' => 1]], connections_list());

        $this->assertTrue(connection_is_local('qemu:///system'));
        $this->assertTrue(connection_is_local('qemu+ssh://localhost/system'));
        $this->assertFalse(connection_is_local('qemu+ssh://root@lab.example.com/system'));
    }

    public function testConsoleVncHost(): void
    {
        $this->assertSame('127.0.0.1', console_vnc_host('0.0.0.0'));
        $this->assertSame('10.0.0.5', console_vnc_host('10.0.0.5'));
    }
}
