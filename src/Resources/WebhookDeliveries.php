<?php

declare(strict_types=1);

namespace Vettore\Resources;

use Vettore\Dto\WebhookDelivery;
use Vettore\Pagination\Page;
use Vettore\Pagination\Paginator;

/** /webhook-deliveries: diagnosi delle consegne e replay di quelle morte. Filtri: endpoint_id, status, event_type. */
final class WebhookDeliveries extends Resource
{
    /**
     * @param  array<string, scalar|null>  $filters
     * @return Page<WebhookDelivery>
     */
    public function list(array $filters = [], ?int $limit = null, ?string $cursor = null): Page
    {
        return $this->page('webhook-deliveries', $this->listQuery($filters, $limit, $cursor), WebhookDelivery::fromArray(...));
    }

    /**
     * @param  array<string, scalar|null>  $filters
     * @return Paginator<WebhookDelivery>
     */
    public function iterate(array $filters = [], ?int $limit = null): Paginator
    {
        return $this->paginator('webhook-deliveries', $this->listQuery($filters, $limit, null), WebhookDelivery::fromArray(...));
    }

    /** Rispedisce una consegna morta o gia' riuscita (una consegna ancora in corso da' ConflictException). */
    public function replay(string $deliveryId): WebhookDelivery
    {
        return WebhookDelivery::fromArray($this->data($this->transport->request('POST', 'webhook-deliveries/'.$this->requireId($deliveryId, 'whd_').'/replay')));
    }
}
