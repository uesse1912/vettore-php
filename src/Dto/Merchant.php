<?php

declare(strict_types=1);

namespace Vettore\Dto;

use DateTimeImmutable;

/** Sotto-account del cliente integratore: ogni spedizione appartiene a un merchant. */
final class Merchant
{
    /** @param  array<string, mixed>  $raw */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $externalRef,
        public readonly ?string $defaultSenderName,
        public readonly bool $active,
        public readonly ?DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $updatedAt,
        private readonly array $raw = [],
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::string($data, 'id'),
            Values::string($data, 'name'),
            Values::nullableString($data, 'external_ref'),
            Values::nullableString($data, 'default_sender_name'),
            Values::bool($data, 'active'),
            Values::dateTime($data, 'created_at'),
            Values::dateTime($data, 'updated_at'),
            $data,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->raw;
    }
}
