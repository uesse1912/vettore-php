<?php

declare(strict_types=1);

namespace Vettore\Resources;

use Vettore\Dto\CarrierAccount;
use Vettore\Http\HttpTransport;
use Vettore\Pagination\Page;
use Vettore\RequestOptions;

/**
 * /merchants/{merchantId}/carrier-accounts: credenziali del merchant presso il corriere (bring your own credentials).
 * Campi di credentials per corriere:
 *   gls:  sede, codice_cliente, codice_contratto, password
 *   brt:  user_id, password [, vas_user, vas_password]            (VAS = disposizioni dal portale)
 *   sda:  username, password, codice_cliente (12), postazione (4-5) [, mysda_user, mysda_password, mysda_station]
 *   fake: token ("valido" per superare la verifica)                 (solo chiavi vt_test_)
 */
final class CarrierAccounts extends Resource
{
    private readonly string $basePath;

    public function __construct(HttpTransport $transport, string $merchantId)
    {
        parent::__construct($transport);
        $this->basePath = 'merchants/'.$this->requireId($merchantId, 'mer_').'/carrier-accounts';
    }

    /** @return Page<CarrierAccount> */
    public function list(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->page($this->basePath, $this->listQuery([], $limit, $cursor), CarrierAccount::fromArray(...));
    }

    /**
     * Registra le credenziali. La verifica e' asincrona: lo stato passa da pending a verified o failed entro pochi minuti.
     *
     * @param  array<string, string>  $credentials
     */
    public function create(string $carrier, string $label, #[\SensitiveParameter] array $credentials, ?RequestOptions $options = null): CarrierAccount
    {
        $payload = ['carrier' => $carrier, 'label' => $label, 'credentials' => $credentials];

        return CarrierAccount::fromArray($this->data($this->transport->request('POST', $this->basePath, [], $payload, $options)));
    }

    public function get(string $carrierAccountId): CarrierAccount
    {
        return CarrierAccount::fromArray($this->data($this->transport->request('GET', $this->path($carrierAccountId))));
    }

    /** Riavvia la verifica (risposta 202, stato pending). */
    public function verify(string $carrierAccountId): CarrierAccount
    {
        return CarrierAccount::fromArray($this->data($this->transport->request('POST', $this->path($carrierAccountId).'/verify')));
    }

    public function delete(string $carrierAccountId): void
    {
        $this->transport->request('DELETE', $this->path($carrierAccountId));
    }

    private function path(string $carrierAccountId): string
    {
        return $this->basePath.'/'.$this->requireId($carrierAccountId, 'car_');
    }
}
