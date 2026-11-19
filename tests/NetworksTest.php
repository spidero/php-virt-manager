<?php

use PHPUnit\Framework\TestCase;

final class NetworksTest extends TestCase
{
    public function testCidr(): void
    {
        $c = network_cidr('192.168.50.77/24');
        $this->assertSame(['192.168.50.0', 24, '255.255.255.0', '192.168.50.1', '192.168.50.2', '192.168.50.254'],
            [$c['network'], $c['prefix'], $c['netmask'], $c['gateway'], $c['dhcp_start'], $c['dhcp_end']]);
        $this->assertSame('10.10.0.1', network_cidr('10.10.0.0/16')['gateway']);
        $this->assertSame('10.10.255.254', network_cidr('10.10.0.0/16')['dhcp_end']);
        foreach (['', '192.168.50.0', '192.168.50.0/30', '192.168.50.0/7', '300.1.1.1/24', '1.2.3/24', 'a.b.c.d/24'] as $bad) {
            $this->assertNull(network_cidr($bad), $bad);
        }
    }

    public function testUsableAndOverlap(): void
    {
        $c = network_cidr('192.168.50.0/24');
        $this->assertTrue(network_ip_usable('192.168.50.10', $c));
        $this->assertTrue(network_ip_usable('192.168.50.254', $c));
        foreach (['192.168.50.0', '192.168.50.1', '192.168.50.255', '192.168.51.10', 'x', '192.168.50.300'] as $bad) {
            $this->assertFalse(network_ip_usable($bad, $c), $bad);
        }
        $this->assertTrue(network_overlaps('192.168.0.0/16', '192.168.50.0/24'));
        $this->assertTrue(network_overlaps('192.168.50.0/24', '192.168.50.128/25'));
        $this->assertFalse(network_overlaps('192.168.50.0/24', '192.168.51.0/24'));
        $this->assertFalse(network_overlaps('bad', '192.168.51.0/24'));
    }

    public function testNewNetworkXmlAndParsing(): void
    {
        $c = network_cidr('192.168.50.0/24');
        $nat = simplexml_load_string(network_new_xml('lab<1>', 'nat', $c, true));
        $this->assertSame('lab<1>', (string)$nat->name);
        $this->assertSame('nat', (string)$nat->forward['mode']);
        $this->assertSame('192.168.50.1', (string)$nat->ip['address']);
        $this->assertSame('192.168.50.2', (string)$nat->ip->dhcp->range['start']);
        $iso = simplexml_load_string(network_new_xml('iso', 'isolated', $c, false));
        $this->assertCount(0, $iso->forward);
        $this->assertCount(0, $iso->ip->dhcp);

        $xml = simplexml_load_string("<network><name>default</name><ip address='192.168.122.1' netmask='255.255.255.0'>
            <dhcp><range start='192.168.122.2' end='192.168.122.254'/>
            <host mac='52:54:00:AA:BB:CC' name='web' ip='192.168.122.10'/></dhcp></ip>
            <ip family='ipv6' address='fd00::1' prefix='64'/></network>");
        $ipv4 = network_ipv4($xml);
        $this->assertSame('192.168.122.0/24', $ipv4['cidr']);
        $this->assertSame(['start' => '192.168.122.2', 'end' => '192.168.122.254'], $ipv4['range']);
        $this->assertSame([['mac' => '52:54:00:aa:bb:cc', 'ip' => '192.168.122.10', 'name' => 'web']], $ipv4['hosts']);
        $this->assertSame('10.0.0.0/16', network_ipv4(simplexml_load_string("<network><ip address='10.0.0.1' prefix='16'/></network>"))['cidr']);
        $this->assertNull(network_ipv4(simplexml_load_string('<network><name>none</name></network>')));
    }
}
