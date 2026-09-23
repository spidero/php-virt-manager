<?php

use PHPUnit\Framework\TestCase;

final class CloudTest extends TestCase
{
    private const KEY = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIHtbyCK3xn0OHnYrK1Nw1g0M8c5lqgH0z7tS2f0nQvXb user@host';

    public function testSshKeysFilter(): void
    {
        $keys = cloud_ssh_keys(self::KEY."\n\n  not-a-key AAAA\nssh-rsa AAAAB3NzaC1yc2E= \r\n");
        $this->assertSame([self::KEY, 'ssh-rsa AAAAB3NzaC1yc2E='], $keys);
        $this->assertSame([], cloud_ssh_keys("ssh-ed25519 AAAA; rm -rf /"));
    }

    public function testPasswordHashIsSha512Crypt(): void
    {
        $hash = cloud_password_hash('secret-pass');
        $this->assertStringStartsWith('$6$', $hash);
        $this->assertSame($hash, crypt('secret-pass', $hash));
    }

    public function testUserData(): void
    {
        $yaml = cloud_user_data('web-1', 'admin', [self::KEY], '$6$salt$hash');
        $this->assertStringStartsWith("#cloud-config\n", $yaml);
        $config = [];
        foreach (array_slice(explode("\n", trim($yaml)), 1) as $line) {
            [$key, $json] = explode(': ', $line, 2);
            $config[$key] = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        }
        $this->assertSame('web-1', $config['hostname']);
        $this->assertSame('admin', $config['users'][0]['name']);
        $this->assertSame([self::KEY], $config['users'][0]['ssh_authorized_keys']);
        $this->assertSame('$6$salt$hash', $config['users'][0]['passwd']);
        $this->assertFalse($config['users'][0]['lock_passwd']);
        $this->assertArrayNotHasKey('groups', $config['users'][0]);
        $this->assertTrue($config['ssh_pwauth']);
    }

    public function testUserDataWithoutPassword(): void
    {
        $yaml = cloud_user_data('vm', 'admin', [self::KEY], '');
        $this->assertStringContainsString('ssh_pwauth: false', $yaml);
        $this->assertStringNotContainsString('passwd', $yaml);
    }

    public function testUserDataEscapesValues(): void
    {
        // a crafted value must not break out of its YAML line
        $yaml = cloud_user_data("vm\nruncmd: [evil]", 'admin', [], '');
        $this->assertSame(1, substr_count($yaml, "\nruncmd:"));
    }

    public function testMetaDataAndCatalog(): void
    {
        $this->assertMatchesRegularExpression('/^instance-id: vm-[0-9a-f]{8}\nlocal-hostname: vm\n$/', cloud_meta_data('vm'));
        $this->assertArrayHasKey('debian-13', cloud_catalog());
        $this->assertSame('cloud-debian-13.qcow2', cloud_volume_name('debian-13'));
    }

    public function testSeedIsoBuild(): void
    {
        if (!is_executable('/usr/bin/xorriso') && !is_executable('/usr/bin/genisoimage')) {
            $this->markTestSkipped('xorriso/genisoimage not installed');
        }
        $dir = data_path('tmp/seed-test/x');
        $iso = cloud_build_seed_iso(dirname($dir), "#cloud-config\n", "instance-id: x\n");
        $this->assertGreaterThan(0, filesize($iso));
        $this->assertStringContainsString('CIDATA', strtoupper((string)file_get_contents($iso, false, null, 32768, 2048)));
        array_map('unlink', glob(dirname($dir).'/*'));
        rmdir(dirname($dir));
    }

    public function testSeedPathsInDomainXml(): void
    {
        $xml = "<domain><devices>
            <disk type='file' device='disk'><source file='/p/vm.qcow2'/><target dev='vda'/></disk>
            <disk type='file' device='cdrom'><source file='/p/vm-seed.iso'/><target dev='sda'/></disk>
            <disk type='file' device='cdrom'><source file='/p/debian.iso'/><target dev='sdb'/></disk>
        </devices></domain>";
        $this->assertSame(['sda' => '/p/vm-seed.iso'], vm_seed_paths($xml));
    }
}
