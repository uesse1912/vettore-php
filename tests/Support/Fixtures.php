<?php

declare(strict_types=1);

namespace Vettore\Tests\Support;

/** Payload realistici, coerenti con le Resource dell'API (app/Http/Resources/Api). */
final class Fixtures
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function merchant(array $overrides = []): array
    {
        return array_replace([
            'id' => 'mer_01JCGJ3XK2Q4N5Z6Y7W8V9U0T1',
            'name' => 'Beauty Shop',
            'external_ref' => 'cliente-42',
            'default_sender_name' => 'Beauty Shop',
            'active' => true,
            'created_at' => '2026-11-11T09:00:00Z',
            'updated_at' => '2026-11-11T09:00:00Z',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function carrierAccount(array $overrides = []): array
    {
        return array_replace([
            'id' => 'car_01JCGJ4A7B8C9D0E1F2G3H4J5K',
            'merchant_id' => 'mer_01JCGJ3XK2Q4N5Z6Y7W8V9U0T1',
            'carrier' => 'fake',
            'label' => 'Corriere simulato',
            'verification_status' => 'pending',
            'verification_message' => null,
            'capabilities' => ['tracking' => true, 'exception_reason' => true, 'instructions' => 'api'],
            'verified_at' => null,
            'created_at' => '2026-11-11T09:01:00Z',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function event(array $overrides = []): array
    {
        return array_replace([
            'id' => 'evt_01JCGJ6Z1X2C3V4B5N6M7K8L9J',
            'shipment_id' => 'shp_01JCGJ5P9Q8R7S6T5U4V3W2X1Y',
            'state' => 'ON_HOLD',
            'is_exception' => true,
            'cause' => 'RECIPIENT_ABSENT',
            'carrier_code' => 'HOLD_ABSENT',
            'carrier_message' => 'Destinatario assente, in attesa di istruzioni',
            'location' => 'Roma',
            'occurred_at' => '2026-11-11T09:33:00Z',
            'received_at' => '2026-11-11T09:35:12Z',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function shipment(array $overrides = []): array
    {
        return array_replace([
            'id' => 'shp_01JCGJ5P9Q8R7S6T5U4V3W2X1Y',
            'merchant_id' => 'mer_01JCGJ3XK2Q4N5Z6Y7W8V9U0T1',
            'carrier' => 'fake',
            'carrier_account_id' => 'car_01JCGJ4A7B8C9D0E1F2G3H4J5K',
            'tracking_number' => 'TEST-0001-HOLD',
            'carrier_reference' => [],
            'order_ref' => 'ORD-1001',
            'recipient' => [
                'name' => 'Mario Rossi',
                'phone' => '+393331234567',
                'email' => null,
                'address' => ['line1' => 'Via Roma 1', 'postal_code' => '00100', 'city' => 'Roma', 'province' => 'RM', 'country' => 'IT'],
            ],
            'cod' => ['amount' => 49.9, 'currency' => 'EUR'],
            'state' => 'ON_HOLD',
            'state_changed_at' => '2026-11-11T09:35:12Z',
            'last_event' => self::event(),
            'open_exception_id' => 'exc_01JCGJ7A2B3C4D5E6F7G8H9J0K',
            'tracking_active' => true,
            'last_polled_at' => '2026-11-11T09:35:12Z',
            'tracking_url' => null,
            'shipped_at' => null,
            'closed_at' => null,
            'metadata' => ['campagna' => 'novembre'],
            'created_at' => '2026-11-11T09:30:00Z',
            'updated_at' => '2026-11-11T09:35:12Z',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function exception(array $overrides = []): array
    {
        return array_replace([
            'id' => 'exc_01JCGJ7A2B3C4D5E6F7G8H9J0K',
            'shipment_id' => 'shp_01JCGJ5P9Q8R7S6T5U4V3W2X1Y',
            'merchant_id' => 'mer_01JCGJ3XK2Q4N5Z6Y7W8V9U0T1',
            'carrier' => 'fake',
            'state' => 'OPEN',
            'cause' => 'RECIPIENT_ABSENT',
            'carrier_code' => 'HOLD_ABSENT',
            'carrier_message' => 'Destinatario assente, in attesa di istruzioni',
            'carrier_reason' => null,
            'mode' => 'automatic',
            'available_actions' => ['redeliver', 'change_address', 'return_to_sender'],
            'min_redelivery_date' => '2026-11-12',
            'carrier_deadline_at' => '2026-11-18T23:00:00Z',
            'current_instruction_id' => null,
            'reopen_count' => 0,
            'resolution' => null,
            'billing' => ['handled' => false, 'delivered' => false],
            'opened_at' => '2026-11-11T09:35:12Z',
            'closed_at' => null,
            'updated_at' => '2026-11-11T09:35:12Z',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function instruction(array $overrides = []): array
    {
        return array_replace([
            'id' => 'ins_01JCGJ8B3C4D5E6F7G8H9J0K1L',
            'exception_id' => 'exc_01JCGJ7A2B3C4D5E6F7G8H9J0K',
            'shipment_id' => 'shp_01JCGJ5P9Q8R7S6T5U4V3W2X1Y',
            'type' => 'redeliver',
            'requested_date' => '2026-11-13',
            'effective_date' => null,
            'address' => null,
            'cod_amount' => null,
            'note' => 'citofono rotto',
            'source' => 'api',
            'channel' => 'api',
            'state' => 'planned',
            'carrier_response' => null,
            'attempts' => 0,
            'created_at' => '2026-11-11T09:40:00Z',
            'sent_at' => null,
            'resolved_at' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function webhookEndpoint(array $overrides = []): array
    {
        return array_replace([
            'id' => 'whe_01JCGJ9C4D5E6F7G8H9J0K1L2M',
            'url' => 'https://gestionale.example.com/vettore/webhook',
            'events' => ['exception.opened', 'exception.updated'],
            'description' => 'Gestionale',
            'active' => true,
            'last_success_at' => null,
            'last_failure_at' => null,
            'created_at' => '2026-11-11T09:05:00Z',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function webhookDelivery(array $overrides = []): array
    {
        return array_replace([
            'id' => 'whd_01JCGJAD5E6F7G8H9J0K1L2M3N',
            'endpoint_id' => 'whe_01JCGJ9C4D5E6F7G8H9J0K1L2M',
            'event_id' => 'wev_01JCGJAE6F7G8H9J0K1L2M3N4P',
            'event_type' => 'exception.opened',
            'status' => 'dead',
            'attempts' => 7,
            'last_response_status' => 500,
            'last_error' => 'HTTP 500',
            'last_attempt_at' => '2026-11-12T09:35:12Z',
            'next_attempt_at' => null,
            'created_at' => '2026-11-11T09:35:12Z',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function usage(array $overrides = []): array
    {
        return array_replace([
            'period' => '2026-11',
            'merchant_id' => null,
            'units' => ['shipments_tracked' => 120, 'whatsapp_conversations' => 0, 'exceptions_handled' => 7, 'exceptions_delivered' => 5],
            'unit_prices_eur' => ['shipment_tracked' => 0.05, 'whatsapp_conversation' => 0.05, 'exception_handled' => 0.5, 'exception_delivered' => 1.0],
            'total_eur' => 14.5,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function webhookPayload(string $type = 'exception.opened', array $data = []): array
    {
        return [
            'id' => 'wev_01JCGJAE6F7G8H9J0K1L2M3N4P',
            'type' => $type,
            'created_at' => '2026-11-11T09:35:13Z',
            'account_id' => 'acc_01JCGJ2W1V0U9T8S7R6Q5P4N3M',
            'merchant_id' => 'mer_01JCGJ3XK2Q4N5Z6Y7W8V9U0T1',
            'data' => $data ?: ['exception' => self::exception(), 'shipment' => self::shipment()],
        ];
    }
}
