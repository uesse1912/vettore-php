<?php

declare(strict_types=1);

namespace Vettore;

use InvalidArgumentException;
use Vettore\Dto\Account;
use Vettore\Http\HttpTransport;
use Vettore\Resources\CarrierAccounts;
use Vettore\Resources\Exceptions;
use Vettore\Resources\Merchants;
use Vettore\Resources\Shipments;
use Vettore\Resources\Usage;
use Vettore\Resources\WebhookDeliveries;
use Vettore\Resources\WebhookEndpoints;

/**
 * Punto d'ingresso dell'SDK.
 *
 *   $vettore = new Vettore('vt_test_...');
 *   $merchant = $vettore->merchants()->create(['name' => 'Beauty Shop']);
 *   $vettore->carrierAccounts($merchant->id)->create('fake', 'Simulato', ['token' => 'valido']);
 *   $shipment = $vettore->shipments()->create([...], RequestOptions::idempotent('ordine-1'));
 */
final class Vettore
{
    public const VERSION = '0.1.0';

    private readonly HttpTransport $transport;

    public function __construct(#[\SensitiveParameter] string $apiKey, ?ClientOptions $options = null)
    {
        $apiKey = trim($apiKey);
        if (! preg_match('/^vt_(live|test)_[A-Za-z0-9]+$/', $apiKey)) {
            throw new InvalidArgumentException('Chiave API non valida: attesa una chiave vt_live_... o vt_test_...');
        }
        $this->transport = new HttpTransport($apiKey, $options ?? ClientOptions::defaults());
    }

    /** Account corrente e riepilogo d'uso del mese (GET /me): la chiamata piu' semplice per provare la chiave. */
    public function me(): Account
    {
        return Account::fromArray($this->transport->request('GET', 'me')->dataArray());
    }

    public function merchants(): Merchants
    {
        return new Merchants($this->transport);
    }

    public function carrierAccounts(string $merchantId): CarrierAccounts
    {
        return new CarrierAccounts($this->transport, $merchantId);
    }

    public function shipments(): Shipments
    {
        return new Shipments($this->transport);
    }

    public function exceptions(): Exceptions
    {
        return new Exceptions($this->transport);
    }

    public function webhookEndpoints(): WebhookEndpoints
    {
        return new WebhookEndpoints($this->transport);
    }

    public function webhookDeliveries(): WebhookDeliveries
    {
        return new WebhookDeliveries($this->transport);
    }

    public function usage(): Usage
    {
        return new Usage($this->transport);
    }
}
