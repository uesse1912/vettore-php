<?php

declare(strict_types=1);

namespace Vettore;

use InvalidArgumentException;

/**
 * Opzioni di una singola richiesta.
 *
 *   $vettore->shipments()->create($payload, RequestOptions::idempotent('ordine-1234'));
 *
 * - Idempotency-Key (max 128 caratteri): stessa chiave e stesso corpo entro 24 ore = stessa risposta, e l'SDK
 *   puo' riprovare in sicurezza anche una POST su 429/5xx.
 * - X-Request-Id (1-64 caratteri fra A-Z a-z 0-9 _ . : -): tornato in meta.request_id, utile per correlare i log.
 */
final class RequestOptions
{
    public function __construct(
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $requestId = null,
    ) {
        if ($idempotencyKey !== null && ($idempotencyKey === '' || strlen($idempotencyKey) > 128)) {
            throw new InvalidArgumentException('Idempotency-Key: da 1 a 128 caratteri');
        }
        if ($requestId !== null && ! preg_match('/^[A-Za-z0-9_.:-]{1,64}$/', $requestId)) {
            throw new InvalidArgumentException('X-Request-Id: da 1 a 64 caratteri fra A-Z a-z 0-9 _ . : -');
        }
    }

    public static function none(): self
    {
        return new self;
    }

    public static function idempotent(string $key, ?string $requestId = null): self
    {
        return new self($key, $requestId);
    }

    public function withRequestId(string $requestId): self
    {
        return new self($this->idempotencyKey, $requestId);
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        $headers = [];
        if ($this->idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $this->idempotencyKey;
        }
        if ($this->requestId !== null) {
            $headers['X-Request-Id'] = $this->requestId;
        }

        return $headers;
    }
}
