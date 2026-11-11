<?php

declare(strict_types=1);

namespace Vettore\Dto;

use DateTimeImmutable;

/**
 * Caso di giacenza. Stato (dimensione C): OPEN, INSTRUCTION_PLANNED, INSTRUCTION_SENT, INSTRUCTION_ACCEPTED,
 * CLOSED_DELIVERED, CLOSED_RETURNED, CLOSED_EXPIRED, CLOSED_MANUAL. Modalita': automatic o assisted.
 */
final class ExceptionCase
{
    /**
     * @param  list<string>  $availableActions  redeliver, change_address, return_to_sender
     * @param  array{handled: bool, delivered: bool}  $billing
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $shipmentId,
        public readonly string $merchantId,
        public readonly string $carrier,
        public readonly string $state,
        public readonly string $cause,
        public readonly ?string $carrierCode,
        public readonly ?string $carrierMessage,
        public readonly ?string $carrierReason,
        public readonly string $mode,
        public readonly array $availableActions,
        public readonly ?DateTimeImmutable $minRedeliveryDate,
        public readonly ?DateTimeImmutable $carrierDeadlineAt,
        public readonly ?string $currentInstructionId,
        public readonly int $reopenCount,
        public readonly ?string $resolution,
        public readonly array $billing,
        public readonly ?DateTimeImmutable $openedAt,
        public readonly ?DateTimeImmutable $closedAt,
        public readonly ?DateTimeImmutable $updatedAt,
        private readonly array $raw = [],
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $billing = Values::array($data, 'billing');

        return new self(
            Values::string($data, 'id'),
            Values::string($data, 'shipment_id'),
            Values::string($data, 'merchant_id'),
            Values::string($data, 'carrier'),
            Values::string($data, 'state', 'OPEN'),
            Values::string($data, 'cause', 'OTHER'),
            Values::nullableString($data, 'carrier_code'),
            Values::nullableString($data, 'carrier_message'),
            Values::nullableString($data, 'carrier_reason'),
            Values::string($data, 'mode', 'automatic'),
            Values::stringList($data, 'available_actions'),
            Values::dateTime($data, 'min_redelivery_date'),
            Values::dateTime($data, 'carrier_deadline_at'),
            Values::nullableString($data, 'current_instruction_id'),
            Values::int($data, 'reopen_count'),
            Values::nullableString($data, 'resolution'),
            ['handled' => Values::bool($billing, 'handled'), 'delivered' => Values::bool($billing, 'delivered')],
            Values::dateTime($data, 'opened_at'),
            Values::dateTime($data, 'closed_at'),
            Values::dateTime($data, 'updated_at'),
            $data,
        );
    }

    public function isClosed(): bool
    {
        return str_starts_with($this->state, 'CLOSED_');
    }

    public function isAssisted(): bool
    {
        return $this->mode === 'assisted';
    }

    public function allows(string $instructionType): bool
    {
        return in_array($instructionType, $this->availableActions, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->raw;
    }
}
