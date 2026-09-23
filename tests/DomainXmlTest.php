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
}
