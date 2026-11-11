<?php

declare(strict_types=1);

namespace Vettore\Resources;

use Vettore\Dto\ExceptionCase;
use Vettore\Dto\Instruction;
use Vettore\Pagination\Page;
use Vettore\Pagination\Paginator;
use Vettore\RequestOptions;

/**
 * /exceptions: casi di giacenza e disposizioni di svincolo.
 * Filtri di list()/iterate(): merchant_id, carrier, state (anche "OPEN,INSTRUCTION_SENT"), cause, mode, opened_since.
 */
final class Exceptions extends Resource
{
    /**
     * @param  array<string, scalar|null>  $filters
     * @return Page<ExceptionCase>
     */
    public function list(array $filters = [], ?int $limit = null, ?string $cursor = null): Page
    {
        return $this->page('exceptions', $this->listQuery($filters, $limit, $cursor), ExceptionCase::fromArray(...));
    }

    /**
     * @param  array<string, scalar|null>  $filters
     * @return Paginator<ExceptionCase>
     */
    public function iterate(array $filters = [], ?int $limit = null): Paginator
    {
        return $this->paginator('exceptions', $this->listQuery($filters, $limit, null), ExceptionCase::fromArray(...));
    }

    public function get(string $exceptionId): ExceptionCase
    {
        return ExceptionCase::fromArray($this->data($this->transport->request('GET', $this->path($exceptionId))));
    }

    /**
     * Chiude il caso a mano (gestito fuori da Vettore). reason: handled_externally, duplicate, not_an_exception.
     * Su un caso gia' chiuso: ConflictException.
     */
    public function close(string $exceptionId, string $reason, ?string $note = null): ExceptionCase
    {
        $payload = array_filter(['reason' => $reason, 'note' => $note], static fn ($v) => $v !== null);

        return ExceptionCase::fromArray($this->data($this->transport->request('POST', $this->path($exceptionId).'/close', [], $payload)));
    }

    /**
     * Storico delle disposizioni del caso.
     *
     * @return Page<Instruction>
     */
    public function instructions(string $exceptionId, ?int $limit = null, ?string $cursor = null): Page
    {
        return $this->page($this->path($exceptionId).'/instructions', $this->listQuery([], $limit, $cursor), Instruction::fromArray(...));
    }

    /**
     * Invia una disposizione di svincolo (risposta 202: pianificata; l'esito arriva con il webhook exception.updated).
     *
     * @param  array{type: string, requested_date?: string, address?: array<string, string>, cod_amount?: float, note?: string}  $payload
     *                                                                                                                                     type: redeliver | change_address | return_to_sender. requested_date (YYYY-MM-DD, giorno lavorativo) obbligatoria
     *                                                                                                                                     per redeliver e change_address; address obbligatorio per change_address; cod_amount solo GLS su redeliver.
     */
    public function createInstruction(string $exceptionId, array $payload, ?RequestOptions $options = null): Instruction
    {
        return Instruction::fromArray($this->data($this->transport->request('POST', $this->path($exceptionId).'/instructions', [], $payload, $options)));
    }

    /** Scorciatoia: riconsegna allo stesso indirizzo in una data (YYYY-MM-DD). */
    public function redeliver(string $exceptionId, string $requestedDate, ?string $note = null, ?RequestOptions $options = null): Instruction
    {
        $payload = array_filter(['type' => Instruction::TYPE_REDELIVER, 'requested_date' => $requestedDate, 'note' => $note], static fn ($v) => $v !== null);

        return $this->createInstruction($exceptionId, $payload, $options);
    }

    /**
     * Scorciatoia: consegna a un nuovo indirizzo in una data.
     *
     * @param  array<string, string>  $address  line1, postal_code (5 cifre per IT), city, province [, line2, country, notes]
     */
    public function changeAddress(string $exceptionId, string $requestedDate, array $address, ?string $note = null, ?RequestOptions $options = null): Instruction
    {
        $payload = array_filter(['type' => Instruction::TYPE_CHANGE_ADDRESS, 'requested_date' => $requestedDate, 'address' => $address, 'note' => $note], static fn ($v) => $v !== null);

        return $this->createInstruction($exceptionId, $payload, $options);
    }

    /** Scorciatoia: reso al mittente (nessuna data). */
    public function returnToSender(string $exceptionId, ?string $note = null, ?RequestOptions $options = null): Instruction
    {
        $payload = array_filter(['type' => Instruction::TYPE_RETURN_TO_SENDER, 'note' => $note], static fn ($v) => $v !== null);

        return $this->createInstruction($exceptionId, $payload, $options);
    }

    private function path(string $exceptionId): string
    {
        return 'exceptions/'.$this->requireId($exceptionId, 'exc_');
    }
}
