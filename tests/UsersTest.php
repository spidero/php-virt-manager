<?php

use PHPUnit\Framework\TestCase;

final class UsersTest extends TestCase
{
    public function testRolePermissions(): void
    {
        $matrix = [
            'user'       => ['own', 'create'],
            'localadmin' => ['own', 'create', 'delete_own'],
            'viewer'     => ['view'],
            'auditor'    => ['view', 'audit'],
            'operator'   => ['view', 'operate', 'create'],
            'admin'      => ['view', 'operate', 'create', 'audit', 'admin'],
        ];
        foreach ($matrix as $role => $allowed) {
            foreach (array_keys(PERMISSIONS) as $permission) {
                $this->assertSame(in_array($permission, $allowed, true), role_allows($role, $permission), $role.' '.$permission);
            }
        }
        $this->assertSame(array_keys($matrix), array_keys(ROLES));
        $this->assertFalse(role_allows('unknown', 'view'));
        $this->assertFalse(role_allows('admin', 'no-such-permission'));
    }

    public function testBootstrapCreatesFirstAdminOnce(): void
    {
        // the bootstrap only acts on an empty user table
        db_query('DELETE FROM users');
        users_bootstrap('boss', password_hash('secret-pass', PASSWORD_DEFAULT));
        users_bootstrap('other', password_hash('secret-pass', PASSWORD_DEFAULT));
        $boss = user_find_by_name('BOSS');
        $this->assertNotNull($boss, 'usernames are case insensitive');
        $this->assertSame('admin', $boss['role']);
        $this->assertNull(user_find_by_name('other'));
    }

    public function testSetPasswordOverwritesOrCreates(): void
    {
        // unlike the bootstrap, an explicit password applies to an existing account too
        db_query('DELETE FROM users');
        users_bootstrap('boss', password_hash('first-pass', PASSWORD_DEFAULT));
        $id = (int)user_find_by_name('boss')['id'];
        user_update($id, ['role' => 'operator']);
        $this->assertSame('updated', user_set_password('Boss', password_hash('second-pass', PASSWORD_DEFAULT)));
        $boss = user_find($id);
        $this->assertTrue(password_verify('second-pass', $boss['password_hash']));
        $this->assertSame('operator', $boss['role'], 'role is kept');

        $this->assertSame('created', user_set_password('newadmin', password_hash('third-pass', PASSWORD_DEFAULT)));
        $this->assertSame('admin', user_find_by_name('newadmin')['role']);
    }

    public function testCreateUpdateDelete(): void
    {
        $id = user_create('tester', password_hash('x', PASSWORD_DEFAULT), 'viewer');
        user_update($id, ['role' => 'operator', 'lang' => 'pl', 'username' => 'ignored']);
        $user = user_find($id);
        $this->assertSame('operator', $user['role']);
        $this->assertSame('pl', $user['lang']);
        $this->assertSame('tester', $user['username']);
        user_delete($id);
        $this->assertNull(user_find($id));
    }

    public function testInvalidRoleIsRejectedByDatabase(): void
    {
        $this->expectException(PDOException::class);
        user_create('bad-role', 'x', 'root');
    }

    public function testPasswordValidation(): void
    {
        $this->assertNotNull(user_validate_password('short'));
        $this->assertNull(user_validate_password('long enough'));
    }

    public function testDummyHashIsRealBcrypt(): void
    {
        $this->assertSame('bcrypt', password_get_info(LOGIN_DUMMY_HASH)['algoName']);
    }

    public function testDismissedNoticesPerUser(): void
    {
        $this->assertFalse(notice_dismissed(901, 'contact'));
        notice_dismiss(901, 'contact');
        $this->assertTrue(notice_dismissed(901, 'contact'));
        $this->assertFalse(notice_dismissed(902, 'contact'), 'per user');
        notice_dismiss(901, 'unknown-notice');
        $this->assertFalse(notice_dismissed(901, 'unknown-notice'), 'only known notices');
    }
}
