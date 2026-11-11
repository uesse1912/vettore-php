<?php

declare(strict_types=1);

namespace Vettore\Http;

use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions as Guzzle;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use Vettore\ClientOptions;
use Vettore\Exceptions\ApiException;
use Vettore\Exceptions\TransportException;
use Vettore\RequestOptions;

/**
 * Esegue le chiamate HTTP verso l'API: Bearer, JSON, intestazioni per richiesta, retry con backoff,
 * decodifica della busta e conversione degli errori in eccezioni tipizzate.
 */
final class HttpTransport
{
    private readonly ClientInterface $http;

    private readonly RetryPolicy $retry;

    private readonly LoggerInterface $logger;

    /** @var Closure(int): void */
    private readonly Closure $sleep;

    public function __construct(
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly ClientOptions $options,
    ) {
        $this->http = $options->httpClient ?? new Client([
            Guzzle::TIMEOUT => $options->timeout,
            Guzzle::CONNECT_TIMEOUT => $options->connectTimeout,
            Guzzle::HTTP_ERRORS => false,
            Guzzle::ALLOW_REDIRECTS => false,
        ]);
        $this->retry = new RetryPolicy($options->maxRetries, $options->retryBaseDelayMs, $options->retryMaxDelayMs);
        $this->logger = $options->logger ?? new NullLogger;
        $this->sleep = $options->sleeper ?? static fn (int $ms) => usleep($ms * 1000);
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @param  array<string, mixed>|null  $json
     */
    public function request(string $method, string $path, array $query = [], ?array $json = null, ?RequestOptions $options = null): ApiResult
    {
        $options ??= RequestOptions::none();
        $url = $this->options->baseUrl.'/'.ltrim($path, '/');
        $guzzleOptions = $this->guzzleOptions($query, $json, $options);
        $repeatable = $this->retry->isRepeatable($method, $options->idempotencyKey !== null);

        $attempt = 0;
        while (true) {
            $attempt++;
            try {
                $response = $this->http->request($method, $url, $guzzleOptions);
            } catch (ConnectException|RequestException $e) {
                if ($repeatable && $this->retry->shouldRetry($attempt, null, true)) {
                    $this->wait($attempt, null, $method, $path, 'rete: '.$e->getMessage());

                    continue;
                }
                throw new TransportException('Richiesta fallita verso '.$method.' '.$path.': '.$e->getMessage(), $options->requestId, $e);
            } catch (Throwable $e) {
                throw new TransportException('Richiesta fallita verso '.$method.' '.$path.': '.$e->getMessage(), $options->requestId, $e);
            }

            $status = $response->getStatusCode();
            if ($repeatable && $this->retry->shouldRetry($attempt, $status, false)) {
                $this->wait($attempt, $response->getHeaderLine('Retry-After') ?: null, $method, $path, 'HTTP '.$status);

                continue;
            }

            return $this->decode($response, $method, $path);
        }
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    private function guzzleOptions(array $query, ?array $json, RequestOptions $options): array
    {
        $headers = [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => $this->options->userAgent,
        ] + $options->headers();

        $guzzle = [Guzzle::HEADERS => $headers];
        $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
        if ($query !== []) {
            $guzzle[Guzzle::QUERY] = $query;
        }
        if ($json !== null) {
            $guzzle[Guzzle::JSON] = $json;
        }

        return $guzzle;
    }

    private function wait(int $attempt, ?string $retryAfter, string $method, string $path, string $reason): void
    {
        $delay = $this->retry->delayMs($attempt, $retryAfter);
        $this->logger->info('vettore.sdk.retry', ['method' => $method, 'path' => $path, 'attempt' => $attempt, 'delay_ms' => $delay, 'reason' => $reason]);
        ($this->sleep)($delay);
    }

    private function decode(ResponseInterface $response, string $method, string $path): ApiResult
    {
        $status = $response->getStatusCode();
        $headers = $this->flattenHeaders($response);
        $raw = (string) $response->getBody();

        if ($status === 204 || trim($raw) === '') {
            if ($status >= 200 && $status < 300) {
                return new ApiResult($status, null, ['request_id' => $headers['x-request-id'] ?? null], $headers);
            }
            throw ApiException::fromResponse($status, [], $headers);
        }

        $body = json_decode($raw, true);
        if (! is_array($body)) {
            if ($status >= 400) {
                throw ApiException::fromResponse($status, [], $headers);
            }
            throw new TransportException('Risposta non JSON da '.$method.' '.$path.' (HTTP '.$status.')', $headers['x-request-id'] ?? null);
        }

        if ($status >= 400 || ($body['status'] ?? null) === 'error') {
            $this->logger->warning('vettore.sdk.api_error', ['method' => $method, 'path' => $path, 'status' => $status, 'code' => $body['error']['code'] ?? null, 'request_id' => $body['meta']['request_id'] ?? null]);
            throw ApiException::fromResponse($status, $body, $headers);
        }

        $meta = is_array($body['meta'] ?? null) ? $body['meta'] : [];

        return new ApiResult($status, $body['data'] ?? null, $meta, $headers);
    }

    /** @return array<string, string> */
    private function flattenHeaders(ResponseInterface $response): array
    {
        $flat = [];
        foreach ($response->getHeaders() as $name => $values) {
            $flat[strtolower((string) $name)] = implode(', ', $values);
        }

        return $flat;
    }
}
