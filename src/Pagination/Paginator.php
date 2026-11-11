<?php

declare(strict_types=1);

namespace Vettore\Pagination;

use Closure;
use Generator;
use IteratorAggregate;

/**
 * Scorre tutte le pagine di una lista seguendo meta.next_cursor, una pagina alla volta (lazy).
 *
 *   foreach ($vettore->shipments()->iterate(['state' => 'ON_HOLD']) as $shipment) { ... }
 *
 * @template T
 *
 * @implements IteratorAggregate<int, T>
 */
final class Paginator implements IteratorAggregate
{
    /** @param  Closure(?string): Page<T>  $fetchPage  riceve il cursore (null per la prima pagina) */
    public function __construct(
        private readonly Closure $fetchPage,
        private readonly int $maxPages = PHP_INT_MAX,
    ) {}

    /** @return Generator<int, T> */
    public function getIterator(): Generator
    {
        $cursor = null;
        $pages = 0;
        do {
            $page = ($this->fetchPage)($cursor);
            $pages++;
            foreach ($page->items as $item) {
                yield $item;
            }
            $cursor = $page->nextCursor;
        } while ($cursor !== null && $pages < $this->maxPages);
    }

    /** @return list<T> */
    public function toArray(): array
    {
        return iterator_to_array($this->getIterator(), false);
    }
}
