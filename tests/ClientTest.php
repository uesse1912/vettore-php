<?php

declare(strict_types=1);

namespace Vettore\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vettore\ClientOptions;
use Vettore\Exceptions\TransportException;
use Vettore\RequestOptions;
use Vettore\Tests\Support\FakeApi;
use Vettore\Tests\Support\Fixtures;
use Vettore\Vettore;

final class ClientTest extends TestCase
{
    #[Test]
    public function sends_bearer_token_json_accept_and_user_agent(): void
    {
        $api = FakeApi::create('vt_test_abc123')->success(['id' => 'acc_1', 'name' => 'Integratore', 'environment' => 'test']);

        $account = $api->client->me();

        $request = $api->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://api.vettoreapi.com/v1/me', (string) $request->getUri());
        self::assertSame('Bearer vt_test_abc123', $request->getHeaderLine('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('vettore-php/'.Vettore::VERSION, $request->getHeaderLine('User-Agent'));
        self::assertSame('acc_1', $account->id);
        self::assertTrue($account->isTest());
    }

    #[Test]
    public function base_url_is_configurable_without_trailing_slash_issues(): void
    {
        $api = FakeApi::create('vt_live_xyz', ClientOptions::defaults()->withBaseUrl('https://sandbox.example.test/v1/'))
            ->success(['id' => 'acc_1']);

        $api->client->me();

        self::assertSame('https://sandbox.example.test/v1/me', (string) $api->lastRequest()->getUri());
    }

    #[Test]
    public function idempotency_key_and_request_id_travel_as_headers(): void
    {
        $api = FakeApi::create()->success(Fixtures::merchant(), 201);

        $api->client->merchants()->create(['name' => 'Beauty Shop'], RequestOptions::idempotent('crea-merchant-1', 'req_cliente_1'));

        $request = $api->lastRequest();
        self::assertSame('crea-merchant-1', $request->getHeaderLine('Idempotency-Key'));
        self::assertSame('req_cliente_1', $request->getHeaderLine('X-Request-Id'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame(['name' => 'Beauty Shop'], $api->lastJson());
    }

    #[Test]
    public function request_options_validate_their_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RequestOptions::idempotent(str_repeat('x', 129));
    }

    #[Test]
    public function request_id_must_match_the_server_pattern(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RequestOptions(null, 'con spazi');
    }

    #[Test]
    public function api_key_format_is_checked_up_front(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Vettore('sk_live_sbagliata');
    }

    #[Test]
    public function invalid_options_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ClientOptions::defaults()->withMaxRetries(99);
    }

    #[Test]
    public function null_query_values_are_dropped(): void
    {
        $api = FakeApi::create()->success([]);

        $api->client->shipments()->list(['state' => 'ON_HOLD', 'carrier' => null, 'order_ref' => ''], 25);

        self::assertSame('state=ON_HOLD&limit=25', urldecode($api->lastRequest()->getUri()->getQuery()));
    }

    #[Test]
    public function non_json_success_body_is_a_transport_error(): void
    {
        $api = FakeApi::create()->raw(new Response(200, ['Content-Type' => 'text/html'], '<html>proxy</html>'));

        $this->expectException(TransportException::class);
        $api->client->me();
    }

    #[Test]
    public function network_failures_become_transport_exceptions_with_the_cause_attached(): void
    {
        $api = FakeApi::create(options: ClientOptions::defaults()->withMaxRetries(0))
            ->throws(new ConnectException('timeout', new Request('GET', '/me')));

        try {
            $api->client->me();
            self::fail('attesa TransportException');
        } catch (TransportException $e) {
            self::assertStringContainsString('GET me', $e->getMessage());
            self::assertInstanceOf(ConnectException::class, $e->getPrevious());
        }
    }
}
