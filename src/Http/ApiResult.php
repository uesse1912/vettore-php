<?php

declare(strict_types=1);

namespace Vettore\Http;

/** Risposta 2xx gia' decodificata: la busta {status, data, error, meta} piu' le intestazioni utili. */
final class ApiResult
{
    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, string>  $headers  nomi in minuscolo
     */
    public function __construct(
        public readonly int $status,
        public readonly mixed $data,
        public readonly array $meta,
        public readonly array $headers,
    ) {}

    public function requestId(): ?string
    {
        $id = $this->meta['request_id'] ?? $this->headers['x-request-id'] ?? null;

        return is_string($id) ? $id : null;
    }

    public function nextCursor(): ?string
    {
        $cursor = $this->meta['next_cursor'] ?? null;

        return is_string($cursor) && $cursor !== '' ? $cursor : null;
    }

    /** True se il server ha risposto con la copia di una richiesta precedente con la stessa Idempotency-Key. */
    public function replayed(): bool
    {
        return ($this->headers['idempotent-replayed'] ?? '') === 'true';
    }

    /** @return array<string, mixed> */
    public function dataArray(): array
    {
        return is_array($this->data) ? $this->data : [];
    }

    /** @return list<array<string, mixed>> */
    public function dataList(): array
    {
        if (! is_array($this->data)) {
            return [];
        }

        return array_values(array_filter($this->data, 'is_array'));
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
