<?php

declare(strict_types=1);

namespace Vettore\Webhooks;

/**
 * Firma dei webhook, identica a quella del server (App\Domain\Webhooks\WebhookSigner).
 *
 * Header `Vettore-Signature: t={unix},v1={hex}[,v1={hex}]` dove ogni v1 e' HMAC-SHA256 del testo "{t}.{corpo}"
 * con il segreto dell'endpoint (whsec_...). Il corpo va usato grezzo, byte per byte, senza decodificarlo e
 * ricodificarlo. Durante la rotazione del segreto il server manda due firme (nuovo e vecchio segreto) per 24 ore:
 * basta che una corrisponda. La tolleranza sul timestamp e' di 5 minuti (300 secondi).
 */
final class Signature
{
    public const HEADER = 'Vettore-Signature';

    public const DEFAULT_TOLERANCE = 300;

    private const HEADER_PATTERN = '/^t=(\d+)((?:,v1=[a-f0-9]{64})+)$/';

    /**
     * Verifica una consegna con il segreto dell'endpoint.
     *
     * @param  string  $payload  corpo grezzo della richiesta (file_get_contents('php://input'))
     * @param  string  $signatureHeader  valore dell'header Vettore-Signature
     * @param  int  $tolerance  secondi di scarto ammessi fra il timestamp firmato e l'ora attuale
     * @param  int|null  $now  ora attuale in secondi Unix (solo per i test)
     */
    public static function verify(string $payload, string $signatureHeader, #[\SensitiveParameter] string $secret, int $tolerance = self::DEFAULT_TOLERANCE, ?int $now = null): bool
    {
        return self::verifyAny($payload, $signatureHeader, [$secret], $tolerance, $now);
    }

    /**
     * Come verify() ma con piu' segreti candidati (per esempio il nuovo e il precedente dopo una rotazione).
     *
     * @param  list<string>  $secrets
     */
    public static function verifyAny(string $payload, string $signatureHeader, #[\SensitiveParameter] array $secrets, int $tolerance = self::DEFAULT_TOLERANCE, ?int $now = null): bool
    {
        $parsed = self::parse($signatureHeader);
        if ($parsed === null) {
            return false;
        }
        [$timestamp, $candidates] = $parsed;
        if (abs(($now ?? time()) - $timestamp) > $tolerance) {
            return false;
        }

        foreach ($secrets as $secret) {
            if ($secret === '') {
                continue;
            }
            $expected = self::sign($payload, $secret, $timestamp);
            foreach ($candidates as $candidate) {
                if (hash_equals($expected, $candidate)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** HMAC-SHA256 esadecimale di "{timestamp}.{payload}". */
    public static function sign(string $payload, #[\SensitiveParameter] string $secret, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    /**
     * Costruisce l'header come lo manda il server: utile per testare il proprio ricevitore.
     *
     * @param  string|list<string>  $secrets
     */
    public static function header(string $payload, #[\SensitiveParameter] string|array $secrets, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $parts = ['t='.$timestamp];
        foreach ((array) $secrets as $secret) {
            $parts[] = 'v1='.self::sign($payload, $secret, $timestamp);
        }

        return implode(',', $parts);
    }

    /**
     * Timestamp e firme contenute nell'header, null se il formato non e' quello atteso.
     *
     * @return array{0: int, 1: list<string>}|null
     */
    public static function parse(string $signatureHeader): ?array
    {
        if (! preg_match(self::HEADER_PATTERN, trim($signatureHeader), $m)) {
            return null;
        }
        preg_match_all('/v1=([a-f0-9]{64})/', $m[2], $signatures);

        return [(int) $m[1], array_values($signatures[1])];
    }
}
