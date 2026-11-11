<?php

declare(strict_types=1);

namespace Vettore\Dto;

use DateTimeImmutable;

/** Spedizione tracciata. Gli stati terminali sono DELIVERED, RETURNED, LOST_OR_DAMAGED, CANCELLED. */
final class Shipment
{
    public const TERMINAL_STATES = ['DELIVERED', 'RETURNED', 'LOST_OR_DAMAGED', 'CANCELLED'];

    /**
     * @param  array<string, string>  $carrierReference
     * @param  array<string, string>  $metadata
     * @param  array{amount: float, currency: string}|null  $cod
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $merchantId,
        public readonly string $carrier,
        public readonly ?string $carrierAccountId,
        public readonly string $trackingNumber,
        public readonly array $carrierReference,
        public readonly ?string $orderRef,
        public readonly ?Recipient $recipient,
        public readonly ?array $cod,
        public readonly string $state,
        public readonly ?DateTimeImmutable $stateChangedAt,
        public readonly ?ShipmentEvent $lastEvent,
        public readonly ?string $openExceptionId,
        public readonly bool $trackingActive,
        public readonly ?DateTimeImmutable $lastPolledAt,
        public readonly ?string $trackingUrl,
        public readonly ?DateTimeImmutable $shippedAt,
        public readonly ?DateTimeImmutable $closedAt,
        public readonly array $metadata,
        public readonly ?DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $updatedAt,
        private readonly array $raw = [],
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $recipient = Values::nullableArray($data, 'recipient');
        $lastEvent = Values::nullableArray($data, 'last_event');
        $cod = Values::nullableArray($data, 'cod');

        return new self(
            Values::string($data, 'id'),
            Values::string($data, 'merchant_id'),
            Values::string($data, 'carrier'),
            Values::nullableString($data, 'carrier_account_id'),
            Values::string($data, 'tracking_number'),
            self::stringMap(Values::array($data, 'carrier_reference')),
            Values::nullableString($data, 'order_ref'),
            $recipient !== null ? Recipient::fromArray($recipient) : null,
            $cod !== null ? ['amount' => (float) ($cod['amount'] ?? 0), 'currency' => Values::string($cod, 'currency', 'EUR')] : null,
            Values::string($data, 'state', 'UNKNOWN'),
            Values::dateTime($data, 'state_changed_at'),
            $lastEvent !== null ? ShipmentEvent::fromArray($lastEvent) : null,
            Values::nullableString($data, 'open_exception_id'),
            Values::bool($data, 'tracking_active'),
            Values::dateTime($data, 'last_polled_at'),
            Values::nullableString($data, 'tracking_url'),
            Values::dateTime($data, 'shipped_at'),
            Values::dateTime($data, 'closed_at'),
            self::stringMap(Values::array($data, 'metadata')),
            Values::dateTime($data, 'created_at'),
            Values::dateTime($data, 'updated_at'),
            $data,
        );
    }

    public function isTerminal(): bool
    {
        return in_array($this->state, self::TERMINAL_STATES, true);
    }

    public function hasOpenException(): bool
    {
        return $this->openExceptionId !== null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->raw;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private static function stringMap(array $values): array
    {
        $out = [];
        foreach ($values as $k => $v) {
            if (is_scalar($v)) {
                $out[(string) $k] = (string) $v;
            }
        }

        return $out;
    }
}
