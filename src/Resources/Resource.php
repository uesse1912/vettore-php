<?php

declare(strict_types=1);

namespace Vettore\Resources;

use Closure;
use InvalidArgumentException;
use Vettore\Http\ApiResult;
use Vettore\Http\HttpTransport;
use Vettore\Pagination\Page;
use Vettore\Pagination\Paginator;

/** Base delle risorse: chiamate tipizzate e paginazione a cursore. */
abstract class Resource
{
    public function __construct(protected readonly HttpTransport $transport) {}

    /**
     * Legge una pagina di lista e mappa ogni elemento con $map.
     *
     * @template T
     *
     * @param  array<string, scalar|null>  $query
     * @param  Closure(array<string, mixed>): T  $map
     * @return Page<T>
     */
    protected function page(string $path, array $query, Closure $map): Page
    {
        $result = $this->transport->request('GET', $path, $query);

        return new Page(array_map($map, $result->dataList()), $result->nextCursor(), $result->requestId());
    }

    /**
     * Iteratore pigro su tutte le pagine.
     *
     * @template T
     *
     * @param  array<string, scalar|null>  $query
     * @param  Closure(array<string, mixed>): T  $map
     * @return Paginator<T>
     */
    protected function paginator(string $path, array $query, Closure $map): Paginator
    {
        return new Paginator(fn (?string $cursor): Page => $this->page($path, array_replace($query, ['cursor' => $cursor]), $map));
    }

    /**
     * @param  array<string, scalar|null>  $filters
     * @return array<string, scalar|null>
     */
    protected function listQuery(array $filters, ?int $limit, ?string $cursor): array
    {
        if ($limit !== null && ($limit < 1 || $limit > 200)) {
            throw new InvalidArgumentException('limit deve essere fra 1 e 200');
        }

        return $filters + ['limit' => $limit, 'cursor' => $cursor];
    }

    protected function requireId(string $id, string $prefix): string
    {
        $id = trim($id);
        if ($id === '' || ! str_starts_with($id, $prefix)) {
            throw new InvalidArgumentException(sprintf('identificativo non valido: atteso un id con prefisso "%s", ricevuto "%s"', $prefix, $id));
        }

        return rawurlencode($id);
    }

    /** @return array<string, mixed> */
    protected function data(ApiResult $result): array
    {
        return $result->dataArray();
    }
}
