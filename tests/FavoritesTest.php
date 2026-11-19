<?php

use PHPUnit\Framework\TestCase;

final class FavoritesTest extends TestCase
{
    public function testTogglePerUserAndConnection(): void
    {
        db_query('DELETE FROM users WHERE username IN (?, ?)', ['fav-a', 'fav-b']);
        $a = user_create('fav-a', password_hash('x', PASSWORD_DEFAULT), 'viewer');
        $b = user_create('fav-b', password_hash('x', PASSWORD_DEFAULT), 'viewer');

        $this->assertTrue(favorite_toggle($a, 'local', 'web-2'));
        $this->assertTrue(favorite_toggle($a, 'local', 'db-1'));
        $this->assertTrue(favorite_toggle($a, 'remote', 'web-2'));
        $this->assertSame(['db-1', 'web-2'], favorite_names($a, 'local'));
        $this->assertSame(['web-2'], favorite_names($a, 'remote'), 'per connection');
        $this->assertSame([], favorite_names($b, 'local'), 'per user');

        $this->assertFalse(favorite_toggle($a, 'local', 'web-2'), 'second toggle removes the star');
        $this->assertSame(['db-1'], favorite_names($a, 'local'));

        user_delete($a);
        $this->assertSame([], favorite_names($a, 'remote'), 'removed with the user');
        user_delete($b);
    }

    public function testReferencesFollowMigrationAndRename(): void
    {
        db_query('DELETE FROM users WHERE username = ?', ['fav-m']);
        $u = user_create('fav-m', password_hash('x', PASSWORD_DEFAULT), 'admin');
        favorite_toggle($u, 'local', 'vm1');
        $id = schedule_create('local', 'vm1', 'daily', 3, 0, 7, 'fav-m');
        domain_move_references('local', 'remote', 'vm1');
        $this->assertSame([], favorite_names($u, 'local'));
        $this->assertSame(['vm1'], favorite_names($u, 'remote'));
        $this->assertSame('remote', db_query('SELECT conn FROM schedules WHERE id = ?', [$id])->fetchColumn());
        domain_rename_references('remote', 'vm1', 'vm2');
        $this->assertSame(['vm2'], favorite_names($u, 'remote'));
        $this->assertSame('vm2', db_query('SELECT domain FROM schedules WHERE id = ?', [$id])->fetchColumn());
        db_query('DELETE FROM schedules WHERE id = ?', [$id]);
        user_delete($u);
    }
}
