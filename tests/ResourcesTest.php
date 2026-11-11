<?php

declare(strict_types=1);

namespace Vettore\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vettore\Dto\CarrierAccount;
use Vettore\Dto\Instruction;
use Vettore\Tests\Support\FakeApi;
use Vettore\Tests\Support\Fixtures;

final class ResourcesTest extends TestCase
{
    #[Test]
    public function merchants_crud_hits_the_right_paths(): void
    {
        $api = FakeApi::create()
            ->success(Fixtures::merchant(), 201)
            ->success(Fixtures::merchant())
            ->success(Fixtures::merchant(['name' => 'Dopo']))
            ->noContent();
        $id = 'mer_01JCGJ3XK2Q4N5Z6Y7W8V9U0T1';

        $created = $api->client->merchants()->create(['name' => 'Beauty Shop', 'external_ref' => 'cliente-42']);
        self::assertSame('POST', $api->lastRequest()->getMethod());
        self::assertSame('/v1/merchants', $api->lastRequest()->getUri()->getPath());
        self::assertSame($id, $created->id);
        self::assertSame('cliente-42', $created->externalRef);
        self::assertTrue($created->active);
        self::assertSame('2026-11-11T09:00:00+00:00', $created->createdAt?->format(DATE_ATOM));

        $api->client->merchants()->get($id);
        self::assertSame('/v1/merchants/'.$id, $api->lastRequest()->getUri()->getPath());

        $updated = $api->client->merchants()->update($id, ['name' => 'Dopo']);
        self::assertSame('PATCH', $api->lastRequest()->getMethod());
        self::assertSame(['name' => 'Dopo'], $api->lastJson());
        self::assertSame('Dopo', $updated->name);

        $api->client->merchants()->delete($id);
        self::assertSame('DELETE', $api->lastRequest()->getMethod());
    }

    #[Test]
    public function carrier_accounts_are_nested_under_the_merchant_and_never_echo_credentials(): void
    {
        $api = FakeApi::create()
            ->success(Fixtures::carrierAccount(), 201)
            ->success(Fixtures::carrierAccount(['verification_status' => 'verified', 'verified_at' => '2026-11-11T09:02:00Z']))
            ->success(Fixtures::carrierAccount(), 202)
            ->noContent();
        $merchantId = 'mer_01JCGJ3XK2Q4N5Z6Y7W8V9U0T1';
        $accounts = $api->client->carrierAccounts($merchantId);

        $created = $accounts->create('fake', 'Corriere simulato', ['token' => 'valido']);
        self::assertSame('/v1/merchants/'.$merchantId.'/carrier-accounts', $api->lastRequest()->getUri()->getPath());
        self::assertSame(['carrier' => 'fake', 'label' => 'Corriere simulato', 'credentials' => ['token' => 'valido']], $api->lastJson());
        self::assertTrue($created->isPending());
        self::assertSame('api', $created->capabilities->instructions);
        self::assertTrue($created->capabilities->canSendInstructions());
        self::assertArrayNotHasKey('credentials', $created->toArray());

        $verified = $accounts->get($created->id);
        self::assertTrue($verified->isVerified());
        self::assertSame(CarrierAccount::STATUS_VERIFIED, $verified->verificationStatus);

        $accounts->verify($created->id);
        self::assertStringEndsWith('/verify', $api->lastRequest()->getUri()->getPath());

        $accounts->delete($created->id);
        self::assertSame('DELETE', $api->lastRequest()->getMethod());
    }

