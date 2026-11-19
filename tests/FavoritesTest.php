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
}
