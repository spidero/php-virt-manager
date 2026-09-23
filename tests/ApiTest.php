<?php

use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase
{
    public function testRouteMatchesAndDecodesArguments(): void
    {
        [$handler, $permission, $args] = api_route('POST', '/v1/domains/azure%20linux/actions/start');
        $this->assertSame('api_domain_action', $handler);
        $this->assertSame('operate', $permission);
        $this->assertSame(['azure linux', 'start'], $args);

        [$handler, , $args] = api_route('DELETE', '/v1/domains/vm1/snapshots/snap-1');
        $this->assertSame('api_snapshot_delete', $handler);
        $this->assertSame(['vm1', 'snap-1'], $args);
    }

    public function testReadRoutesNeedViewPermission(): void
    {
        foreach (API_ROUTES as $route) {
            $method = $route[0];
            $handler = $route[2];
            $permission = $route[3];
            if ($method === 'GET' && $handler !== 'api_jobs' && $handler !== 'api_job') {
                $this->assertSame('view', $permission, $handler);
            }
            if ($method !== 'GET') {
                $this->assertNotSame('view', $permission, $handler.' changes state');
            }
        }
    }

    public function testUnknownRouteIs404(): void
    {
        $this->expectExceptionCode(404);
        api_route('GET', '/v1/nothing');
    }

    public function testWrongMethodIs405(): void
    {
        try {
            api_route('PUT', '/v1/domains');
            $this->fail('no exception');
        }
        catch (ApiError $e) {
            $this->assertSame(405, $e->getCode());
            $this->assertStringContainsString('GET', $e->getMessage());
        }
    }

    public function testBearerToken(): void
    {
        $this->assertSame('pvm_abc', api_bearer_token(['HTTP_AUTHORIZATION' => 'Bearer pvm_abc']));
        $this->assertSame('pvm_abc', api_bearer_token(['REDIRECT_HTTP_AUTHORIZATION' => 'bearer  pvm_abc']));
        $this->assertSame('', api_bearer_token(['HTTP_AUTHORIZATION' => 'Basic dXNlcjpwYXNz']));
        $this->assertSame('', api_bearer_token([]));
    }

    public function testTokenLifecycle(): void
    {
        $uid = user_create('api-user', password_hash('x', PASSWORD_DEFAULT), 'operator');
        $token = api_token_create($uid, 'ci');
        $this->assertMatchesRegularExpression('/^pvm_[0-9a-f]{40}$/', $token);
        $this->assertSame('api-user', api_token_user($token)['username']);
        $this->assertNull(api_token_user($token.'0'));
        $this->assertNull(api_token_user('pvm_'.str_repeat('0', 40)));

        $list = api_token_list($uid);
        $this->assertCount(1, $list);
        $this->assertArrayNotHasKey('token_hash', $list[0], 'hash is never listed');
        $this->assertNotNull($list[0]['last_used']);

        $other = user_create('api-other', 'x', 'viewer');
        $this->assertFalse(api_token_delete($other, $list[0]['id']), 'only the owner can revoke');
        $this->assertTrue(api_token_delete($uid, $list[0]['id']));
        $this->assertNull(api_token_user($token));
    }

    public function testTokensAreDeletedWithTheUser(): void
    {
        $uid = user_create('api-gone', 'x', 'viewer');
        $token = api_token_create($uid, 't');
        user_delete($uid);
        $this->assertNull(api_token_user($token));
    }
}
