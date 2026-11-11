<?php

declare(strict_types=1);

namespace Vettore\Dto;

use DateTimeImmutable;

/** Endpoint webhook. Il segreto (whsec_...) e' presente solo nella risposta di creazione e di rotazione: salvalo subito. */
final class WebhookEndpoint
{
    /**
     * @param  list<string>  $events
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly array $events,
        public readonly ?string $description,
        public readonly bool $active,
        public readonly ?DateTimeImmutable $lastSuccessAt,
        public readonly ?DateTimeImmutable $lastFailureAt,
        public readonly ?DateTimeImmutable $createdAt,
        #[\SensitiveParameter] public readonly ?string $secret,
        private readonly array $raw = [],
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::string($data, 'id'),
            Values::string($data, 'url'),
            Values::stringList($data, 'events'),
            Values::nullableString($data, 'description'),
            Values::bool($data, 'active'),
            Values::dateTime($data, 'last_success_at'),
            Values::dateTime($data, 'last_failure_at'),
            Values::dateTime($data, 'created_at'),
            Values::nullableString($data, 'secret'),
            $data,
        );
    }

    public function subscribedTo(string $eventType): bool
    {
        return in_array($eventType, $this->events, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->raw;
    }
}
