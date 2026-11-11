<?php

declare(strict_types=1);

namespace Vettore\Dto;

use DateTimeImmutable;

/** Account integratore (GET /me). */
final class Account
{
    /** @param  array<string, mixed>  $raw */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $environment,
        public readonly ?DateTimeImmutable $createdAt,
        public readonly ?UsageSummary $usageCurrentPeriod,
        private readonly array $raw = [],
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $usage = Values::nullableArray($data, 'usage_current_period');

        return new self(
            Values::string($data, 'id'),
            Values::string($data, 'name'),
            Values::string($data, 'environment'),
            Values::dateTime($data, 'created_at'),
            $usage !== null ? UsageSummary::fromArray($usage) : null,
            $data,
        );
    }

    public function isTest(): bool
    {
        return $this->environment === 'test';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->raw;
    }
}
