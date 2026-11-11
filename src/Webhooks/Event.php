<?php

declare(strict_types=1);

namespace Vettore\Webhooks;

use DateTimeImmutable;
use InvalidArgumentException;
use Vettore\Dto\CarrierAccount;
use Vettore\Dto\ExceptionCase;
use Vettore\Dto\Instruction;
use Vettore\Dto\Shipment;
use Vettore\Dto\ShipmentEvent;
use Vettore\Dto\Values;
use Vettore\Exceptions\InvalidSignatureException;

/**
 * Evento ricevuto su un endpoint webhook: {id, type, created_at, account_id, merchant_id, data}.
 * L'id (uguale all'header Vettore-Event-Id) serve a deduplicare: lo stesso evento puo' arrivare piu' volte.
 *
 *   $event = Event::fromRequest(file_get_contents('php://input'), $_SERVER['HTTP_VETTORE_SIGNATURE'], $secret);
 *   if ($event->type === Event::EXCEPTION_OPENED) { $case = $event->exception(); ... }
 */
final class Event
{
    public const SHIPMENT_STATUS_CHANGED = 'shipment.status_changed';

    public const EXCEPTION_OPENED = 'exception.opened';

    public const EXCEPTION_UPDATED = 'exception.updated';

    public const EXCEPTION_ASSIST_REQUIRED = 'exception.assist_required';

    public const EXCEPTION_CLOSED = 'exception.closed';

    public const CARRIER_ACCOUNT_VERIFIED = 'carrier_account.verified';

    public const TYPES = [
        self::SHIPMENT_STATUS_CHANGED,
        self::EXCEPTION_OPENED,
        self::EXCEPTION_UPDATED,
        self::EXCEPTION_ASSIST_REQUIRED,
        self::EXCEPTION_CLOSED,
        self::CARRIER_ACCOUNT_VERIFIED,
    ];

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly ?DateTimeImmutable $createdAt,
        public readonly string $accountId,
        public readonly ?string $merchantId,
        public readonly array $data,
        private readonly array $raw = [],
    ) {}

    /**
     * Costruisce l'evento dal corpo (stringa JSON o array gia' decodificato). Non verifica la firma.
     *
     * @param  string|array<string, mixed>  $payload
     */
    public static function fromPayload(string|array $payload): self
    {
        $decoded = is_string($payload) ? json_decode($payload, true) : $payload;
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Payload del webhook non e\' un oggetto JSON');
        }
        $id = Values::string($decoded, 'id');
        $type = Values::string($decoded, 'type');
        if ($id === '' || $type === '') {
            throw new InvalidArgumentException('Payload del webhook senza id o type');
        }

        return new self(
            $id,
            $type,
            Values::dateTime($decoded, 'created_at'),
            Values::string($decoded, 'account_id'),
            Values::nullableString($decoded, 'merchant_id'),
            Values::array($decoded, 'data'),
            $decoded,
        );
    }

    /**
     * Verifica la firma e poi costruisce l'evento. Solleva InvalidSignatureException se la firma non corrisponde.
     *
     * @param  string|list<string>  $secret  il segreto dell'endpoint, o piu' segreti candidati dopo una rotazione
     */
    public static function fromRequest(string $rawBody, string $signatureHeader, #[\SensitiveParameter] string|array $secret, int $tolerance = Signature::DEFAULT_TOLERANCE): self
    {
        if (! Signature::verifyAny($rawBody, $signatureHeader, (array) $secret, $tolerance)) {
            throw new InvalidSignatureException('Firma del webhook non valida');
        }

        return self::fromPayload($rawBody);
    }

    public function isKnownType(): bool
    {
        return in_array($this->type, self::TYPES, true);
    }

    public function shipment(): ?Shipment
    {
        $data = Values::nullableArray($this->data, 'shipment');

        return $data !== null ? Shipment::fromArray($data) : null;
    }

    /** Evento di tracking che ha causato shipment.status_changed. */
    public function shipmentEvent(): ?ShipmentEvent
    {
        $data = Values::nullableArray($this->data, 'event');

        return $data !== null ? ShipmentEvent::fromArray($data) : null;
    }

    /** Stato precedente del pacco in shipment.status_changed. */
    public function previousState(): ?string
    {
        return Values::nullableString($this->data, 'previous_state');
    }

    public function exception(): ?ExceptionCase
    {
        $data = Values::nullableArray($this->data, 'exception');

        return $data !== null ? ExceptionCase::fromArray($data) : null;
    }

    public function instruction(): ?Instruction
    {
        $data = Values::nullableArray($this->data, 'instruction');

        return $data !== null ? Instruction::fromArray($data) : null;
    }

    /** Passi da fare sul portale del corriere (solo exception.assist_required). */
    public function howTo(): ?string
    {
        return Values::nullableString($this->data, 'how_to');
    }

    public function carrierAccount(): ?CarrierAccount
    {
        $data = Values::nullableArray($this->data, 'carrier_account');

        return $data !== null ? CarrierAccount::fromArray($data) : null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->raw;
    }
}
