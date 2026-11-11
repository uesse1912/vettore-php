<?php

declare(strict_types=1);

namespace Vettore\Tests\Webhooks;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vettore\Exceptions\InvalidSignatureException;
use Vettore\Tests\Support\Fixtures;
use Vettore\Webhooks\Event;
use Vettore\Webhooks\Signature;

final class EventTest extends TestCase
{
    #[Test]
    public function builds_an_exception_opened_event_from_the_raw_payload(): void
    {
        $json = json_encode(Fixtures::webhookPayload(), JSON_THROW_ON_ERROR);

        $event = Event::fromPayload($json);

        self::assertSame('wev_01JCGJAE6F7G8H9J0K1L2M3N4P', $event->id);
        self::assertSame(Event::EXCEPTION_OPENED, $event->type);
        self::assertTrue($event->isKnownType());
        self::assertSame('acc_01JCGJ2W1V0U9T8S7R6Q5P4N3M', $event->accountId);
        self::assertSame('mer_01JCGJ3XK2Q4N5Z6Y7W8V9U0T1', $event->merchantId);
        self::assertSame('2026-11-11T09:35:13+00:00', $event->createdAt?->format(DATE_ATOM));
        $case = $event->exception();
        self::assertNotNull($case);
        self::assertSame('exc_01JCGJ7A2B3C4D5E6F7G8H9J0K', $case->id);
        self::assertSame('RECIPIENT_ABSENT', $case->cause);
        self::assertSame('TEST-0001-HOLD', $event->shipment()?->trackingNumber);
        self::assertNull($event->instruction());
        self::assertNull($event->howTo());
        self::assertNull($event->carrierAccount());
        self::assertSame(json_decode($json, true), $event->toArray());
    }

    #[Test]
    public function status_changed_exposes_event_and_previous_state(): void
    {
        $event = Event::fromPayload(Fixtures::webhookPayload(Event::SHIPMENT_STATUS_CHANGED, [
            'shipment' => Fixtures::shipment(),
            'event' => Fixtures::event(),
            'previous_state' => 'OUT_FOR_DELIVERY',
        ]));

        self::assertSame('OUT_FOR_DELIVERY', $event->previousState());
        $shipmentEvent = $event->shipmentEvent();
        self::assertNotNull($shipmentEvent);
        self::assertSame('HOLD_ABSENT', $shipmentEvent->carrierCode);
        self::assertTrue($shipmentEvent->isException);
    }

    #[Test]
    public function assist_required_carries_instruction_and_how_to(): void
    {
        $event = Event::fromPayload(Fixtures::webhookPayload(Event::EXCEPTION_ASSIST_REQUIRED, [
            'exception' => Fixtures::exception(['mode' => 'assisted', 'state' => 'INSTRUCTION_SENT']),
            'shipment' => Fixtures::shipment(['carrier' => 'brt']),
            'instruction' => Fixtures::instruction(['channel' => 'assisted', 'state' => 'sent']),
            'how_to' => 'Accedi al portale VAS BRT (vas.brt.it) ...',
        ]));

        self::assertTrue($event->exception()?->isAssisted());
        self::assertTrue($event->instruction()?->isAssisted());
        self::assertStringStartsWith('Accedi al portale VAS BRT', (string) $event->howTo());
    }

    #[Test]
    public function carrier_account_verified_exposes_the_account(): void
    {
        $event = Event::fromPayload(Fixtures::webhookPayload(Event::CARRIER_ACCOUNT_VERIFIED, [
            'carrier_account' => Fixtures::carrierAccount(['verification_status' => 'verified']),
        ]));

        self::assertTrue($event->carrierAccount()?->isVerified());
    }

    #[Test]
    public function from_request_verifies_the_signature_first(): void
    {
        $body = json_encode(Fixtures::webhookPayload(), JSON_THROW_ON_ERROR);
        $good = Signature::header($body, 'whsec_segreto');

        $event = Event::fromRequest($body, $good, 'whsec_segreto');
        self::assertSame(Event::EXCEPTION_OPENED, $event->type);

        $afterRotation = Event::fromRequest($body, $good, ['whsec_nuovo', 'whsec_segreto']);
        self::assertSame($event->id, $afterRotation->id);

        $this->expectException(InvalidSignatureException::class);
        Event::fromRequest($body, $good, 'whsec_sbagliato');
    }

    #[Test]
    public function tampered_body_is_refused(): void
    {
        $body = json_encode(Fixtures::webhookPayload(), JSON_THROW_ON_ERROR);
        $header = Signature::header($body, 'whsec_segreto');

        $this->expectException(InvalidSignatureException::class);
        Event::fromRequest(str_replace('RECIPIENT_ABSENT', 'REFUSED', $body), $header, 'whsec_segreto');
    }

    #[Test]
    public function payload_without_id_or_type_is_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Event::fromPayload('{"data":{}}');
    }

    #[Test]
    public function non_json_payload_is_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Event::fromPayload('non json');
    }
}
