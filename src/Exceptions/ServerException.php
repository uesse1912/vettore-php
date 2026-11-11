<?php

declare(strict_types=1);

namespace Vettore\Exceptions;

/** 5xx: errore lato Vettore. L'SDK ha gia' riprovato (se la richiesta era ripetibile) prima di sollevarla. */
final class ServerException extends ApiException {}
