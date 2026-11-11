<?php

declare(strict_types=1);

namespace Vettore\Dto;

use DateTimeImmutable;

/**
 * Disposizione di svincolo. Stati: planned, sent, accepted, rejected, failed.
 * Canale: api (webservice del corriere), portal (connettore di portale), assisted (la esegue il cliente).
 */
final class Instruction
{
    public const TYPE_REDELIVER = 'redeliver';

    public const TYPE_CHANGE_ADDRESS = 'change_address';

    public const TYPE_RETURN_TO_SENDER = 'return_to_sender';

    /** @param  array<string, mixed>  $raw */
    public function __construct(
        public readonly string $id,
        public readonly string $exceptionId,
        public readonly ?string $shipmentId,
        public readonly string $type,
        public readonly ?DateTimeImmutable $requestedDate,
        public readonly ?DateTimeImmutable $effectiveDate,
        public readonly ?Address $address,
        public readonly ?float $codAmount,
        public readonly ?string $note,
        public readonly string $source,
        public readonly string $channel,
        public readonly string $state,
        public readonly ?string $carrierResponse,
        public readonly int $attempts,
        public readonly ?DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $sentAt,
        public readonly ?DateTimeImmutable $resolvedAt,
        private readonly array $raw = [],
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $address = Values::nullableArray($data, 'address');

        return new self(
            Values::string($data, 'id'),
            Values::string($data, 'exception_id'),
            Values::nullableString($data, 'shipment_id'),
            Values::string($data, 'type'),
            Values::dateTime($data, 'requested_date'),
            Values::dateTime($data, 'effective_date'),
            $address !== null && $address !== [] ? Address::fromArray($address) : null,
            Values::nullableFloat($data, 'cod_amount'),
            Values::nullableString($data, 'note'),
            Values::string($data, 'source', 'api'),
            Values::string($data, 'channel', 'api'),
            Values::string($data, 'state', 'planned'),
            Values::nullableString($data, 'carrier_response'),
            Values::int($data, 'attempts'),
            Values::dateTime($data, 'created_at'),
            Values::dateTime($data, 'sent_at'),
            Values::dateTime($data, 'resolved_at'),
            $data,
        );
    }

    public function isAssisted(): bool
    {
        return $this->channel === 'assisted';
    }

    public function isFinal(): bool
    {
        return in_array($this->state, ['accepted', 'rejected', 'failed'], true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->raw;
    }
}
