<?php

declare(strict_types=1);

namespace Vettore\Exceptions;

/** 409: tracking gia' registrato, caso gia' chiuso, disposizione in corso o Idempotency-Key riusata con un corpo diverso. */
final class ConflictException extends ApiException
{
    public function isIdempotencyConflict(): bool
    {
        return $this->errorCode === 'idempotency_conflict';
    }
}
