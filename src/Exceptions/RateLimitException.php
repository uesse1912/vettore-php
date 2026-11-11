<?php

declare(strict_types=1);

namespace Vettore\Exceptions;

/** 429: oltre il limite per chiave (600/min) o il cooldown di un refresh (uno ogni 10 minuti per spedizione). */
final class RateLimitException extends ApiException
{
    /** Secondi suggeriti dal server prima di riprovare (header Retry-After), null se assente. */
    public function retryAfter(): ?int
    {
        $value = $this->headers['retry-after'] ?? null;

        return is_numeric($value) ? max(0, (int) $value) : null;
    }
}
