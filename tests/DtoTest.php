<?php

declare(strict_types=1);

namespace Vettore\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vettore\Dto\Address;
use Vettore\Dto\ExceptionCase;
use Vettore\Dto\Recipient;
use Vettore\Dto\Shipment;
use Vettore\Dto\Values;
use Vettore\Tests\Support\Fixtures;

final class DtoTest extends TestCase
{
    #[Test]
    public function to_array_returns_the_original_payload_including_unknown_fields(): void
    {
        $raw = Fixtures::shipment(['campo_futuro' => 'x']);

        $shipment = Shipment::fromArray($raw);

        self::assertSame($raw, $shipment->toArray());
        self::assertSame('x', $shipment->toArray()['campo_futuro']);
    }

    #[Test]
    public function missing_and_null_fields_do_not_break_construction(): void
    {
        $shipment = Shipment::fromArray(['id' => 'shp_1', 'state' => 'IN_TRANSIT']);

        self::assertSame('shp_1', $shipment->id);
        self::assertNull($shipment->recipient);
        self::assertNull($shipment->cod);
        self::assertNull($shipment->lastEvent);
        self::assertSame([], $shipment->metadata);
        self::assertSame([], $shipment->carrierReference);
        self::assertFalse($shipment->trackingActive);
        self::assertNull($shipment->createdAt);

        $case = ExceptionCase::fromArray([]);
        self::assertSame('OPEN', $case->state);
        self::assertSame([], $case->availableActions);
        self::assertFalse($case->billing['delivered']);
    }

    #[Test]
    public function date_only_values_are_read_in_the_rome_timezone(): void
    {
        $date = Values::dateTime(['d' => '2026-11-13'], 'd');

        self::assertSame('2026-11-13 00:00:00 Europe/Rome', $date?->format('Y-m-d H:i:s e'));
        self::assertNull(Values::dateTime(['d' => 'ieri'], 'd'));
        self::assertNull(Values::dateTime(['d' => null], 'd'));
        self::assertSame('2026-11-11T09:35:12+00:00', Values::dateTime(['d' => '2026-11-11T09:35:12Z'], 'd')?->format(DATE_ATOM));
    }

    #[Test]
    public function address_and_recipient_round_trip_to_the_api_shape(): void
    {
        $recipient = new Recipient('Mario Rossi', new Address('Via Roma 1', '00100', 'Roma', 'RM', notes: 'citofono Rossi'), '+393331234567');

        self::assertSame([
            'name' => 'Mario Rossi',
            'phone' => '+393331234567',
            'address' => ['line1' => 'Via Roma 1', 'postal_code' => '00100', 'city' => 'Roma', 'province' => 'RM', 'country' => 'IT', 'notes' => 'citofono Rossi'],
        ], $recipient->toArray());
        self::assertSame('Via Roma 1, 00100 Roma, RM', $recipient->address->oneLine());
        self::assertSame($recipient->toArray(), Recipient::fromArray($recipient->toArray())->toArray());
    }

    #[Test]
    public function dto_objects_are_immutable(): void
    {
        $shipment = Shipment::fromArray(Fixtures::shipment());

        $this->expectException(\Error::class);
        /** @phpstan-ignore-next-line */
        $shipment->state = 'DELIVERED';
    }
}
