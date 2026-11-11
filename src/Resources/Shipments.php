<?php

declare(strict_types=1);

namespace Vettore\Resources;

use Vettore\Dto\Shipment;
use Vettore\Dto\ShipmentEvent;
use Vettore\Pagination\Page;
use Vettore\Pagination\Paginator;
use Vettore\RequestOptions;

/**
 * /shipments: registrazione e lettura delle spedizioni tracciate.
 * Filtri di list()/iterate(): merchant_id, carrier, state (anche "ON_HOLD,DELIVERY_FAILED"), tracking_number,
 * order_ref, updated_since (ISO 8601).
 */
final class Shipments extends Resource
{
    /**
     * @param  array<string, scalar|null>  $filters
     * @return Page<Shipment>
     */
    public function list(array $filters = [], ?int $limit = null, ?string $cursor = null): Page
    {
        return $this->page('shipments', $this->listQuery($filters, $limit, $cursor), Shipment::fromArray(...));
    }

    /**
     * @param  array<string, scalar|null>  $filters
     * @return Paginator<Shipment>
     */
    public function iterate(array $filters = [], ?int $limit = null): Paginator
    {
        return $this->paginator('shipments', $this->listQuery($filters, $limit, null), Shipment::fromArray(...));
    }

    /**
     * Registra una spedizione gia' creata presso il corriere. Campi obbligatori: merchant_id, carrier, tracking_number,
     * recipient {name, address {line1, postal_code, city, province}}. Facoltativi: carrier_account_id, carrier_reference,
     * order_ref, cod {amount, currency}, shipped_at, notifications {whatsapp}, metadata.
     *
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, ?RequestOptions $options = null): Shipment
    {
        return Shipment::fromArray($this->data($this->transport->request('POST', 'shipments', [], $payload, $options)));
    }

    public function get(string $shipmentId): Shipment
    {
        return Shipment::fromArray($this->data($this->transport->request('GET', $this->path($shipmentId))));
    }

    /**
     * Aggiornamento parziale: destinatario, notifiche e metadati si fondono con i valori esistenti
     * (un metadato a null viene rimosso).
     *
     * @param  array<string, mixed>  $payload
     */
    public function update(string $shipmentId, array $payload): Shipment
    {
        return Shipment::fromArray($this->data($this->transport->request('PATCH', $this->path($shipmentId), [], $payload)));
    }

    /** Interrompe il tracking; la spedizione resta consultabile. */
    public function stop(string $shipmentId): void
    {
        $this->transport->request('DELETE', $this->path($shipmentId));
    }

    /**
     * Timeline degli eventi, dal piu' recente.
     *
     * @return Page<ShipmentEvent>
     */
    public function events(string $shipmentId, ?int $limit = null, ?string $cursor = null): Page
    {
        return $this->page($this->path($shipmentId).'/events', $this->listQuery([], $limit, $cursor), ShipmentEvent::fromArray(...));
    }

    /**
     * Forza una lettura immediata dal corriere (massimo una ogni 10 minuti per spedizione: oltre, RateLimitException;
     * su una spedizione con tracking fermo, ConflictException).
     */
    public function refresh(string $shipmentId): Shipment
    {
        return Shipment::fromArray($this->data($this->transport->request('POST', $this->path($shipmentId).'/refresh')));
    }

    private function path(string $shipmentId): string
    {
        return 'shipments/'.$this->requireId($shipmentId, 'shp_');
    }
}
