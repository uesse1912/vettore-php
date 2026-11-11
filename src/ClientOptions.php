<?php

declare(strict_types=1);

namespace Vettore;

use Closure;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Opzioni del client, immutabili: ogni with*() restituisce una copia.
 *
 *   $options = ClientOptions::defaults()->withTimeout(20)->withMaxRetries(3);
 */
final class ClientOptions
{
    public const DEFAULT_BASE_URL = 'https://api.vettoreapi.com/v1';

    /**
     * @param  float  $timeout  secondi complessivi per richiesta
     * @param  float  $connectTimeout  secondi per aprire la connessione
     * @param  int  $maxRetries  nuovi tentativi (oltre al primo) su 429, 5xx ed errori di rete per le richieste ripetibili
     * @param  int  $retryBaseDelayMs  attesa prima del primo nuovo tentativo; raddoppia a ogni tentativo (jitter incluso)
     * @param  int  $retryMaxDelayMs  tetto all'attesa fra due tentativi, anche quando il server chiede di piu' con Retry-After
     * @param  ClientInterface|null  $httpClient  client Guzzle gia' configurato (nei test: con un MockHandler)
     * @param  Closure(int): void|null  $sleeper  funzione che attende N millisecondi (nei test: una no-op)
     */
    public function __construct(
        public readonly string $baseUrl = self::DEFAULT_BASE_URL,
        public readonly float $timeout = 15.0,
        public readonly float $connectTimeout = 5.0,
        public readonly int $maxRetries = 2,
        public readonly int $retryBaseDelayMs = 500,
        public readonly int $retryMaxDelayMs = 8000,
        public readonly ?LoggerInterface $logger = null,
        public readonly ?ClientInterface $httpClient = null,
        public readonly ?Closure $sleeper = null,
        public readonly string $userAgent = 'vettore-php/'.Vettore::VERSION,
    ) {
        if (! filter_var($this->baseUrl, FILTER_VALIDATE_URL) || ! str_starts_with($this->baseUrl, 'http')) {
            throw new InvalidArgumentException('baseUrl non valido: '.$this->baseUrl);
        }
        if ($this->maxRetries < 0 || $this->maxRetries > 10) {
            throw new InvalidArgumentException('maxRetries deve essere fra 0 e 10');
        }
        if ($this->timeout <= 0 || $this->connectTimeout <= 0) {
            throw new InvalidArgumentException('timeout e connectTimeout devono essere positivi');
        }
    }

    public static function defaults(): self
    {
        return new self;
    }

    public function withBaseUrl(string $baseUrl): self
    {
        return $this->copy(['baseUrl' => rtrim($baseUrl, '/')]);
    }

    public function withTimeout(float $timeout, ?float $connectTimeout = null): self
    {
        return $this->copy(['timeout' => $timeout, 'connectTimeout' => $connectTimeout ?? $this->connectTimeout]);
    }

    public function withMaxRetries(int $maxRetries): self
    {
        return $this->copy(['maxRetries' => $maxRetries]);
    }

    public function withRetryDelays(int $baseDelayMs, int $maxDelayMs): self
    {
        return $this->copy(['retryBaseDelayMs' => $baseDelayMs, 'retryMaxDelayMs' => $maxDelayMs]);
    }

    public function withLogger(?LoggerInterface $logger): self
    {
        return $this->copy(['logger' => $logger]);
    }

    public function withHttpClient(?ClientInterface $httpClient): self
    {
        return $this->copy(['httpClient' => $httpClient]);
    }

    /** @param  Closure(int): void|null  $sleeper */
    public function withSleeper(?Closure $sleeper): self
    {
        return $this->copy(['sleeper' => $sleeper]);
    }

    /** @param  array<string, mixed>  $changes */
    private function copy(array $changes): self
    {
        $current = [
            'baseUrl' => $this->baseUrl,
            'timeout' => $this->timeout,
            'connectTimeout' => $this->connectTimeout,
            'maxRetries' => $this->maxRetries,
            'retryBaseDelayMs' => $this->retryBaseDelayMs,
            'retryMaxDelayMs' => $this->retryMaxDelayMs,
            'logger' => $this->logger,
            'httpClient' => $this->httpClient,
            'sleeper' => $this->sleeper,
            'userAgent' => $this->userAgent,
        ];

        return new self(...array_replace($current, $changes));
    }
}
