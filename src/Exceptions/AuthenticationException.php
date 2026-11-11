<?php

declare(strict_types=1);

namespace Vettore\Exceptions;

/** 401: chiave API mancante, revocata o non valida. */
final class AuthenticationException extends ApiException {}
