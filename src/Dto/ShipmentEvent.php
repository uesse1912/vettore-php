<?php

declare(strict_types=1);

namespace Vettore\Dto;

use DateTimeImmutable;

/** Evento di tracking normalizzato: stato (dimensione A), causa (B) e il testo originale del corriere. */
final class ShipmentEvent
{
    /** @param  array<string, mixed>  $raw */
    public function __construct(
        public readonly string $id,
        public readonly string $shipmentId,
        public readonly string $state,
        public readonly bool $isException,
        public readonly ?string $cause,
        public readonly ?string $carrierCode,
        public readonly ?string $carrierMessage,
        public readonly ?string $location,
        public readonly ?DateTimeImmutable $occurredAt,
        public readonly ?DateTimeImmutable $receivedAt,
        private readonly array $raw = [],
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::string($data, 'id'),
            Values::string($data, 'shipment_id'),
            Values::string($data, 'state', 'UNKNOWN'),
            Values::bool($data, 'is_exception'),
            Values::nullableString($data, 'cause'),
            Values::nullableString($data, 'carrier_code'),
            Values::nullableString($data, 'carrier_message'),
            Values::nullableString($data, 'location'),
            Values::dateTime($data, 'occurred_at'),
            Values::dateTime($data, 'received_at'),
            $data,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->raw;
    }
}
