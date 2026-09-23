<?php

use PHPUnit\Framework\TestCase;

final class UsersTest extends TestCase
{
    public function testRoleHierarchy(): void
    {
        $this->assertTrue(role_allows('viewer', 'view'));
        $this->assertFalse(role_allows('viewer', 'operate'));
        $this->assertTrue(role_allows('operator', 'operate'));
        $this->assertFalse(role_allows('operator', 'admin'));
        $this->assertTrue(role_allows('admin', 'admin'));
        $this->assertFalse(role_allows('unknown', 'view'));
        // unknown permissions require the admin role
        $this->assertTrue(role_allows('admin', 'no-such-permission'));
        $this->assertFalse(role_allows('operator', 'no-such-permission'));
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
}
