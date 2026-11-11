<?php

declare(strict_types=1);

namespace Vettore\Tests\Webhooks;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vettore\Webhooks\Signature;

/** Stessi casi del test del server (tests/Feature/Webhooks/WebhookDeliveryTest.php): l'algoritmo deve coincidere. */
final class SignatureTest extends TestCase
{
    #[Test]
    public function signature_is_hmac_of_timestamp_and_body_and_verifies_within_tolerance(): void
    {
        $header = Signature::header('{"a":1}', 'whsec_abc', 1700000000);

        self::assertMatchesRegularExpression('/^t=1700000000,v1=[a-f0-9]{64}$/', $header);
        self::assertSame(hash_hmac('sha256', '1700000000.{"a":1}', 'whsec_abc'), substr($header, strlen('t=1700000000,v1=')));
        self::assertTrue(Signature::verify('{"a":1}', $header, 'whsec_abc', now: 1700000100));
        self::assertFalse(Signature::verify('{"a":2}', $header, 'whsec_abc', now: 1700000100));
        self::assertFalse(Signature::verify('{"a":1}', $header, 'whsec_abc', now: 1700000000 + 400));
        self::assertFalse(Signature::verify('{"a":1}', $header, 'whsec_altro', now: 1700000100));
    }

    #[Test]
    public function known_vector_matches_the_server_algorithm(): void
    {
        // Vettore di prova fisso: hash_hmac('sha256', '1700000000.{"a":1}', 'whsec_abc'). Se cambia, l'algoritmo non e' piu' quello del server.
        self::assertSame('3b843bacfb18cc5554542fdcc84d815b399c52758e508a6fcecf0a2af4efea2b', Signature::sign('{"a":1}', 'whsec_abc', 1700000000));
        self::assertTrue(Signature::verify('{"a":1}', 't=1700000000,v1=3b843bacfb18cc5554542fdcc84d815b399c52758e508a6fcecf0a2af4efea2b', 'whsec_abc', now: 1700000000));
    }

    #[Test]
    public function during_secret_rotation_both_signatures_are_accepted(): void
    {
        $header = Signature::header('{}', ['whsec_nuovo', 'whsec_vecchio'], 1700000000);

        self::assertMatchesRegularExpression('/^t=1700000000,v1=[a-f0-9]{64},v1=[a-f0-9]{64}$/', $header);
        self::assertTrue(Signature::verify('{}', $header, 'whsec_nuovo', now: 1700000001));
        self::assertTrue(Signature::verify('{}', $header, 'whsec_vecchio', now: 1700000001));
        self::assertFalse(Signature::verify('{}', $header, 'whsec_altro', now: 1700000001));
        self::assertTrue(Signature::verifyAny('{}', $header, ['whsec_altro', 'whsec_vecchio'], now: 1700000001));
        self::assertFalse(Signature::verifyAny('{}', $header, ['', 'whsec_altro'], now: 1700000001));
    }

    #[Test]
    public function tolerance_is_symmetric_and_configurable(): void
    {
        $header = Signature::header('x', 'whsec_abc', 1700000000);

        self::assertTrue(Signature::verify('x', $header, 'whsec_abc', tolerance: 10, now: 1700000010));
        self::assertTrue(Signature::verify('x', $header, 'whsec_abc', tolerance: 10, now: 1699999990));
        self::assertFalse(Signature::verify('x', $header, 'whsec_abc', tolerance: 10, now: 1700000011));
        self::assertFalse(Signature::verify('x', $header, 'whsec_abc', tolerance: 10, now: 1699999989));
    }

    #[Test]
    public function malformed_headers_are_rejected_without_exceptions(): void
    {
        foreach (['', 't=abc,v1=00', 'v1=aaaa', 't=1700000000', 't=1700000000,v2='.str_repeat('a', 64), 't=1700000000,v1='.str_repeat('g', 64), 't=1700000000,v1='.str_repeat('a', 63)] as $bad) {
            self::assertFalse(Signature::verify('x', $bad, 'whsec_abc', now: 1700000000), 'accettato: '.$bad);
            self::assertNull(Signature::parse($bad));
        }
    }

    #[Test]
    public function parse_returns_timestamp_and_candidates(): void
    {
        $header = Signature::header('body', ['s1', 's2'], 1700000000);

        [$timestamp, $candidates] = Signature::parse(' '.$header.' ') ?? [0, []];

        self::assertSame(1700000000, $timestamp);
        self::assertCount(2, $candidates);
        self::assertSame(Signature::sign('body', 's1', 1700000000), $candidates[0]);
    }

    #[Test]
    public function header_uses_current_time_when_timestamp_is_omitted(): void
    {
        $header = Signature::header('body', 'whsec_abc');

        self::assertTrue(Signature::verify('body', $header, 'whsec_abc'));
    }
}
