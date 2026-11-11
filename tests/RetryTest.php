<?php

declare(strict_types=1);

namespace Vettore\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vettore\ClientOptions;
use Vettore\Exceptions\NotFoundException;
use Vettore\Exceptions\RateLimitException;
use Vettore\Exceptions\ServerException;
use Vettore\Http\RetryPolicy;
use Vettore\RequestOptions;
use Vettore\Tests\Support\FakeApi;
use Vettore\Tests\Support\Fixtures;

final class RetryTest extends TestCase
{
    #[Test]
    public function get_is_retried_on_429_honouring_retry_after(): void
    {
        $api = FakeApi::create()
            ->error(429, 'rate_limited', 'Troppe richieste', headers: ['Retry-After' => '2'])
            ->success(['id' => 'acc_1']);

        $account = $api->client->me();

        self::assertSame('acc_1', $account->id);
        self::assertSame(2, $api->requestCount());
        self::assertSame([2000], $api->sleeps);
    }

    #[Test]
    public function get_is_retried_on_5xx_with_exponential_backoff_then_gives_up(): void
    {
        $api = FakeApi::create(options: ClientOptions::defaults()->withMaxRetries(2)->withRetryDelays(100, 10000))
            ->error(500, 'internal_error')
            ->error(502, 'internal_error')
            ->error(503, 'internal_error');

        try {
            $api->client->me();
            self::fail('attesa ServerException');
        } catch (ServerException $e) {
            self::assertSame(503, $e->status);
            self::assertSame('req_err', $e->requestId);
        }
        self::assertSame(3, $api->requestCount());
        self::assertCount(2, $api->sleeps);
        self::assertGreaterThanOrEqual(100, $api->sleeps[0]);
        self::assertGreaterThanOrEqual(200, $api->sleeps[1]);
        self::assertGreaterThan($api->sleeps[0], $api->sleeps[1]);
    }

    #[Test]
    public function retry_after_is_capped_by_the_configured_maximum(): void
    {
        $api = FakeApi::create(options: ClientOptions::defaults()->withRetryDelays(100, 1500))
            ->error(429, 'rate_limited', headers: ['Retry-After' => '60'])
            ->success(['id' => 'acc_1']);

        $api->client->me();

        self::assertSame([1500], $api->sleeps);
    }

    #[Test]
    public function post_without_idempotency_key_is_never_retried(): void
    {
        $api = FakeApi::create()->error(503, 'internal_error');

        $this->expectException(ServerException::class);
        try {
            $api->client->merchants()->create(['name' => 'Uno']);
        } finally {
            self::assertSame(1, $api->requestCount());
            self::assertSame([], $api->sleeps);
        }
    }

    #[Test]
    public function post_with_idempotency_key_is_retried(): void
    {
        $api = FakeApi::create()
            ->error(500, 'internal_error')
            ->success(Fixtures::merchant(), 201);

        $merchant = $api->client->merchants()->create(['name' => 'Beauty Shop'], RequestOptions::idempotent('crea-1'));

        self::assertSame('Beauty Shop', $merchant->name);
        self::assertSame(2, $api->requestCount());
        self::assertSame('crea-1', $api->lastRequest()->getHeaderLine('Idempotency-Key'));
    }

    #[Test]
    public function network_errors_are_retried_for_repeatable_requests(): void
    {
        $api = FakeApi::create()
            ->throws(new ConnectException('timeout', new Request('GET', '/me')))
            ->success(['id' => 'acc_1']);

        self::assertSame('acc_1', $api->client->me()->id);
        self::assertSame(2, $api->requestCount());
    }

    #[Test]
    public function with_zero_retries_a_429_surfaces_immediately_with_retry_after(): void
    {
        $api = FakeApi::create(options: ClientOptions::defaults()->withMaxRetries(0))
            ->error(429, 'rate_limited', 'Troppe richieste', headers: ['Retry-After' => '7']);

        try {
            $api->client->me();
            self::fail('attesa RateLimitException');
        } catch (RateLimitException $e) {
            self::assertSame(7, $e->retryAfter());
            self::assertSame('rate_limited', $e->errorCode);
        }
        self::assertSame(1, $api->requestCount());
    }

    #[Test]
    public function four_xx_other_than_429_are_not_retried(): void
    {
        $api = FakeApi::create()->error(404, 'not_found');

        $this->expectException(NotFoundException::class);
        try {
            $api->client->me();
        } finally {
            self::assertSame(1, $api->requestCount());
        }
    }

    #[Test]
    public function policy_classifies_methods_and_statuses(): void
    {
        $policy = new RetryPolicy(2, 100, 1000);

        self::assertTrue($policy->isRepeatable('GET', false));
        self::assertTrue($policy->isRepeatable('DELETE', false));
        self::assertTrue($policy->isRepeatable('PATCH', false));
        self::assertFalse($policy->isRepeatable('POST', false));
        self::assertTrue($policy->isRepeatable('POST', true));

        self::assertTrue($policy->shouldRetry(1, 429, false));
        self::assertTrue($policy->shouldRetry(1, 500, false));
        self::assertTrue($policy->shouldRetry(1, null, true));
        self::assertFalse($policy->shouldRetry(1, 422, false));
        self::assertTrue($policy->shouldRetry(2, 500, false));
        self::assertFalse($policy->shouldRetry(3, 500, false));
        self::assertSame(1000, $policy->delayMs(1, '5'));
        self::assertLessThanOrEqual(1000, $policy->delayMs(5, null));
    }
}
