<?php

declare(strict_types=1);

namespace Vettore\Exceptions;

/**
 * Risposta di errore dell'API nella busta {status: "error", error: {code, message, details}, meta: {request_id}}.
 * Il codice HTTP e il codice applicativo sono stabili; il messaggio e' in italiano e pensato per i log.
 */
class ApiException extends VettoreException
{
    /**
     * @param  list<array{field: string, issue: string}>  $details
     * @param  array<string, string>  $headers  intestazioni di risposta utili (per esempio Retry-After)
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
        public readonly ?string $requestId = null,
        public readonly array $headers = [],
    ) {
        parent::__construct($message, $status);
    }

    /**
     * Costruisce l'eccezione piu' specifica per lo stato HTTP e il codice dell'API.
     *
     * @param  array<string, mixed>  $body  busta decodificata (puo' essere vuota se il corpo non era JSON)
     * @param  array<string, string>  $headers
     */
    public static function fromResponse(int $status, array $body, array $headers = []): self
    {
        $error = is_array($body['error'] ?? null) ? $body['error'] : [];
        $code = is_string($error['code'] ?? null) ? $error['code'] : self::defaultCode($status);
        $message = is_string($error['message'] ?? null) ? $error['message'] : 'Errore HTTP '.$status;
        $details = self::details($error['details'] ?? null);
        $meta = is_array($body['meta'] ?? null) ? $body['meta'] : [];
        $requestId = is_string($meta['request_id'] ?? null) ? $meta['request_id'] : ($headers['x-request-id'] ?? null);
        if ($status < 400) {
            // Busta di errore con stato 2xx (proxy o replica anomala): si classifica dal codice applicativo.
            $status = self::statusForCode($code);
        }

        return match (true) {
            $status === 401 => new AuthenticationException($status, $code, $message, $details, $requestId, $headers),
            $status === 404 => new NotFoundException($status, $code, $message, $details, $requestId, $headers),
            $status === 409 => new ConflictException($status, $code, $message, $details, $requestId, $headers),
            $status === 422 => new ValidationException($status, $code, $message, $details, $requestId, $headers),
            $status === 429 => new RateLimitException($status, $code, $message, $details, $requestId, $headers),
            $status === 503 && $code === 'carrier_unavailable' => new CarrierUnavailableException($status, $code, $message, $details, $requestId, $headers),
            $status >= 500 => new ServerException($status, $code, $message, $details, $requestId, $headers),
            default => new self($status, $code, $message, $details, $requestId, $headers),
        };
    }

    /**
     * Problemi di un singolo campo (solo validation_error), nella forma "campo: problema".
     *
     * @return list<string>
     */
    public function fieldIssues(): array
    {
        return array_map(fn (array $d) => $d['field'].': '.$d['issue'], $this->details);
    }

    private static function defaultCode(int $status): string
    {
        return match (true) {
            $status === 401 => 'authentication_error',
            $status === 403 => 'permission_denied',
            $status === 404 => 'not_found',
            $status === 409 => 'conflict',
            $status === 422 => 'validation_error',
            $status === 429 => 'rate_limited',
            $status >= 500 => 'internal_error',
            default => 'error',
        };
    }

    private static function statusForCode(string $code): int
    {
        return match ($code) {
            'authentication_error' => 401,
            'permission_denied' => 403,
            'not_found' => 404,
            'conflict', 'idempotency_conflict' => 409,
            'validation_error' => 422,
            'rate_limited' => 429,
            'carrier_unavailable' => 503,
            default => 500,
        };
    }

    /** @return list<array{field: string, issue: string}> */
    private static function details(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $details = [];
        foreach ($raw as $item) {
            if (is_array($item) && isset($item['field'], $item['issue'])) {
                $details[] = ['field' => (string) $item['field'], 'issue' => (string) $item['issue']];
            }
        }

        return $details;
    }
}
