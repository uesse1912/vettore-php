<?php

declare(strict_types=1);

namespace Vettore\Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use LogicException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Vettore\ClientOptions;
use Vettore\Vettore;

/** API finta: accoda risposte, registra le richieste, nessuna rete e nessuna attesa nei retry. */
final class FakeApi
{
    public readonly Vettore $client;

    public readonly MockHandler $mock;

    /** @var array<int, array{request: RequestInterface, response: ?ResponseInterface}> */
    public array $history = [];

    /** @var list<int> */
    public array $sleeps = [];

    private function __construct(string $apiKey, ClientOptions $options)
    {
        $this->mock = new MockHandler;
        $stack = HandlerStack::create($this->mock);
        $stack->push(function (callable $handler): callable {
            return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($request): ResponseInterface {
                        $this->history[] = ['request' => $request, 'response' => $response];

                        return $response;
                    },
                    function (mixed $reason) use ($request): PromiseInterface {
                        $this->history[] = ['request' => $request, 'response' => null];

                        return Create::rejectionFor($reason);
                    },
                );
            };
        });
        $options = $options
            ->withHttpClient(new Client(['handler' => $stack, 'http_errors' => false]))
            ->withSleeper(function (int $ms): void {
                $this->sleeps[] = $ms;
            });
        $this->client = new Vettore($apiKey, $options);
    }

    public static function create(string $apiKey = 'vt_test_abc123', ?ClientOptions $options = null): self
    {
        return new self($apiKey, $options ?? ClientOptions::defaults());
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, string>  $headers
     */
    public function success(mixed $data, int $status = 200, array $meta = [], array $headers = []): self
    {
        $body = ['status' => 'success', 'data' => $data, 'error' => null, 'meta' => ['request_id' => 'req_test'] + $meta];
        $this->mock->append(new Response($status, ['Content-Type' => 'application/json', 'X-Request-Id' => 'req_test'] + $headers, json_encode($body, JSON_THROW_ON_ERROR)));

        return $this;
    }

    /**
     * @param  list<array{field: string, issue: string}>  $details
     * @param  array<string, string>  $headers
     */
    public function error(int $status, string $code, string $message = 'errore', array $details = [], array $headers = []): self
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== []) {
            $error['details'] = $details;
        }
        $body = ['status' => 'error', 'data' => null, 'error' => $error, 'meta' => ['request_id' => 'req_err']];
        $this->mock->append(new Response($status, ['Content-Type' => 'application/json', 'X-Request-Id' => 'req_err'] + $headers, json_encode($body, JSON_THROW_ON_ERROR)));

        return $this;
    }

    public function raw(Response $response): self
    {
        $this->mock->append($response);

        return $this;
    }

    public function noContent(): self
    {
        $this->mock->append(new Response(204, ['X-Request-Id' => 'req_test']));

        return $this;
    }

    public function throws(Throwable $e): self
    {
        $this->mock->append($e);

        return $this;
    }

    public function lastRequest(): RequestInterface
    {
        $last = end($this->history);
        if ($last === false) {
            throw new LogicException('nessuna richiesta registrata');
        }

        return $last['request'];
    }

    public function requestCount(): int
    {
        return count($this->history);
    }

    /** @return array<string, mixed> */
    public function lastJson(): array
    {
        $body = (string) $this->lastRequest()->getBody();
        if ($body === '') {
            return [];
        }
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
