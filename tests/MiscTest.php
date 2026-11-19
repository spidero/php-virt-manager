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
        db_query('DELETE FROM connections');
        $this->assertSame(['default' => ['uri' => 'qemu:///system', 'label' => 'qemu:///system', 'readonly' => 1, 'source' => 'config']], connections_list());

        // connections added in the panel
        $this->assertSame([], connection_add('Lab KVM 2', 'qemu+ssh://root@kvm2.example.com/system?no_verify=1', true));
        $this->assertSame([], connection_add('lab kvm 2', 'qemu+ssh://root@kvm3.example.com/system', false));
        $list = connections_list();
        $this->assertSame(['default', 'lab-kvm-2', 'lab-kvm-2-2'], array_keys($list));
        $this->assertSame(['panel', 1], [$list['lab-kvm-2']['source'], $list['lab-kvm-2']['readonly']]);
        $this->assertNotEmpty(connection_add('dup', 'qemu+ssh://root@kvm2.example.com/system?no_verify=1', false), 'same URI');
        $this->assertNotEmpty(connection_add('x', 'http://evil/', false));
        $this->assertNotEmpty(connection_add('x', 'qemu:///system; rm -rf /', false));
        $this->assertNotEmpty(connection_add('', 'qemu:///session', false));
        $this->assertTrue(connection_delete('lab-kvm-2'));
        $this->assertFalse(connection_delete('default'), 'config connections are not stored');
        db_query('DELETE FROM connections');

        $this->assertTrue(connection_is_local('qemu:///system'));
        $this->assertTrue(connection_is_local('qemu+ssh://localhost/system'));
        $this->assertFalse(connection_is_local('qemu+ssh://root@lab.example.com/system'));
    }

    public function testConsoleVncHost(): void
    {
        $this->assertSame('127.0.0.1', console_vnc_host('0.0.0.0'));
        $this->assertSame('10.0.0.5', console_vnc_host('10.0.0.5'));
    }

    public function testStoragePoolXml(): void
    {
        [$xml, $errors] = storage_pool_xml('dir', 'vm-data', ['path' => '/srv/vm-images/']);
        $this->assertSame([], $errors);
        $pool = simplexml_load_string($xml);
        $this->assertSame(['dir', 'vm-data', '/srv/vm-images'], [(string)$pool['type'], (string)$pool->name, (string)$pool->target->path]);

        [$xml] = storage_pool_xml('netfs', 'nas', ['path' => '/mnt/nas', 'host' => 'nas.local', 'export' => '/export/vm']);
        $nfs = simplexml_load_string($xml);
        $this->assertSame(['nas.local', '/export/vm', 'nfs'], [(string)$nfs->source->host['name'], (string)$nfs->source->dir['path'], (string)$nfs->source->format['type']]);

        [$xml] = storage_pool_xml('logical', 'lvm', ['vg' => 'vg_vms']);
        $this->assertSame('/dev/vg_vms', (string)simplexml_load_string($xml)->target->path);

        foreach ([
            ['dir', 'x', ['path' => 'relative']], ['dir', 'x', ['path' => '/']], ['dir', 'x', ['path' => '/srv/../etc']],
            ['dir', 'x', ['path' => "/srv/a'b"]], ['dir', 'bad name', ['path' => '/srv/x']], ['netfs', 'x', ['path' => '/mnt/x', 'host' => 'a b', 'export' => '/e']],
            ['logical', 'x', ['vg' => '../vg']], ['iscsi', 'x', []],
        ] as [$type, $name, $params]) {
            $this->assertNotEmpty(storage_pool_xml($type, $name, $params)[1], $type.' '.json_encode($params));
        }
    }

    public function testMorePoolTypes(): void
    {
        [$xml, $errors] = storage_pool_xml('iscsi', 'san', ['host' => 'san.local', 'iqn' => 'iqn.2004-04.com.example:storage']);
        $this->assertSame([], $errors);
        $p = simplexml_load_string($xml);
        $this->assertSame(['iscsi', 'san.local', 'iqn.2004-04.com.example:storage', '/dev/disk/by-path'],
            [(string)$p['type'], (string)$p->source->host['name'], (string)$p->source->device['path'], (string)$p->target->path]);

        [$xml] = storage_pool_xml('disk', 'sdb', ['device' => '/dev/sdb']);
        $this->assertSame('/dev/sdb', (string)simplexml_load_string($xml)->source->device['path']);
        [$xml] = storage_pool_xml('zfs', 'tank', ['vg' => 'tank']);
        $this->assertSame('tank', (string)simplexml_load_string($xml)->source->name);

        [$xml, $errors] = storage_pool_xml('rbd', 'ceph', ['host' => 'mon1.local', 'vg' => 'vms', 'auth_user' => 'libvirt', 'secret' => '0b6f1b2a-1111-2222-3333-444455556666']);
        $this->assertSame([], $errors);
        $rbd = simplexml_load_string($xml);
        $this->assertSame(['vms', 'mon1.local', 'libvirt'], [(string)$rbd->source->name, (string)$rbd->source->host['name'], (string)$rbd->source->auth['username']]);
        $this->assertCount(0, simplexml_load_string(storage_pool_xml('rbd', 'c', ['host' => 'm', 'vg' => 'v'])[0])->source->auth, 'no auth');

        foreach ([
            ['iscsi', ['host' => 'san', 'iqn' => 'not-an-iqn']], ['iscsi', ['host' => 'a b', 'iqn' => 'iqn.2004-04.com.example:x']],
            ['disk', ['device' => '/etc/passwd']], ['disk', ['device' => '/dev/../sda']], ['zfs', ['vg' => '../x']],
            ['rbd', ['host' => 'm', 'vg' => 'v', 'auth_user' => 'u']], ['rbd', ['host' => 'm', 'vg' => 'v', 'auth_user' => 'u', 'secret' => 'nope']],
        ] as [$type, $params]) {
            $this->assertNotEmpty(storage_pool_xml($type, 'x', $params)[1], $type.' '.json_encode($params));
        }
        $this->assertNotContains('disk', STORAGE_BUILD_TYPES, 'a whole disk is never built (partition table)');
    }
}
