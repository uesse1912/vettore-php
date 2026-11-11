<?php

declare(strict_types=1);

namespace Vettore\Pagination;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Una pagina di risultati (paginazione a cursore). Passa $nextCursor alla prossima chiamata list(),
 * oppure usa il metodo iterate() della risorsa per scorrere tutto senza occuparti dei cursori.
 *
 * @template T
 *
 * @implements IteratorAggregate<int, T>
 */
final class Page implements Countable, IteratorAggregate
{
    /** @param  list<T>  $items */
    public function __construct(
        public readonly array $items,
        public readonly ?string $nextCursor,
        public readonly ?string $requestId = null,
    ) {}

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /** @return T|null */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return Traversable<int, T> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