    #[Test]
    public function shipments_create_get_events_refresh_and_stop(): void
    {
        $api = FakeApi::create()
            ->success(Fixtures::shipment(['state' => 'CREATED', 'last_event' => null, 'open_exception_id' => null]), 201)
            ->success(Fixtures::shipment())
            ->success([Fixtures::event(), Fixtures::event(['id' => 'evt_2', 'state' => 'OUT_FOR_DELIVERY', 'is_exception' => false, 'cause' => null])], meta: ['next_cursor' => null])
            ->success(Fixtures::shipment(), 202)
            ->success(Fixtures::shipment(['order_ref' => 'ORD-2']))
            ->noContent();
        $payload = [
            'merchant_id' => 'mer_01JCGJ3XK2Q4N5Z6Y7W8V9U0T1',
            'carrier' => 'fake',
            'tracking_number' => 'TEST-0001-HOLD',
            'recipient' => ['name' => 'Mario Rossi', 'address' => ['line1' => 'Via Roma 1', 'postal_code' => '00100', 'city' => 'Roma', 'province' => 'RM']],
        ];

        $created = $api->client->shipments()->create($payload);
        self::assertSame($payload, $api->lastJson());
        self::assertSame('CREATED', $created->state);
        self::assertNull($created->lastEvent);
        self::assertFalse($created->hasOpenException());

        $shipment = $api->client->shipments()->get($created->id);
        self::assertSame('ON_HOLD', $shipment->state);
        self::assertSame('RECIPIENT_ABSENT', $shipment->lastEvent?->cause);
        self::assertNotNull($shipment->recipient);
        self::assertSame('Mario Rossi', $shipment->recipient->name);
        self::assertSame('Via Roma 1, 00100 Roma, RM', $shipment->recipient->address->oneLine());
        self::assertSame(49.9, $shipment->cod['amount'] ?? null);
        self::assertSame(['campagna' => 'novembre'], $shipment->metadata);
        self::assertTrue($shipment->hasOpenException());
        self::assertFalse($shipment->isTerminal());

        $events = $api->client->shipments()->events($shipment->id);
        self::assertSame('/v1/shipments/'.$shipment->id.'/events', $api->lastRequest()->getUri()->getPath());
        self::assertCount(2, $events);
        self::assertFalse($events->hasMore());
        self::assertSame('ON_HOLD', $events->first()?->state);

        $api->client->shipments()->refresh($shipment->id);
        self::assertStringEndsWith('/refresh', $api->lastRequest()->getUri()->getPath());

        $patched = $api->client->shipments()->update($shipment->id, ['order_ref' => 'ORD-2']);
        self::assertSame('ORD-2', $patched->orderRef);

        $api->client->shipments()->stop($shipment->id);
        self::assertSame('DELETE', $api->lastRequest()->getMethod());
        self::assertSame('/v1/shipments/'.$shipment->id, $api->lastRequest()->getUri()->getPath());
    }

    #[Test]
    public function exceptions_list_get_close_and_instructions(): void
    {
        $api = FakeApi::create()
            ->success([Fixtures::exception()], meta: ['next_cursor' => null])
            ->success(Fixtures::exception())
            ->success(Fixtures::instruction(), 202)
            ->success(Fixtures::instruction(['type' => 'change_address', 'address' => ['line1' => 'Via Nuova 2', 'postal_code' => '00100', 'city' => 'Roma', 'province' => 'RM', 'country' => 'IT']]), 202)
            ->success(Fixtures::instruction(['type' => 'return_to_sender', 'requested_date' => null]), 202)
            ->success([Fixtures::instruction()], meta: ['next_cursor' => null])
            ->success(Fixtures::exception(['state' => 'CLOSED_MANUAL', 'resolution' => 'manual', 'closed_at' => '2026-11-11T10:00:00Z']));
        $id = 'exc_01JCGJ7A2B3C4D5E6F7G8H9J0K';

        $page = $api->client->exceptions()->list(['state' => 'OPEN,INSTRUCTION_SENT', 'carrier' => 'fake']);
        self::assertSame('state=OPEN,INSTRUCTION_SENT&carrier=fake', urldecode($api->lastRequest()->getUri()->getQuery()));
        self::assertCount(1, $page);

        $case = $api->client->exceptions()->get($id);
        self::assertSame('OPEN', $case->state);
        self::assertTrue($case->allows('redeliver'));
        self::assertFalse($case->isClosed());
        self::assertSame('2026-11-12', $case->minRedeliveryDate?->format('Y-m-d'));
        self::assertFalse($case->billing['handled']);

        $redeliver = $api->client->exceptions()->redeliver($id, '2026-11-13', 'citofono rotto');
        self::assertSame('/v1/exceptions/'.$id.'/instructions', $api->lastRequest()->getUri()->getPath());
        self::assertSame(['type' => 'redeliver', 'requested_date' => '2026-11-13', 'note' => 'citofono rotto'], $api->lastJson());
        self::assertSame(Instruction::TYPE_REDELIVER, $redeliver->type);
        self::assertSame('planned', $redeliver->state);
        self::assertFalse($redeliver->isFinal());

        $change = $api->client->exceptions()->changeAddress($id, '2026-11-13', ['line1' => 'Via Nuova 2', 'postal_code' => '00100', 'city' => 'Roma', 'province' => 'RM']);
        self::assertSame('change_address', $api->lastJson()['type']);
        self::assertSame('Via Nuova 2', $change->address?->line1);

        $return = $api->client->exceptions()->returnToSender($id);
        self::assertSame(['type' => 'return_to_sender'], $api->lastJson());
        self::assertNull($return->requestedDate);

        $history = $api->client->exceptions()->instructions($id);
        self::assertSame('GET', $api->lastRequest()->getMethod());
        self::assertCount(1, $history);

        $closed = $api->client->exceptions()->close($id, 'handled_externally', 'svincolata dal portale');
        self::assertSame(['reason' => 'handled_externally', 'note' => 'svincolata dal portale'], $api->lastJson());
        self::assertTrue($closed->isClosed());
        self::assertSame('manual', $closed->resolution);
    }

