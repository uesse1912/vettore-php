<?php

declare(strict_types=1);

namespace Vettore\Exceptions;

use Throwable;

/** La richiesta non ha ricevuto una risposta HTTP valida: rete, DNS, timeout o corpo non JSON. */
class TransportException extends VettoreException
{
    public function __construct(string $message, public readonly ?string $requestId = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
