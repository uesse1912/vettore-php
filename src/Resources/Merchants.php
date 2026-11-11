<?php

declare(strict_types=1);

namespace Vettore\Resources;

use Vettore\Dto\Merchant;
use Vettore\Pagination\Page;
use Vettore\Pagination\Paginator;
use Vettore\RequestOptions;

/** /merchants: sotto-account del cliente integratore. */
final class Merchants extends Resource
{
    /** @return Page<Merchant> */
    public function list(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->page('merchants', $this->listQuery([], $limit, $cursor), Merchant::fromArray(...));
    }

    /** @return Paginator<Merchant> */
    public function iterate(?int $limit = null): Paginator
    {
        return $this->paginator('merchants', $this->listQuery([], $limit, null), Merchant::fromArray(...));
    }

    /**
     * @param  array{name: string, external_ref?: string, default_sender_name?: string}  $payload
     */
    public function create(array $payload, ?RequestOptions $options = null): Merchant
    {
        return Merchant::fromArray($this->data($this->transport->request('POST', 'merchants', [], $payload, $options)));
    }

    public function get(string $merchantId): Merchant
    {
        return Merchant::fromArray($this->data($this->transport->request('GET', 'merchants/'.$this->requireId($merchantId, 'mer_'))));
    }

    /**
     * @param  array{name?: string, external_ref?: ?string, default_sender_name?: ?string, active?: bool}  $payload
     */
    public function update(string $merchantId, array $payload): Merchant
    {
        return Merchant::fromArray($this->data($this->transport->request('PATCH', 'merchants/'.$this->requireId($merchantId, 'mer_'), [], $payload)));
    }

    /** Disattiva il merchant: il tracking delle sue spedizioni si ferma e le credenziali corriere vengono rimosse. */
    public function delete(string $merchantId): void
    {
        $this->transport->request('DELETE', 'merchants/'.$this->requireId($merchantId, 'mer_'));
    }
}
