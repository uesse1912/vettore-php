# Vettore PHP SDK

Client PHP ufficiale per l'API [Vettore](https://api.vettoreapi.com/docs): tracking unificato dei corrieri italiani (GLS, BRT, SDA), casi di giacenza, disposizioni di svincolo e webhook firmati.

- PHP >= 8.2, una sola dipendenza di runtime (Guzzle 7), `psr/log` facoltativo.
- Namespace `Vettore\`, oggetti risposta immutabili (`readonly`), eccezioni tipizzate.
- Retry automatico e limitato su 429/5xx/errori di rete, con backoff e rispetto di `Retry-After`.
- Verifica della firma dei webhook identica a quella del server, rotazione del segreto compresa.

Per il percorso completo "da zero a prima giacenza risolta" vedi la guida rapida su [api.vettoreapi.com/docs](https://api.vettoreapi.com/docs); il contratto e' l'OpenAPI servita su `https://api.vettoreapi.com/openapi.yaml`. Sorgente: [github.com/uesse1912/vettore-php](https://github.com/uesse1912/vettore-php).

## Installazione

```bash
composer require vettore/vettore-php
```

Finche' il pacchetto non e' pubblicato su Packagist, si installa dal repository Git:

```bash
composer config repositories.vettore vcs https://github.com/uesse1912/vettore-php
composer require vettore/vettore-php:dev-main
```

## Primi passi

```php
use Vettore\Vettore;

$vettore = new Vettore(getenv('VETTORE_API_KEY')); // vt_test_... o vt_live_...

$me = $vettore->me();
echo $me->name, ' (', $me->environment, ')', PHP_EOL;
```

Opzioni del client (tutte facoltative, oggetto immutabile):

```php
use Vettore\ClientOptions;

$options = ClientOptions::defaults()
    ->withBaseUrl('https://api.vettoreapi.com/v1')  // predefinito
    ->withTimeout(15.0, connectTimeout: 5.0)
    ->withMaxRetries(2)                              // nuovi tentativi su 429, 5xx, rete (0 = mai)
    ->withRetryDelays(baseDelayMs: 500, maxDelayMs: 8000)
    ->withLogger($psrLogger);                        // facoltativo: log dei retry e degli errori API

$vettore = new Vettore('vt_test_...', $options);
```

Opzioni per singola richiesta: `Idempotency-Key` (le POST che creano risorse: stessa chiave e stesso corpo entro 24 ore restituiscono la stessa risposta, e l'SDK puo' riprovare la POST in sicurezza) e `X-Request-Id` (tornato in `meta.request_id`, utile per correlare i log).

```php
use Vettore\RequestOptions;

$shipment = $vettore->shipments()->create($payload, RequestOptions::idempotent('ordine-1001'));
```

## Esempi completi

### 1. Crea un merchant

Ogni spedizione appartiene a un merchant (il tuo cliente, o te stesso).

```php
$merchant = $vettore->merchants()->create([
    'name' => 'Beauty Shop',
    'external_ref' => 'cliente-42',          // il suo id nel tuo gestionale
    'default_sender_name' => 'Beauty Shop',  // mostrato al destinatario nelle notifiche
]);
echo $merchant->id; // mer_01JC...
```

### 2. Registra le credenziali corriere

Le credenziali sono cifrate a riposo e non vengono mai restituite. La verifica e' asincrona: `verification_status` passa da `pending` a `verified` o `failed` entro pochi minuti (rileggi con `get()`).

```php
$accounts = $vettore->carrierAccounts($merchant->id);

// Con una chiave vt_test_ e' disponibile il corriere simulato "fake": il token "valido" supera la verifica.
$fake = $accounts->create('fake', 'Corriere simulato', ['token' => 'valido']);

// Corrieri reali (chiave live):
$gls = $accounts->create('gls', 'GLS contratto principale', [
    'sede' => 'M4', 'codice_cliente' => '123456', 'codice_contratto' => '7890', 'password' => '...',
]);
$brt = $accounts->create('brt', 'BRT', [
    'user_id' => '1234567', 'password' => '...',
    'vas_user' => '1234567', 'vas_password' => '...',   // facoltative: disposizioni dal portale VAS
]);
$sda = $accounts->create('sda', 'SDA', [
    'username' => '...', 'password' => '...', 'codice_cliente' => '000000012345', 'postazione' => '0001',
    'mysda_user' => '...', 'mysda_password' => '...', 'mysda_station' => 'PS2022', // facoltative: portale MySDA
]);

$status = $accounts->get($gls->id);
if ($status->isFailed()) {
    echo $status->verificationMessage;
}
echo $status->capabilities->instructions; // api | portal | none
```

