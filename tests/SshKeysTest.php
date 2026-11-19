<?php

use PHPUnit\Framework\TestCase;

final class SshKeysTest extends TestCase
{
    // test-only keys, fingerprints from "ssh-keygen -l"
    private const ED25519 = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIKOce9sB20EPmdUXHlnhYEqlPATPqnV8LTqc25VczTin me@laptop';
    private const ED25519_FP = 'SHA256:6I+kh3+vyDwBCBY6QOPBXEHk41G05bpaKpDzFSLM9Ag';
    private const RSA = 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQDCtrBHD28OrzeowKx9Iz8kAcz1il45Gn4mJ6LiUHG9EZkoZYjFoXmxXbj9QSXdvgSgYnnLOjm05JLt2f7Q6mKucFO5hLJr58zITWZ/aP6fsIpn3KGZUScWT+Fcngtv0UQaU2VxVausP3zg/BI5A0KJLnAgT4+clDvpRp/t+DRLvspSFbxkMSAY7SQLb8AACK12jPZZosg7Kd8g2QsDYQgb50rlefJD3K3uZsl6yVNJ9aJwV8jZZmCUQjf2WTTIhvmb7NO/l8dLOsPy+Rkfu9enLM+hWPPuzZQ8GZBKEqHJSVWFD4ZDEr9kcvmcsVfNUzPBYKbO+tREY+WIcxKGeskz';
    private const RSA_FP = 'SHA256:LzhIB2qkJLTJYHcGGGHt8CgDTzOqiUpJah7s1fHO3pc';

    private function user(string $name): int
    {
        db_query('DELETE FROM users WHERE username = ?', [$name]);
        return user_create($name, password_hash('x', PASSWORD_DEFAULT), 'operator');
    }

    public function testParseAndFingerprint(): void
    {
        $key = ssh_key_parse('  '.self::ED25519.'  ');
        $this->assertSame('ssh-ed25519', $key['type']);
        $this->assertSame(self::ED25519, $key['key']);
        $this->assertSame('me@laptop', $key['comment']);
        $this->assertSame(self::ED25519_FP, $key['fingerprint']);
        $this->assertSame(self::RSA_FP, ssh_key_parse(self::RSA)['fingerprint']);
    }

    public function testParseRejectsInvalidKeys(): void
    {
        $this->assertNull(ssh_key_parse(''));
        $this->assertNull(ssh_key_parse('not a key'));
        $this->assertNull(ssh_key_parse(self::ED25519."\n".self::RSA), 'one key only');
        // valid base64, but the blob says ssh-ed25519 while the line says ssh-rsa
        $this->assertNull(ssh_key_parse('ssh-rsa'.substr(self::ED25519, strlen('ssh-ed25519'))));
        $this->assertNull(ssh_key_parse('ssh-ed25519 AAAA'));
    }

    public function testAddListDeletePerUser(): void
    {
        $alice = $this->user('alice-keys');
        $bob = $this->user('bob-keys');

        $this->assertNull(ssh_key_add($alice, '', self::ED25519));
        $this->assertNull(ssh_key_add($alice, 'work', self::RSA));
        $this->assertNotNull(ssh_key_add($alice, 'again', self::ED25519), 'duplicate');
        $this->assertNotNull(ssh_key_add($alice, 'bad', 'ssh-rsa xyz'));
        $this->assertNull(ssh_key_add($bob, '', self::ED25519), 'other users may save the same key');

        $list = ssh_key_list($alice);
        $this->assertSame(['me@laptop', 'work'], array_column($list, 'name'), 'name defaults to the key comment');
        $ids = array_column($list, 'id');
        $bob_id = ssh_key_list($bob)[0]['id'];

        // only own keys are returned and deleted
        $this->assertSame([self::ED25519, self::RSA], ssh_keys_by_ids($alice, [$ids[0], $ids[1], $bob_id]));
        $this->assertSame([], ssh_keys_by_ids($alice, []));
        $this->assertFalse(ssh_key_delete($alice, $bob_id));
        $this->assertTrue(ssh_key_delete($alice, $ids[0]));
        $this->assertCount(1, ssh_key_list($alice));

        // keys go away with the user
        user_delete($bob);
        $this->assertSame([], ssh_key_list($bob));
        user_delete($alice);
    }
}
