<?php

declare(strict_types=1);

namespace Vettore\Exceptions;

/** La firma di un webhook non corrisponde (segreto sbagliato, corpo alterato o timestamp fuori tolleranza). */
final class InvalidSignatureException extends VettoreException {}
