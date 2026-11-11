<?php

declare(strict_types=1);

namespace Vettore\Dto;

/** Indirizzo del destinatario o di una consegna a nuovo indirizzo. */
final class Address
{
    public function __construct(
        public readonly string $line1,
        public readonly string $postalCode,
        public readonly string $city,
        public readonly string $province,
        public readonly string $country = 'IT',
        public readonly ?string $line2 = null,
        public readonly ?string $notes = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::string($data, 'line1'),
            Values::string($data, 'postal_code'),
            Values::string($data, 'city'),
            Values::string($data, 'province'),
            Values::string($data, 'country', 'IT'),
            Values::nullableString($data, 'line2'),
            Values::nullableString($data, 'notes'),
        );
    }

    /**
     * Forma accettata dall'API in scrittura (chiavi nulle omesse).
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'line1' => $this->line1,
            'line2' => $this->line2,
            'postal_code' => $this->postalCode,
            'city' => $this->city,
            'province' => $this->province,
            'country' => $this->country,
            'notes' => $this->notes,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    public function oneLine(): string
    {
        return trim(implode(', ', array_filter([
            trim($this->line1.' '.($this->line2 ?? '')),
            trim($this->postalCode.' '.$this->city),
            $this->province,
        ])));
    }
}
