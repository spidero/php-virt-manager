<?php

use PHPUnit\Framework\TestCase;

final class DomainXmlTest extends TestCase
{
    public function testNewDomainXmlIsValidAndEscaped(): void
    {
        $xml = domain_new_xml('vm-1', 2048, 2, "/pool/a'b<c>.qcow2", '/pool/os.iso', 'default');
        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc);
        $this->assertSame('vm-1', (string)$doc->name);
        $this->assertSame('2048', (string)$doc->memory);
        $this->assertSame("/pool/a'b<c>.qcow2", (string)$doc->devices->disk[0]->source['file']);
        $this->assertSame('cdrom', (string)$doc->devices->disk[1]['device']);
        $this->assertSame('vnc', (string)$doc->devices->graphics['type']);
        $this->assertSame('127.0.0.1', (string)$doc->devices->graphics['listen']);
    }

    public function testIsoBus(): void
    {
        // installer ISOs: SATA, no SCSI controller
        $doc = simplexml_load_string(domain_new_xml('vm', 512, 1, '/d.qcow2', '/os.iso', 'net'));
        $this->assertSame('sata', (string)$doc->devices->disk[1]->target['bus']);
        $this->assertCount(0, $doc->xpath("/domain/devices/controller[@type='scsi']"));
        // cloud-init seed: virtio-scsi (cloud kernels lack AHCI)
        $doc = simplexml_load_string(domain_new_xml('vm', 512, 1, '/d.qcow2', '/vm-seed.iso', 'net', ['hd'], 'scsi'));
        $this->assertSame('cdrom', (string)$doc->devices->disk[1]['device']);
        $this->assertSame('scsi', (string)$doc->devices->disk[1]->target['bus']);
        $this->assertSame('virtio-scsi', (string)$doc->xpath("/domain/devices/controller[@type='scsi']")[0]['model']);
        $this->assertSame(['sda' => '/vm-seed.iso'], vm_seed_paths($doc->asXML()), 'still found as the seed CD-ROM');
    }

    public function testNewDomainHasSparePcieRootPorts(): void
    {
        $doc = simplexml_load_string(domain_new_xml('vm', 512, 1, '/d.qcow2', '', 'net'));
        $this->assertSame('pcie-root', (string)$doc->xpath("/domain/devices/controller[@index='0']")[0]['model']);
        $this->assertCount(8, $doc->xpath("/domain/devices/controller[@model='pcie-root-port']"));
    }

    public function testNewDomainXmlWithoutIso(): void
    {
        $doc = simplexml_load_string(domain_new_xml('vm', 512, 1, '/d.qcow2', '', 'net'));
        $this->assertCount(1, $doc->devices->disk);
    }

    public function testSpiceToVnc(): void
    {
        $xml = "<domain><devices>
            <channel type='spicevmc'><target type='virtio' name='com.redhat.spice.0'/></channel>
            <channel type='unix'><target type='virtio' name='org.qemu.guest_agent.0'/></channel>
            <graphics type='spice' autoport='yes'/>
            <audio id='1' type='spice'/>
            <redirdev bus='usb' type='spicevmc'/>
        </devices></domain>";
        $doc = simplexml_load_string((string)domain_xml_spice_to_vnc($xml));
        $this->assertSame('vnc', (string)$doc->devices->graphics['type']);
        $this->assertCount(1, $doc->devices->graphics);
        $this->assertCount(1, $doc->devices->channel, 'guest agent channel is kept');
        $this->assertSame('unix', (string)$doc->devices->channel['type']);
        $this->assertCount(0, $doc->devices->redirdev);
        $this->assertSame('none', (string)$doc->devices->audio['type']);
    }

    public function testSpiceToVncWithoutSpiceReturnsNull(): void
    {
        $this->assertNull(domain_xml_spice_to_vnc("<domain><devices><graphics type='vnc'/></devices></domain>"));
        $this->assertNull(domain_xml_spice_to_vnc('not xml'));
    }

    public function testSnapshotXmlEscapesDescription(): void
    {
        $doc = simplexml_load_string(domain_snapshot_xml('s1', '<b>&"x"</b>'));
        $this->assertSame('<b>&"x"</b>', (string)$doc->description);
    }

    public function testVolumeXml(): void
    {
        $doc = simplexml_load_string(volume_new_xml('disk.qcow2', 20));
        $this->assertSame('20', (string)$doc->capacity);
        $this->assertSame('G', (string)$doc->capacity['unit']);
        $this->assertSame('qcow2', (string)$doc->target->format['type']);
    }

    public function testDomainState(): void
    {
        $this->assertSame('running', domain_state(1)['label']);
        $this->assertSame('unknown', domain_state(99)['label']);
    }

    public function testFirmwareAndTpm(): void
    {
        $bios = simplexml_load_string(domain_new_xml('vm', 512, 1, '/d.qcow2', '', 'net'));
        $this->assertSame('', (string)$bios->os['firmware']);
        $this->assertCount(0, $bios->devices->tpm);

        $efi = simplexml_load_string(domain_new_xml('vm', 512, 1, '/d.qcow2', '', 'net', ['hd'], 'sata', 0, 'efi'));
        $this->assertSame('efi', (string)$efi->os['firmware']);
        $this->assertSame('no', (string)$efi->xpath("/domain/os/firmware/feature[@name='secure-boot']")[0]['enabled']);
        $this->assertCount(0, $efi->features->smm);

        $secure = simplexml_load_string(domain_new_xml('vm', 512, 1, '/d.qcow2', '', 'net', ['hd'], 'sata', 0, 'efi-secure', true));
        $this->assertSame('yes', (string)$secure->xpath("/domain/os/firmware/feature[@name='secure-boot']")[0]['enabled']);
        $this->assertSame('yes', (string)$secure->xpath("/domain/os/firmware/feature[@name='enrolled-keys']")[0]['enabled']);
        $this->assertSame('on', (string)$secure->features->smm['state']);
        $this->assertSame('2.0', (string)$secure->devices->tpm->backend['version']);
        // the boot order stays inside <os>
        $this->assertSame('hd', (string)$secure->os->boot['dev']);
    }

    public function testImportedRawDiskAndPxeBoot(): void
    {
        $doc = simplexml_load_string(domain_new_xml('vm', 512, 1, '/p/imported.img', '', 'net', ['network', 'hd'], 'sata', 0, 'bios', false, 'raw'));
        $this->assertSame('raw', (string)$doc->devices->disk[0]->driver['type']);
        $this->assertSame(['network', 'hd'], [(string)$doc->os->boot[0]['dev'], (string)$doc->os->boot[1]['dev']]);
        $this->assertCount(1, $doc->devices->disk, 'no CD-ROM without an ISO');
        $this->assertSame('qcow2', (string)simplexml_load_string(domain_new_xml('vm', 512, 1, '/p/d.qcow2', '', 'net'))->devices->disk[0]->driver['type']);
    }

    public function testWindowsProfile(): void
    {
        $win = simplexml_load_string(domain_new_xml('win', 4096, 2, '/p/w.qcow2', '/p/win11.iso', 'default', ['cdrom', 'hd'], 'sata', 0,
            'efi-secure', true, 'qcow2', ['windows' => true, 'disk_bus' => 'sata', 'nic_model' => 'e1000e']));
        $this->assertSame('on', (string)$win->features->hyperv->relaxed['state']);
        $this->assertSame('localtime', (string)$win->clock['offset']);
        $this->assertSame('sata', (string)$win->devices->disk[0]->target['bus']);
        $this->assertSame('sdc', (string)$win->devices->disk[0]->target['dev'], 'no clash with the CD-ROMs');
        $this->assertSame('e1000e', (string)$win->devices->interface->model['type']);

        $drivers = simplexml_load_string(domain_new_xml('win', 4096, 2, '/p/w.qcow2', '/p/win11.iso', 'default', ['cdrom', 'hd'], 'sata', 0,
            'efi-secure', true, 'qcow2', ['windows' => true, 'drivers_iso' => '/p/virtio-win.iso']));
        $this->assertCount(3, $drivers->devices->disk);
        $this->assertSame('/p/virtio-win.iso', (string)$drivers->devices->disk[2]->source['file']);
        $this->assertSame('vda', (string)$drivers->devices->disk[0]->target['dev']);

        $linux = simplexml_load_string(domain_new_xml('l', 1024, 1, '/p/l.qcow2', '', 'default'));
        $this->assertCount(0, $linux->features->hyperv);
        $this->assertSame('utc', (string)$linux->clock['offset']);
        foreach (DOMAIN_OS_PROFILES as $p) {
            $this->assertArrayHasKey($p['firmware'], DOMAIN_FIRMWARES);
        }
    }
}