### 3. Registra una spedizione

La spedizione e' gia' stata creata presso il corriere: qui si registra il numero di tracking. Con il corriere simulato il suffisso decide lo scenario: `-DLV` consegnata, `-HOLD` giacenza per destinatario assente, `-ADDR` indirizzo errato, `-REF` rifiuto, `-RET` resa, nessun suffisso = in transito.

```php
$shipment = $vettore->shipments()->create([
    'merchant_id' => $merchant->id,
    'carrier' => 'fake',
    'tracking_number' => 'TEST-0001-HOLD',
    'order_ref' => 'ORD-1001',
    'recipient' => [
        'name' => 'Mario Rossi',
        'phone' => '+393331234567',
        'address' => ['line1' => 'Via Roma 1', 'postal_code' => '00100', 'city' => 'Roma', 'province' => 'RM'],
    ],
    'cod' => ['amount' => 49.90, 'currency' => 'EUR'],
    'metadata' => ['campagna' => 'novembre'],
], RequestOptions::idempotent('ORD-1001'));

// Piu' tardi
$shipment = $vettore->shipments()->get($shipment->id);
echo $shipment->state;                 // ON_HOLD
echo $shipment->lastEvent?->cause;     // RECIPIENT_ABSENT
echo $shipment->openExceptionId;       // exc_...

foreach ($vettore->shipments()->events($shipment->id) as $event) {
    printf("%s %s %s\n", $event->occurredAt?->format('d/m H:i'), $event->state, $event->carrierMessage);
}

// Liste con filtri e paginazione a cursore
$page = $vettore->shipments()->list(['state' => 'ON_HOLD,DELIVERY_FAILED', 'merchant_id' => $merchant->id], limit: 100);
$next = $page->nextCursor;

// ...oppure un iteratore che segue i cursori da solo
foreach ($vettore->shipments()->iterate(['updated_since' => '2026-11-01T00:00:00Z']) as $s) {
    // una pagina alla volta, in modo pigro
}

$vettore->shipments()->refresh($shipment->id); // lettura immediata dal corriere, max una ogni 10 minuti
$vettore->shipments()->stop($shipment->id);    // interrompe il tracking
```

### 4. Ricevi un webhook e verifica la firma

Crea l'endpoint (https pubblico) e conserva il segreto: viene mostrato solo qui e alla rotazione.

```php
$endpoint = $vettore->webhookEndpoints()->create(
    'https://gestionale.example.com/vettore/webhook',
    ['shipment.status_changed', 'exception.opened', 'exception.updated', 'exception.assist_required', 'exception.closed'],
    'Gestionale',
);
$secret = $endpoint->secret; // whsec_... da salvare in modo sicuro
```

Nel ricevitore, verifica la firma sul **corpo grezzo** (mai su un JSON ridecodificato) e rispondi 2xx in fretta:

```php
use Vettore\Exceptions\InvalidSignatureException;
use Vettore\Webhooks\Event;

$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_VETTORE_SIGNATURE'] ?? '';

try {
    $event = Event::fromRequest($rawBody, $signature, $secret); // tolleranza 300 s
} catch (InvalidSignatureException) {
    http_response_code(401);
    exit;
}

// Deduplica con $event->id (uguale all'header Vettore-Event-Id): lo stesso evento puo' arrivare piu' volte.
switch ($event->type) {
    case Event::EXCEPTION_OPENED:
        $case = $event->exception();      // Vettore\Dto\ExceptionCase
        $shipment = $event->shipment();   // Vettore\Dto\Shipment
        break;
    case Event::EXCEPTION_UPDATED:
        $instruction = $event->instruction(); // accettata, rifiutata, fallita
        break;
    case Event::EXCEPTION_ASSIST_REQUIRED:
        $howTo = $event->howTo();         // passi da fare sul portale del corriere
        break;
    case Event::SHIPMENT_STATUS_CHANGED:
        $from = $event->previousState();
        $to = $event->shipment()?->state;
        break;
}
http_response_code(200);
```

