<?php

use PHPUnit\Framework\TestCase;

final class VmEditTest extends TestCase
{
    private const XML = "<domain type='kvm'>
        <name>vm1</name><uuid>1111-2222</uuid>
        <memory unit='KiB'>1048576</memory><currentMemory unit='KiB'>1048576</currentMemory>
        <vcpu placement='static'>1</vcpu>
        <os><type arch='x86_64' machine='q35'>hvm</type><boot dev='hd'/><nvram>/var/lib/libvirt/qemu/nvram/vm1_VARS.fd</nvram></os>
        <devices>
          <disk type='file' device='disk'><driver name='qemu' type='qcow2'/><source file='/pool/vm1.qcow2'/><target dev='vda' bus='virtio'/><boot order='1'/></disk>
          <disk type='file' device='cdrom'><driver name='qemu' type='raw'/><source file='/pool/os.iso'/><target dev='sda' bus='sata'/><readonly/></disk>
          <interface type='network'><mac address='52:54:00:00:00:01'/><source network='default'/><target dev='vnet0'/><model type='virtio'/></interface>
          <graphics type='vnc' port='5901' autoport='no' listen='127.0.0.1'/>
        </devices>
      </domain>";

    public function testSetResources(): void
    {
        $doc = simplexml_load_string(vm_xml_set_resources(self::XML, 2048, 4));
        $this->assertSame('2048', (string)$doc->memory);
        $this->assertSame('MiB', (string)$doc->memory['unit']);
        $this->assertSame('2048', (string)$doc->currentMemory);
        $this->assertSame('4', (string)$doc->vcpu);
        $this->assertSame('static', (string)$doc->vcpu['placement'], 'attributes are kept');
    }

    public function testMemoryUnits(): void
    {
        $this->assertSame(1024, vm_memory_mb(self::XML));
        $this->assertSame(2048, vm_memory_mb("<domain><memory unit='GiB'>2</memory></domain>"));
        $this->assertSame(512, vm_memory_mb('<domain><memory>524288</memory></domain>'), 'KiB is the default unit');
    }

    public function testNextTarget(): void
    {
        $this->assertSame('vdb', vm_next_target(self::XML, 'vd'));
        $this->assertSame('sdb', vm_next_target(self::XML, 'sd'));
    }

    public function testEjectAndInsertCdrom(): void
    {
        [$ejected, $device] = vm_xml_set_cdrom(self::XML, '');
        $doc = simplexml_load_string($ejected);
        $cdrom = $doc->xpath("/domain/devices/disk[@device='cdrom']")[0];
        $this->assertCount(0, $cdrom->source);
        $this->assertStringContainsString("device=\"cdrom\"", (string)$device);

        [$inserted] = vm_xml_set_cdrom($ejected, '/pool/other.iso');
        $cdrom = simplexml_load_string($inserted)->xpath("/domain/devices/disk[@device='cdrom']")[0];
        $this->assertSame('/pool/other.iso', (string)$cdrom->source['file']);
    }

    public function testCdromDriveIsAddedWhenMissing(): void
    {
        $xml = "<domain><devices><disk type='file' device='disk'><target dev='vda'/></disk></devices></domain>";
        [$new, $device] = vm_xml_set_cdrom($xml, '/pool/os.iso');
        $this->assertNull($device, 'a new drive cannot be changed live');
        $cdrom = simplexml_load_string($new)->xpath("/domain/devices/disk[@device='cdrom']")[0];
        $this->assertSame('sda', (string)$cdrom->target['dev']);
    }

    public function testBootOrderReplacesDeviceBootEntries(): void
    {
        $xml = vm_xml_set_boot_order(self::XML, ['cdrom', 'hd', 'cdrom']);
        $this->assertSame(['cdrom', 'hd'], vm_boot_order($xml));
        $this->assertCount(0, simplexml_load_string($xml)->xpath('/domain/devices/disk/boot'));
    }

    public function testBootOrderRejectsUnknownDevice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        vm_xml_set_boot_order(self::XML, ['floppy']);
    }

    public function testAddDevice(): void
    {
        $xml = vm_xml_add_device(self::XML, vm_nic_device_xml('lab'));
        $this->assertCount(2, simplexml_load_string($xml)->devices->interface);
    }

    public function testDiskPathsAndSources(): void
    {
        $this->assertSame(['vda' => '/pool/vm1.qcow2'], vm_disk_paths(self::XML));
        $this->assertSame(['/pool/vm1.qcow2', '/pool/os.iso'], vm_all_sources(self::XML));
    }

    public function testClone(): void
    {
        $doc = simplexml_load_string(vm_xml_clone(self::XML, 'vm2', ['/pool/vm1.qcow2' => '/pool/vm2-vda.qcow2']));
        $this->assertSame('vm2', (string)$doc->name);
        $this->assertCount(0, $doc->uuid);
        $this->assertCount(0, $doc->os->nvram);
        $this->assertCount(0, $doc->devices->interface->mac);
        $this->assertCount(0, $doc->devices->interface->target);
        $this->assertSame('/pool/vm2-vda.qcow2', (string)$doc->devices->disk[0]->source['file']);
        $this->assertSame('/pool/os.iso', (string)$doc->devices->disk[1]->source['file'], 'ISO stays shared');
        $this->assertSame('', (string)$doc->devices->graphics['port']);
        $this->assertSame('yes', (string)$doc->devices->graphics['autoport']);
    }

    public function testCloneVolumeName(): void
    {
        $this->assertSame('copy-vda.qcow2', vm_clone_volume_name('copy', 'vda', '/pool/x.qcow2'));
        $this->assertSame('copy-vdb', vm_clone_volume_name('copy', 'vdb', '/dev/sdb'));
    }

    public function testInvalidXmlThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        vm_dom('<broken');
    }
}