    #[Test]
    public function webhook_endpoints_expose_the_secret_only_when_the_api_sends_it(): void
    {
        $api = FakeApi::create()
            ->success(Fixtures::webhookEndpoint() + ['secret' => 'whsec_segreto'], 201)
            ->success(Fixtures::webhookEndpoint())
            ->success(Fixtures::webhookEndpoint() + ['secret' => 'whsec_nuovo'])
            ->success(Fixtures::webhookEndpoint(['active' => false]))
            ->noContent();

        $created = $api->client->webhookEndpoints()->create('https://gestionale.example.com/vettore/webhook', ['exception.opened', 'exception.updated'], 'Gestionale');
        self::assertSame(['url' => 'https://gestionale.example.com/vettore/webhook', 'events' => ['exception.opened', 'exception.updated'], 'description' => 'Gestionale'], $api->lastJson());
        self::assertSame('whsec_segreto', $created->secret);
        self::assertTrue($created->subscribedTo('exception.opened'));

        $fetched = $api->client->webhookEndpoints()->get($created->id);
        self::assertNull($fetched->secret);

        $rotated = $api->client->webhookEndpoints()->rotateSecret($created->id);
        self::assertStringEndsWith('/rotate-secret', $api->lastRequest()->getUri()->getPath());
        self::assertSame('whsec_nuovo', $rotated->secret);

        $paused = $api->client->webhookEndpoints()->update($created->id, ['active' => false]);
        self::assertFalse($paused->active);

        $api->client->webhookEndpoints()->delete($created->id);
        self::assertSame('DELETE', $api->lastRequest()->getMethod());
    }

    #[Test]
    public function webhook_deliveries_list_and_replay(): void
    {
        $api = FakeApi::create()
            ->success([Fixtures::webhookDelivery()], meta: ['next_cursor' => null])
            ->success(Fixtures::webhookDelivery(['status' => 'pending']), 202);

        $dead = $api->client->webhookDeliveries()->list(['status' => 'dead'])->first();
        self::assertNotNull($dead);
        self::assertTrue($dead->isDead());
        self::assertSame(500, $dead->lastResponseStatus);

        $replayed = $api->client->webhookDeliveries()->replay($dead->id);
        self::assertSame('/v1/webhook-deliveries/'.$dead->id.'/replay', $api->lastRequest()->getUri()->getPath());
        self::assertSame('pending', $replayed->status);
    }

    #[Test]
    public function usage_requires_a_year_month_period(): void
    {
        $api = FakeApi::create()->success(Fixtures::usage());

        $usage = $api->client->usage()->get('2026-11', 'mer_01JCGJ3XK2Q4N5Z6Y7W8V9U0T1');

        self::assertSame('period=2026-11&merchant_id=mer_01JCGJ3XK2Q4N5Z6Y7W8V9U0T1', urldecode($api->lastRequest()->getUri()->getQuery()));
        self::assertSame(120, $usage->unit('shipments_tracked'));
        self::assertSame(0.5, $usage->unitPricesEur['exception_handled']);
        self::assertSame(14.5, $usage->totalEur);

        $this->expectException(\InvalidArgumentException::class);
        $api->client->usage()->get('novembre');
    }

    #[Test]
    public function me_includes_current_period_usage(): void
    {
        $api = FakeApi::create()->success(['id' => 'acc_1', 'name' => 'Integratore', 'environment' => 'live', 'created_at' => '2026-10-29T08:00:00Z', 'usage_current_period' => Fixtures::usage()]);

        $me = $api->client->me();

        self::assertFalse($me->isTest());
        self::assertNotNull($me->usageCurrentPeriod);
        self::assertSame('2026-11', $me->usageCurrentPeriod->period);
        self::assertSame(7, $me->usageCurrentPeriod->unit('exceptions_handled'));
    }
}
