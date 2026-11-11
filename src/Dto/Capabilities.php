<?php

declare(strict_types=1);

namespace Vettore\Dto;

/**
 * Cosa Vettore puo' fare con delle credenziali corriere.
 * instructions: api = webservice ufficiale (GLS, fake); portal = connettore di portale (VAS BRT, MySDA) con
 * ripiego assistito; none = solo modalita' assistita (il cliente esegue la disposizione sul portale).
 */
final class Capabilities
{
    public const INSTRUCTIONS_API = 'api';

    public const INSTRUCTIONS_PORTAL = 'portal';

    public const INSTRUCTIONS_NONE = 'none';

    public function __construct(
        public readonly bool $tracking,
        public readonly bool $exceptionReason,
        public readonly string $instructions,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::bool($data, 'tracking'),
            Values::bool($data, 'exception_reason'),
            Values::string($data, 'instructions', self::INSTRUCTIONS_NONE),
        );
    }

    public function canSendInstructions(): bool
    {
        return $this->instructions !== self::INSTRUCTIONS_NONE;
    }

    /** @return array{tracking: bool, exception_reason: bool, instructions: string} */
    public function toArray(): array
    {
        return ['tracking' => $this->tracking, 'exception_reason' => $this->exceptionReason, 'instructions' => $this->instructions];
    }
}
