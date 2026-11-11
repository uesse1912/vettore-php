<?php

declare(strict_types=1);

namespace Vettore\Dto;

/** Unita' consumate in un mese (GET /usage e Account.usage_current_period). */
final class UsageSummary
{
    /**
     * @param  array<string, int>  $units  shipments_tracked, whatsapp_conversations, exceptions_handled, exceptions_delivered
     * @param  array<string, float>  $unitPricesEur
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $period,
        public readonly ?string $merchantId,
        public readonly array $units,
        public readonly array $unitPricesEur,
        public readonly ?float $totalEur,
        private readonly array $raw = [],
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $units = [];
        foreach (Values::array($data, 'units') as $k => $v) {
            $units[(string) $k] = is_numeric($v) ? (int) $v : 0;
        }
        $prices = [];
        foreach (Values::array($data, 'unit_prices_eur') as $k => $v) {
            $prices[(string) $k] = is_numeric($v) ? (float) $v : 0.0;
        }

        return new self(
            Values::string($data, 'period'),
            Values::nullableString($data, 'merchant_id'),
            $units,
            $prices,
            Values::nullableFloat($data, 'total_eur'),
            $data,
        );
    }

    public function unit(string $name): int
    {
        return $this->units[$name] ?? 0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->raw;
    }
}
