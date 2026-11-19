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

    private const DEVICES_XML = "<domain><name>vm</name><devices>
        <disk type='file' device='disk'><source file='/p/vm.qcow2'/><target dev='vda' bus='virtio'/></disk>
        <disk type='file' device='disk'><source file='/p/vm-vdb.qcow2'/><target dev='vdb' bus='virtio'/></disk>
        <disk type='file' device='cdrom'><source file='/p/os.iso'/><target dev='sda' bus='sata'/></disk>
        <interface type='network'><mac address='52:54:00:AA:00:01'/><source network='default'/><model type='virtio'/><address type='pci' slot='0x01'/></interface>
        <interface type='bridge'><mac address='52:54:00:aa:00:02'/><source bridge='br0'/><model type='e1000e'/></interface>
        </devices></domain>";

    public function testDeviceLists(): void
    {
        $disks = vm_disk_devices(self::DEVICES_XML);
        $this->assertSame(['vda', 'vdb', 'sda'], array_keys($disks));
        $this->assertSame('/p/vm-vdb.qcow2', $disks['vdb']['path']);
        $this->assertSame('cdrom', $disks['sda']['device']);
        $this->assertStringContainsString("dev=\"vdb\"", $disks['vdb']['xml']);

        $nics = vm_nic_devices(self::DEVICES_XML);
        $this->assertSame(['52:54:00:aa:00:01', '52:54:00:aa:00:02'], array_keys($nics), 'MACs lower case');
        $this->assertSame(['network', 'default', 'virtio'], [$nics['52:54:00:aa:00:01']['type'], $nics['52:54:00:aa:00:01']['source'], $nics['52:54:00:aa:00:01']['model']]);
        $this->assertSame('br0', $nics['52:54:00:aa:00:02']['source']);
    }

    public function testRemoveDevices(): void
    {
        $xml = vm_xml_remove_disk(self::DEVICES_XML, 'vdb');
        $this->assertSame(['vda', 'sda'], array_keys(vm_disk_devices($xml)));
        $xml = vm_xml_remove_nic($xml, '52:54:00:AA:00:02');
        $this->assertSame(['52:54:00:aa:00:01'], array_keys(vm_nic_devices($xml)));
        $this->assertSame(['vda', 'sda'], array_keys(vm_disk_devices(vm_xml_remove_disk($xml, "x' or '1'='1"))), 'quotes in the target');
    }

    public function testNicChange(): void
    {
        $nic = vm_nic_devices(self::DEVICES_XML)['52:54:00:aa:00:01']['xml'];
        // network only: the PCI address stays
        $same_model = simplexml_load_string(vm_nic_xml_change($nic, 'lab', 'virtio'));
        $this->assertSame('lab', (string)$same_model->source['network']);
        $this->assertCount(1, $same_model->address);
        // another model: the address is dropped, MAC kept
        $other = simplexml_load_string(vm_nic_xml_change($nic, 'lab', 'e1000e'));
        $this->assertSame('e1000e', (string)$other->model['type']);
        $this->assertCount(0, $other->address);
        $this->assertSame('52:54:00:AA:00:01', (string)$other->mac['address']);
        // a bridge interface becomes a network interface
        $bridge = vm_nic_devices(self::DEVICES_XML)['52:54:00:aa:00:02']['xml'];
        $changed = simplexml_load_string(vm_nic_xml_change($bridge, 'default', 'virtio'));
        $this->assertSame('network', (string)$changed['type']);
        $this->assertCount(1, $changed->source);
        $this->assertSame('', (string)$changed->source['bridge']);

        $xml = vm_xml_replace_nic(self::DEVICES_XML, '52:54:00:aa:00:01', vm_nic_xml_change($nic, 'lab', 'e1000e'));
        $this->assertSame('lab', vm_nic_devices($xml)['52:54:00:aa:00:01']['source']);
        $this->assertCount(2, vm_nic_devices($xml));
    }

    public function testSerialConsole(): void
    {
        $xml = "<domain><name>vm</name><devices><serial type='pty'><target port='0'/></serial><console type='pty'><target type='serial' port='0'/></console></devices></domain>";
        $this->assertSame(0, vm_serial_port($xml));
        $tcp = vm_xml_set_serial($xml, 7105);
        $this->assertSame(7105, vm_serial_port($tcp));
        $doc = simplexml_load_string($tcp);
        $this->assertCount(1, $doc->devices->serial, 'old serial ports replaced');
        $this->assertCount(0, $doc->devices->console);
        $this->assertSame('127.0.0.1', (string)$doc->devices->serial->source['host']);
        $back = vm_xml_set_serial($tcp, 0);
        $this->assertSame(0, vm_serial_port($back));
        $this->assertSame('pty', (string)simplexml_load_string($back)->devices->serial['type']);
        // a port on another address is not a browser console
        $this->assertSame(0, vm_serial_port(str_replace('127.0.0.1', '0.0.0.0', $tcp)));
        // clones must not reuse the port
        $this->assertSame(0, vm_serial_port(vm_xml_clone($tcp, 'vm-2', [])));
        // new machines
        $this->assertSame(7200, vm_serial_port(domain_new_xml('vm', 512, 1, '/d.qcow2', '', 'net', ['hd'], 'sata', 7200)));
        $this->assertSame(0, vm_serial_port(domain_new_xml('vm', 512, 1, '/d.qcow2', '', 'net')));
    }

    public function testXmlIdentityCheck(): void
    {
        $uuid = '0b6f1b2a-1111-2222-3333-444455556666';
        $xml = "<domain type='kvm'><name>vm</name><uuid>$uuid</uuid><memory>1</memory></domain>";
        $this->assertSame([], vm_xml_identity_errors($xml, 'vm', $uuid));
        $this->assertSame([], vm_xml_identity_errors(str_replace($uuid, strtoupper($uuid), $xml), 'vm', $uuid), 'UUID case');
        $this->assertSame([], vm_xml_identity_errors("<domain><name>vm</name></domain>", 'vm', $uuid), 'missing UUID keeps the current one');
        $this->assertCount(1, vm_xml_identity_errors(str_replace('<name>vm<', '<name>other<', $xml), 'vm', $uuid));
        $this->assertCount(1, vm_xml_identity_errors(str_replace('0b6f', 'ffff', $xml), 'vm', $uuid));
        $this->assertCount(1, vm_xml_identity_errors('<domain><name>vm</name>', 'vm', $uuid), 'not well-formed');
        $this->assertCount(1, vm_xml_identity_errors('<network><name>vm</name></network>', 'vm', $uuid));
    }

    public function testResourcesWithBalloonAndTopology(): void
    {
        $xml = "<domain><name>vm</name><memory unit='KiB'>2097152</memory><vcpu>2</vcpu>"
            ."<cpu mode='custom' match='exact'><model>Skylake-Client</model><vendor>Intel</vendor></cpu></domain>";
        $this->assertSame(['mode' => 'custom', 'sockets' => 1, 'cores' => 2, 'threads' => 1], vm_cpu_settings($xml));
        $this->assertSame(2048, vm_memory_mb($xml, 'currentMemory'), 'no currentMemory: all memory');

        // same mode: the named model stays, the topology is set
        $same = vm_xml_set_resources($xml, 4096, 4, 3072, ['mode' => 'custom', 'sockets' => 2, 'cores' => 2, 'threads' => 1]);
        $doc = simplexml_load_string($same);
        $this->assertSame('Skylake-Client', (string)$doc->cpu->model);
        $this->assertSame(['4096', '3072', '4'], [(string)$doc->memory, (string)$doc->currentMemory, (string)$doc->vcpu]);
        $this->assertSame(['mode' => 'custom', 'sockets' => 2, 'cores' => 2, 'threads' => 1], vm_cpu_settings($same));
        $this->assertSame(3072, vm_memory_mb($same, 'currentMemory'));

        // another mode: model, vendor and match are dropped
        $pass = simplexml_load_string(vm_xml_set_resources($same, 4096, 4, null, ['mode' => 'host-passthrough', 'sockets' => 1, 'cores' => 2, 'threads' => 2]));
        $this->assertSame('host-passthrough', (string)$pass->cpu['mode']);
        $this->assertCount(0, $pass->cpu->model);
        $this->assertSame('', (string)$pass->cpu['match']);
        $model = simplexml_load_string(vm_xml_set_resources("<domain><vcpu>1</vcpu><cpu mode='host-passthrough' migratable='on'/></domain>",
            512, 1, null, ['mode' => 'host-model', 'sockets' => 1, 'cores' => 1, 'threads' => 1]));
        $this->assertSame('', (string)$model->cpu['migratable'], 'migratable only for host-passthrough');
        $this->assertCount(1, $pass->cpu->topology);
        $this->assertSame('4096', (string)$pass->currentMemory, 'without current memory: all memory');

        // old call without CPU settings keeps the CPU element
        $plain = simplexml_load_string(vm_xml_set_resources($xml, 1024, 1));
        $this->assertSame('Skylake-Client', (string)$plain->cpu->model);
        $this->assertSame(['mode' => 'host-passthrough', 'sockets' => 1, 'cores' => 3, 'threads' => 1],
            vm_cpu_settings("<domain><vcpu>3</vcpu></domain>"), 'defaults');
    }

    public function testHostDevices(): void
    {
        $this->assertNull(hostdev_xml('usb:046d'));
        $this->assertNull(hostdev_xml("usb:046d:082c' or 'x"));
        $this->assertNull(hostdev_xml('pci:0000:02:00.9'));
        $usb = simplexml_load_string(hostdev_xml('usb:046d:082c'));
        $this->assertSame(['usb', 'yes', '0x046d', '0x082c'], [(string)$usb['type'], (string)$usb['managed'], (string)$usb->source->vendor['id'], (string)$usb->source->product['id']]);
        $pci = simplexml_load_string(hostdev_xml('pci:0000:02:00.0'));
        $this->assertSame(['0x0000', '0x02', '0x00', '0x0'], [(string)$pci->source->address['domain'], (string)$pci->source->address['bus'], (string)$pci->source->address['slot'], (string)$pci->source->address['function']]);

        $xml = vm_xml_add_device(vm_xml_add_device("<domain><name>vm</name><devices/></domain>", hostdev_xml('usb:046d:082c')), hostdev_xml('pci:0000:02:00.0'));
        // libvirt writes ids and addresses in its own way; keys stay the same
        $xml = str_replace(["0x046d", "bus='0x02'"], ["0x046D", "bus='2'"], $xml);
        $this->assertSame(['usb:046d:082c', 'pci:0000:02:00.0'], array_keys(vm_hostdevs($xml)));
        $without = vm_xml_remove_hostdev($xml, 'usb:046d:082c');
        $this->assertSame(['pci:0000:02:00.0'], array_keys(vm_hostdevs($without)));
        $this->assertSame([], vm_hostdevs(vm_xml_remove_hostdev($without, 'pci:0000:02:00.0')));
        $this->assertSame($without, vm_xml_remove_hostdev($without, 'usb:ffff:ffff'), 'unknown key: unchanged');
    }
}
