<?php

declare(strict_types=1);

namespace Vettore\Dto;

use DateTimeImmutable;

/** Credenziali del merchant presso un corriere. I segreti non vengono mai restituiti dall'API. */
final class CarrierAccount
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_FAILED = 'failed';

    /** @param  array<string, mixed>  $raw */
    public function __construct(
        public readonly string $id,
        public readonly string $merchantId,
        public readonly string $carrier,
        public readonly string $label,
        public readonly string $verificationStatus,
        public readonly ?string $verificationMessage,
        public readonly Capabilities $capabilities,
        public readonly ?DateTimeImmutable $verifiedAt,
        public readonly ?DateTimeImmutable $createdAt,
        private readonly array $raw = [],
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::string($data, 'id'),
            Values::string($data, 'merchant_id'),
            Values::string($data, 'carrier'),
            Values::string($data, 'label'),
            Values::string($data, 'verification_status', self::STATUS_PENDING),
            Values::nullableString($data, 'verification_message'),
            Capabilities::fromArray(Values::array($data, 'capabilities')),
            Values::dateTime($data, 'verified_at'),
            Values::dateTime($data, 'created_at'),
            $data,
        );
    }

    public function isVerified(): bool
    {
        return $this->verificationStatus === self::STATUS_VERIFIED;
    }

    public function isPending(): bool
    {
        return $this->verificationStatus === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->verificationStatus === self::STATUS_FAILED;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->raw;
    }
}
