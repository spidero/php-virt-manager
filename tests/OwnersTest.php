<?php

use PHPUnit\Framework\TestCase;

final class OwnersTest extends TestCase
{
    private function user(string $name, string $role): array
    {
        db_query('DELETE FROM users WHERE username = ?', [$name]);
        return user_find(user_create($name, password_hash('x', PASSWORD_DEFAULT), $role));
    }

    public function testMachineAccessByRoleAndOwner(): void
    {
        db_query('DELETE FROM machine_owners');
        $ann = $this->user('own-ann', 'user');
        $lea = $this->user('own-lea', 'localadmin');
        $vic = $this->user('own-vic', 'viewer');
        $ops = $this->user('own-ops', 'operator');
        $adm = $this->user('own-adm', 'admin');
        machine_owner_set('local', 'web', $ann['id']);
        machine_owner_set('local', 'db', $lea['id']);

        // [user, machine] => [view, operate, delete]
        $cases = [
            [$ann, 'web', [true, true, false]],   // user: own machine, no delete
            [$ann, 'db', [false, false, false]],  // someone else's machine
            [$lea, 'db', [true, true, true]],     // localadmin deletes own machines
            [$lea, 'web', [false, false, false]],
            [$vic, 'web', [true, false, false]],
            [$ops, 'web', [true, true, false]],   // operators do not delete
            [$adm, 'db', [true, true, true]],
        ];
        foreach ($cases as [$user, $machine, $expected]) {
            $got = array_map(fn($a) => machine_allowed($a, $machine, $user, 'local'), ['view', 'operate', 'delete']);
            $this->assertSame($expected, $got, $user['username'].' '.$machine);
        }
        // ownership is per connection
        $this->assertFalse(machine_allowed('view', 'web', $ann, 'remote'));
        $this->assertSame(['web'], machine_owned_names('local', $ann['id']));

        // rename and migration keep the owner, deletion removes it
        machine_owner_rename('local', 'web', 'web2');
        $this->assertSame($ann['id'], machine_owner_id('local', 'web2'));
        machine_owner_move('local', 'remote', 'web2');
        $this->assertSame($ann['id'], machine_owner_id('remote', 'web2'));
        machine_owner_set('remote', 'web2', null);
        $this->assertNull(machine_owner_id('remote', 'web2'));
        // deleting the user removes the ownership
        user_delete($lea['id']);
        $this->assertNull(machine_owner_id('local', 'db'));
        foreach ([$ann, $vic, $ops, $adm] as $u) {
            user_delete($u['id']);
        }
    }

    public function testQuotas(): void
    {
        $quotas = ['machines' => 2, 'vcpus' => 4, 'memory_mb' => 4096, 'disk_gb' => null];
        $usage = ['machines' => 1, 'vcpus' => 2, 'memory_mb' => 2048, 'disk_gb' => 500];
        $this->assertNull(quota_error($quotas, $usage, ['machines' => 1, 'vcpus' => 2, 'memory_mb' => 2048, 'disk_gb' => 1000]), 'exactly at the limit, disk unlimited');
        $this->assertNotNull(quota_error($quotas, $usage, ['machines' => 1, 'vcpus' => 3]));
        $this->assertNotNull(quota_error($quotas, $usage, ['memory_mb' => 4096]));
        $this->assertNull(quota_error($quotas, ['machines' => 5] + $usage, ['vcpus' => 0]), 'nothing added: over-quota users can still work');
        $u = ['quota_machines' => 3, 'quota_vcpus' => null, 'quota_memory_mb' => 1024, 'quota_disk_gb' => 0];
        $this->assertSame(['machines' => 3, 'vcpus' => null, 'memory_mb' => 1024, 'disk_gb' => 0], user_quotas($u));
    }
}
