<?php

declare(strict_types=1);

namespace Vettore\Dto;

use DateTimeImmutable;

/** Consegna di un evento a un endpoint: pending, succeeded, failed (riprovera'), dead (replay manuale). */
final class WebhookDelivery
{
    /** @param  array<string, mixed>  $raw */
    public function __construct(
        public readonly string $id,
        public readonly string $endpointId,
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly string $status,
        public readonly int $attempts,
        public readonly ?int $lastResponseStatus,
        public readonly ?string $lastError,
        public readonly ?DateTimeImmutable $lastAttemptAt,
        public readonly ?DateTimeImmutable $nextAttemptAt,
        public readonly ?DateTimeImmutable $createdAt,
        private readonly array $raw = [],
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $status = $data['last_response_status'] ?? null;

        return new self(
            Values::string($data, 'id'),
            Values::string($data, 'endpoint_id'),
            Values::string($data, 'event_id'),
            Values::string($data, 'event_type'),
            Values::string($data, 'status', 'pending'),
            Values::int($data, 'attempts'),
            is_numeric($status) ? (int) $status : null,
            Values::nullableString($data, 'last_error'),
            Values::dateTime($data, 'last_attempt_at'),
            Values::dateTime($data, 'next_attempt_at'),
            Values::dateTime($data, 'created_at'),
            $data,
        );
    }

    public function isDead(): bool
    {
        return $this->status === 'dead';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->raw;
    }
}