Solo verifica, senza costruire l'evento:

```php
use Vettore\Webhooks\Signature;

Signature::verify($rawBody, $signature, $secret);            // bool, tolleranza 300 s
Signature::verify($rawBody, $signature, $secret, 60);        // tolleranza personalizzata
Signature::verifyAny($rawBody, $signature, [$nuovo, $vecchio]); // dopo una rotazione, se conservi entrambi
```

Algoritmo (uguale al server): header `Vettore-Signature: t={unix},v1={hex}`; `v1` = HMAC-SHA256 di `"{t}.{corpo}"` con il segreto. Durante le 24 ore dopo `rotateSecret()` il server manda due firme (`v1=` nuovo e `v1=` vecchio): basta che una corrisponda, quindi puoi passare al nuovo segreto senza perdere consegne. Firma fuori tolleranza (5 minuti) = rifiutata.

Per provare il tuo ricevitore puoi generare un header valido con `Signature::header($corpo, $secret)`.

### 5. Leggi una giacenza e manda una disposizione

```php
foreach ($vettore->exceptions()->iterate(['state' => 'OPEN']) as $case) {
    printf("%s %s %s (%s)\n", $case->id, $case->carrier, $case->cause, $case->carrierMessage);
    echo 'Prima data utile: ', $case->minRedeliveryDate?->format('Y-m-d'), PHP_EOL;
    echo 'Azioni: ', implode(', ', $case->availableActions), PHP_EOL; // redeliver, change_address, return_to_sender
}

$case = $vettore->exceptions()->get('exc_...');

// Riconsegna allo stesso indirizzo (giorno lavorativo, non prima di min_redelivery_date)
$instruction = $vettore->exceptions()->redeliver($case->id, '2026-11-13', 'citofono rotto, chiamare');

// Consegna a un nuovo indirizzo
$instruction = $vettore->exceptions()->changeAddress($case->id, '2026-11-13', [
    'line1' => 'Via Nuova 2', 'postal_code' => '00100', 'city' => 'Roma', 'province' => 'RM',
]);

// Reso al mittente (nessuna data)
$instruction = $vettore->exceptions()->returnToSender($case->id);

echo $instruction->state;   // planned (202): l'esito arriva con il webhook exception.updated
echo $instruction->channel; // api | portal | assisted

// Storico e chiusura manuale
$history = $vettore->exceptions()->instructions($case->id);
$vettore->exceptions()->close($case->id, 'handled_externally', 'svincolata dal portale');
```

Con il corriere simulato la nota decide l'esito della disposizione: `simula:rifiuto` = rifiutata, `simula:errore` = corriere non raggiungibile (il job riprova), `simula:credenziali` = credenziali rifiutate; qualunque altra nota = accettata.

### 6. Uso e consumi

```php
$usage = $vettore->usage()->get('2026-11');                  // tutto l'account
$usage = $vettore->usage()->get('2026-11', $merchant->id);   // un merchant
echo $usage->unit('exceptions_handled'), ' giacenze gestite, totale ', $usage->totalEur, ' EUR';
```

### 7. Diagnosi dei webhook

```php
foreach ($vettore->webhookDeliveries()->list(['status' => 'dead']) as $delivery) {
    $vettore->webhookDeliveries()->replay($delivery->id);
}
$vettore->webhookEndpoints()->rotateSecret($endpoint->id)->secret; // nuovo segreto; il vecchio vale 24 ore
```

## Oggetti risposta

Ogni risorsa torna un DTO `readonly` costruito dall'array JSON (`Vettore\Dto\*`): `Account`, `Merchant`, `CarrierAccount` (con `Capabilities`), `Shipment` (con `Recipient`, `Address`, `ShipmentEvent`), `ExceptionCase`, `Instruction`, `WebhookEndpoint`, `WebhookDelivery`, `UsageSummary`. Le date sono `DateTimeImmutable` (UTC; le date semplici `YYYY-MM-DD` sono lette nel fuso Europe/Rome). `toArray()` restituisce il payload originale, campi futuri compresi, cosi' niente si perde quando l'API aggiunge attributi.

Le liste tornano `Vettore\Pagination\Page` (`items`, `nextCursor`, `hasMore()`, iterabile e contabile) e `iterate()` un `Paginator` pigro.

## Gestione degli errori

Tutte le eccezioni estendono `Vettore\Exceptions\VettoreException`.

