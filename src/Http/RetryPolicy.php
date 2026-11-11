<?php

declare(strict_types=1);

namespace Vettore\Http;

/**
 * Quando e quanto riprovare. Si riprova su 429, 5xx ed errori di rete, solo per richieste ripetibili:
 * GET, DELETE, PATCH (l'API fonde i campi, ripeterla e' innocuo) e le POST con Idempotency-Key.
 * Attesa esponenziale con jitter, rispettando Retry-After entro il tetto configurato.
 */
final class RetryPolicy
{
    public function __construct(
        private readonly int $maxRetries,
        private readonly int $baseDelayMs,
        private readonly int $maxDelayMs,
    ) {}

    public function isRepeatable(string $method, bool $hasIdempotencyKey): bool
    {
        $method = strtoupper($method);

        return in_array($method, ['GET', 'HEAD', 'DELETE', 'PATCH', 'PUT'], true) || ($method === 'POST' && $hasIdempotencyKey);
    }

    /** @param  int  $attempt  tentativi gia' fatti: con maxRetries = 2 si riprova dopo il primo e il secondo fallimento, non dopo il terzo */
    public function shouldRetry(int $attempt, ?int $status, bool $networkError): bool
    {
        if ($attempt > $this->maxRetries) {
            return false;
        }

        return $networkError || $status === 429 || ($status !== null && $status >= 500);
    }

    /** @param  int  $attempt  tentativi gia' fatti (1 = il primo e' appena fallito) */
    public function delayMs(int $attempt, ?string $retryAfterHeader): int
    {
        if (is_numeric($retryAfterHeader)) {
            $fromServer = (int) $retryAfterHeader * 1000;

            return max(0, min($fromServer, $this->maxDelayMs));
        }
        $exponential = $this->baseDelayMs * (2 ** max(0, $attempt - 1));
        $jitter = random_int(0, max(1, intdiv($this->baseDelayMs, 2)));

        return (int) min($exponential + $jitter, $this->maxDelayMs);
    }
}
