<?php

namespace Tests\Unit;

use App\Http\Middleware\CronicleToken;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class CronicleTokenTest extends TestCase
{
    private function middleware(mixed $secret): CronicleToken
    {
        return new CronicleToken(new Repository(['cronicle' => ['secret' => $secret]]));
    }

    public function test_only_an_exact_current_header_credential_can_reach_the_action(): void
    {
        $secret = str_repeat('a', 64);
        foreach (['Bearer ', 'bearer ', 'BEARER '] as $scheme) {
            $request = Request::create('/internal/cronicle/trigger', 'POST');
            $request->headers->set('Authorization', $scheme.$secret);
            $response = $this->middleware($secret)->handle($request, fn () => new Response('', 204));
            $this->assertSame(204, $response->getStatusCode());
        }
    }

    public function test_query_body_cookie_and_interactive_user_never_supply_the_credential(): void
    {
        $secret = str_repeat('a', 64);
        $request = Request::create('/internal/cronicle/trigger?token='.$secret, 'POST', ['token' => $secret], ['session' => 'fixture']);
        $request->setUserResolver(fn () => (object) ['id' => 1, 'role' => 'admin']);
        $called = false;
        $response = $this->middleware($secret)->handle($request, function () use (&$called) {
            $called = true;

            return new Response('', 204);
        });
        $this->assertSame(401, $response->getStatusCode());
        $this->assertFalse($called);
        $this->assertSame('Bearer', $response->headers->get('WWW-Authenticate'));
        $this->assertStringNotContainsString($secret, $response->getContent());
    }

    public function test_malformed_and_wrong_credentials_fail_before_the_action(): void
    {
        $secret = str_repeat('a', 64);
        foreach (['', 'Basic '.$secret, 'Bearer '.str_repeat('b', 64), 'Bearer '.$secret.' ', 'Bearer  '.$secret, 'Bearer '.str_repeat('a', 65), 'Bearer '.str_repeat('A', 64)] as $header) {
            $request = Request::create('/internal/cronicle/trigger', 'POST');
            $request->headers->set('Authorization', $header);
            $called = false;
            $response = $this->middleware($secret)->handle($request, function () use (&$called) {
                $called = true;

                return new Response('', 204);
            });
            $this->assertSame(401, $response->getStatusCode());
            $this->assertFalse($called);
        }
    }

    public function test_missing_or_invalid_server_configuration_leaves_the_scheduler_disabled(): void
    {
        foreach ([null, '', 123, str_repeat('a', 63), str_repeat('a', 65)] as $secret) {
            $request = Request::create('/internal/cronicle/trigger', 'POST');
            $request->headers->set('Authorization', 'Bearer '.str_repeat('a', 64));
            $response = $this->middleware($secret)->handle($request, fn () => $this->fail('Disabled scheduler executed an action.'));
            $this->assertSame(503, $response->getStatusCode());
        }
    }
}