| Eccezione | Quando |
|---|---|
| `AuthenticationException` | 401: chiave mancante, revocata o sbagliata |
| `ValidationException` | 422: `->details` elenca `field` e `issue`; `->fieldIssues()` li formatta |
| `NotFoundException` | 404: risorsa inesistente o di un altro account |
| `ConflictException` | 409: tracking gia' registrato, caso chiuso, disposizione in corso; `isIdempotencyConflict()` per la chiave riusata con corpo diverso |
| `RateLimitException` | 429 dopo i retry: `retryAfter()` in secondi |
| `CarrierUnavailableException` | 503 `carrier_unavailable`: il corriere non risponde |
| `ServerException` | altri 5xx dopo i retry |
| `ApiException` | base delle precedenti: `status`, `errorCode`, `getMessage()`, `details`, `requestId`, `headers` |
| `TransportException` | niente risposta HTTP valida (rete, DNS, timeout, corpo non JSON) |
| `InvalidSignatureException` | firma del webhook non valida (`Event::fromRequest`) |

```php
use Vettore\Exceptions\ApiException;
use Vettore\Exceptions\RateLimitException;
use Vettore\Exceptions\TransportException;
use Vettore\Exceptions\ValidationException;

try {
    $shipment = $vettore->shipments()->create($payload);
} catch (ValidationException $e) {
    foreach ($e->details as $d) {
        $log->warning("{$d['field']}: {$d['issue']}");
    }
} catch (RateLimitException $e) {
    sleep($e->retryAfter() ?? 5);
} catch (ApiException $e) {
    $log->error("Vettore {$e->status} {$e->errorCode}: {$e->getMessage()} (request {$e->requestId})");
} catch (TransportException $e) {
    $log->error('Vettore non raggiungibile: '.$e->getMessage());
}
```

Retry: GET, DELETE e PATCH vengono ripetuti (al massimo `maxRetries` volte) su 429, 5xx ed errori di rete; le POST solo se hanno una `Idempotency-Key`. Gli errori 4xx diversi da 429 non vengono mai ripetuti. Gli id sono controllati prima di partire (`InvalidArgumentException` se il prefisso non e' quello atteso, per esempio `shp_` per una spedizione).

## Test dell'SDK

```bash
cd sdk/php
composer install
vendor/bin/phpunit        # oppure: composer test
composer analyse          # phpstan livello 8 (facoltativo)
```

I test usano il `MockHandler` di Guzzle: nessuna chiamata di rete. `tests/Webhooks/SignatureTest.php` replica i casi del test del server, cosi' un cambio di algoritmo da una parte sola si vede subito.

## English summary

Official PHP client for the Vettore API (Italian carriers: GLS, BRT, SDA). Install with `composer require vettore/vettore-php` (PHP 8.2+, Guzzle 7). Create the client with `new Vettore\Vettore('vt_test_...')` and use the fluent resources: `merchants()`, `carrierAccounts($merchantId)`, `shipments()`, `exceptions()` (delivery exceptions / "giacenze", with `redeliver()`, `changeAddress()`, `returnToSender()`), `webhookEndpoints()`, `webhookDeliveries()`, `usage()`, `me()`. Lists return cursor-paginated `Page` objects; `iterate()` walks every page lazily. Responses are immutable DTOs with `toArray()`. Errors are typed exceptions (`AuthenticationException`, `ValidationException`, `NotFoundException`, `ConflictException`, `RateLimitException`, `ServerException`, `TransportException`) all extending `VettoreException`; retries with backoff are applied to 429/5xx/network errors for idempotent requests (POST only with an `Idempotency-Key`). Webhooks are signed with `Vettore-Signature: t={unix},v1={hex}` (HMAC-SHA256 of `"{t}.{raw body}"`, 5 minute tolerance, two `v1` entries during secret rotation): verify with `Vettore\Webhooks\Signature::verify($rawBody, $header, $secret)` or parse and verify in one step with `Vettore\Webhooks\Event::fromRequest(...)`. Test accounts (`vt_test_` keys) can use the simulated carrier `fake` (credentials `{"token": "valido"}`; tracking suffix `-HOLD` opens an exception, `-DLV` delivers, `-ADDR`, `-REF`, `-RET` for the other scenarios).

## Licenza

MIT. Vedi [LICENSE](LICENSE).
