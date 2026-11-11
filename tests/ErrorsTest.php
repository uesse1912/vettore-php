<?php

declare(strict_types=1);

namespace Vettore\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vettore\ClientOptions;
use Vettore\Exceptions\ApiException;
use Vettore\Exceptions\AuthenticationException;
use Vettore\Exceptions\CarrierUnavailableException;
use Vettore\Exceptions\ConflictException;
use Vettore\Exceptions\NotFoundException;
use Vettore\Exceptions\ServerException;
use Vettore\Exceptions\ValidationException;
use Vettore\Exceptions\VettoreException;
use Vettore\Tests\Support\FakeApi;

final class ErrorsTest extends TestCase
{
    private function api(): FakeApi
    {
        return FakeApi::create(options: ClientOptions::defaults()->withMaxRetries(0));
    }

    #[Test]
    public function unauthorized_becomes_authentication_exception(): void
    {
        $api = $this->api()->error(401, 'authentication_error', 'Chiave API mancante o non valida');

        try {
            $api->client->me();
            self::fail('attesa AuthenticationException');
        } catch (AuthenticationException $e) {
            self::assertSame(401, $e->status);
            self::assertSame('authentication_error', $e->errorCode);
            self::assertSame('Chiave API mancante o non valida', $e->getMessage());
            self::assertSame('req_err', $e->requestId);
            self::assertInstanceOf(VettoreException::class, $e);
            self::assertInstanceOf(ApiException::class, $e);
        }
    }

    #[Test]
    public function validation_errors_expose_field_details(): void
    {
        $api = $this->api()->error(422, 'validation_error', 'Corpo della richiesta non valido', [
            ['field' => 'recipient.phone', 'issue' => 'formato E.164 atteso'],
            ['field' => 'carrier', 'issue' => 'nessuna credenziale valida per questo corriere sul merchant'],
        ]);

        try {
            $api->client->shipments()->create(['merchant_id' => 'mer_1']);
            self::fail('attesa ValidationException');
        } catch (ValidationException $e) {
            self::assertCount(2, $e->details);
            self::assertSame('recipient.phone', $e->details[0]['field']);
            self::assertSame(['recipient.phone: formato E.164 atteso', 'carrier: nessuna credenziale valida per questo corriere sul merchant'], $e->fieldIssues());
        }
    }

    #[Test]
    public function not_found_and_conflicts_are_typed(): void
    {
        $api = $this->api()
            ->error(404, 'not_found', 'Risorsa inesistente')
            ->error(409, 'idempotency_conflict', 'Idempotency-Key gia usata con un corpo diverso')
            ->error(409, 'conflict', 'tracking gia registrato');

        try {
            $api->client->merchants()->get('mer_nonesiste');
            self::fail('attesa NotFoundException');
        } catch (NotFoundException $e) {
            self::assertSame('not_found', $e->errorCode);
        }

        try {
            $api->client->merchants()->create(['name' => 'Due']);
            self::fail('attesa ConflictException');
        } catch (ConflictException $e) {
            self::assertTrue($e->isIdempotencyConflict());
        }

        try {
            $api->client->shipments()->create(['tracking_number' => 'X']);
            self::fail('attesa ConflictException');
        } catch (ConflictException $e) {
            self::assertFalse($e->isIdempotencyConflict());
            self::assertSame(409, $e->getCode());
        }
    }

    #[Test]
    public function carrier_unavailable_and_generic_5xx_are_distinguished(): void
    {
        $api = $this->api()
            ->error(503, 'carrier_unavailable', 'GLS non raggiungibile')
            ->error(500, 'internal_error', 'Errore interno');

        try {
            $api->client->me();
            self::fail('attesa CarrierUnavailableException');
        } catch (CarrierUnavailableException $e) {
            self::assertSame(503, $e->status);
        }
        try {
            $api->client->me();
            self::fail('attesa ServerException');
        } catch (ServerException $e) {
            self::assertSame('internal_error', $e->errorCode);
        }
    }

    #[Test]
    public function error_without_json_body_still_maps_the_http_status(): void
    {
        $api = $this->api()->raw(new Response(502, ['Content-Type' => 'text/html', 'X-Request-Id' => 'req_gw'], '<html>Bad gateway</html>'));

        try {
            $api->client->me();
            self::fail('attesa ServerException');
        } catch (ServerException $e) {
            self::assertSame(502, $e->status);
            self::assertSame('internal_error', $e->errorCode);
            self::assertSame('req_gw', $e->requestId);
        }
    }

    #[Test]
    public function error_envelope_with_2xx_status_is_still_an_error(): void
    {
        $api = $this->api()->raw(new Response(200, ['Content-Type' => 'application/json'], '{"status":"error","data":null,"error":{"code":"conflict","message":"x"},"meta":{"request_id":"r"}}'));

        $this->expectException(ConflictException::class);
        $api->client->me();
    }

    #[Test]
    public function resource_ids_are_validated_before_any_request(): void
    {
        $api = $this->api();

        $this->expectException(\InvalidArgumentException::class);
        try {
            $api->client->shipments()->get('mer_sbagliato');
        } finally {
            self::assertSame(0, $api->requestCount());
        }
    }
}
