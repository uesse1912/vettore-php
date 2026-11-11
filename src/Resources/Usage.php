<?php

declare(strict_types=1);

namespace Vettore\Resources;

use InvalidArgumentException;
use Vettore\Dto\UsageSummary;

/** /usage: unita' consumate in un mese, totali o per merchant. */
final class Usage extends Resource
{
    /** @param  string  $period  mese in formato YYYY-MM */
    public function get(string $period, ?string $merchantId = null): UsageSummary
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new InvalidArgumentException('period deve essere nel formato YYYY-MM');
        }
        $query = ['period' => $period, 'merchant_id' => $merchantId !== null ? rawurldecode($this->requireId($merchantId, 'mer_')) : null];

        return UsageSummary::fromArray($this->data($this->transport->request('GET', 'usage', $query)));
    }

    public function currentMonth(?string $merchantId = null): UsageSummary
    {
        return $this->get(date('Y-m'), $merchantId);
    }
}
