<?php

declare(strict_types=1);

namespace Vettore\Dto;

/** Destinatario della spedizione. Il telefono e' in formato E.164 (+39...), facoltativo in Fase 1. */
final class Recipient
{
    public function __construct(
        public readonly string $name,
        public readonly Address $address,
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::string($data, 'name'),
            Address::fromArray(Values::array($data, 'address')),
            Values::nullableString($data, 'phone'),
            Values::nullableString($data, 'email'),
        );
    }

    /**
     * Forma accettata dall'API in scrittura.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address->toArray(),
        ], static fn ($v) => $v !== null);
    }
}
