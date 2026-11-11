<?php

declare(strict_types=1);

namespace Vettore\Resources;

use Vettore\Dto\WebhookEndpoint;
use Vettore\Pagination\Page;
use Vettore\RequestOptions;

/**
 * /webhook-endpoints. L'URL deve essere https pubblico (porta 443 o 8443, niente IP privati o localhost).
 * Eventi: shipment.status_changed, exception.opened, exception.updated, exception.assist_required,
 * exception.closed, carrier_account.verified.
 */
final class WebhookEndpoints extends Resource
{
    /** @return Page<WebhookEndpoint> */
    public function list(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->page('webhook-endpoints', $this->listQuery([], $limit, $cursor), WebhookEndpoint::fromArray(...));
    }

    /**
     * Crea l'endpoint. Il segreto e' in $endpoint->secret SOLO in questa risposta (e non nella replica idempotente).
     *
     * @param  list<string>  $events
     */
    public function create(string $url, array $events, ?string $description = null, ?RequestOptions $options = null): WebhookEndpoint
    {
        $payload = array_filter(['url' => $url, 'events' => array_values($events), 'description' => $description], static fn ($v) => $v !== null);

        return WebhookEndpoint::fromArray($this->data($this->transport->request('POST', 'webhook-endpoints', [], $payload, $options)));
    }

    public function get(string $endpointId): WebhookEndpoint
    {
        return WebhookEndpoint::fromArray($this->data($this->transport->request('GET', $this->path($endpointId))));
    }

    /** @param  array{url?: string, events?: list<string>, description?: ?string, active?: bool}  $payload */
    public function update(string $endpointId, array $payload): WebhookEndpoint
    {
        return WebhookEndpoint::fromArray($this->data($this->transport->request('PATCH', $this->path($endpointId), [], $payload)));
    }

    public function delete(string $endpointId): void
    {
        $this->transport->request('DELETE', $this->path($endpointId));
    }

    /** Ruota il segreto: il nuovo e' in ->secret, il vecchio resta valido 24 ore (le consegne portano entrambe le firme). */
    public function rotateSecret(string $endpointId): WebhookEndpoint
    {
        return WebhookEndpoint::fromArray($this->data($this->transport->request('POST', $this->path($endpointId).'/rotate-secret')));
    }

    private function path(string $endpointId): string
    {
        return 'webhook-endpoints/'.$this->requireId($endpointId, 'whe_');
    }
}
