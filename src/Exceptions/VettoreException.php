<?php

declare(strict_types=1);

namespace Vettore\Exceptions;

use RuntimeException;

/** Radice di tutte le eccezioni dell'SDK: basta un solo catch per intercettare qualunque problema. */
class VettoreException extends RuntimeException {}
