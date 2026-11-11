<?php

declare(strict_types=1);

namespace Vettore\Exceptions;

/** 503 carrier_unavailable: il corriere non ha risposto; riprovare piu' tardi. */
final class CarrierUnavailableException extends ApiException {}
