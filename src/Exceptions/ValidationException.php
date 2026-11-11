<?php

declare(strict_types=1);

namespace Vettore\Exceptions;

/** 422: corpo o parametri non validi; i problemi per campo sono in $details. */
final class ValidationException extends ApiException {}
