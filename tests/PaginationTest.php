<?php

declare(strict_types=1);

namespace Vettore\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vettore\Dto\Shipment;
use Vettore\Pagination\Page;
use Vettore\Pagination\Paginator;
use Vettore\Tests\Support\FakeApi;
use Vettore\Tests\Support\Fixtures;

final class PaginationTest extends TestCase
{
    #[Test]
    public function list_returns_a_page_with_the_next_cursor(): void
    {
        $api = FakeApi::create()->success([Fixtures::shipment(['id' => 'shp_1']), Fixtures::shipment(['id' => 'shp_2'])], meta: ['next_cursor' => 'eyJpZCI6Mn0']);

        $page = $api->client->shipments()->list(['state' => 'ON_HOLD'], 2);

        self::assertInstanceOf(Page::class, $page);
        self::assertCount(2, $page);
        self::assertTrue($page->hasMore());
        self::assertSame('eyJpZCI6Mn0', $page->nextCursor);
        self::assertSame('req_test', $page->requestId);
        self::assertSame(['shp_1', 'shp_2'], array_map(fn (Shipment $s) => $s->id, iterator_to_array($page)));
    }

    #[Test]
    public function iterate_follows_cursors_lazily_until_the_end(): void
    {
        $api = FakeApi::create()
            ->success([Fixtures::shipment(['id' => 'shp_1'])], meta: ['next_cursor' => 'c1'])
            ->success([Fixtures::shipment(['id' => 'shp_2'])], meta: ['next_cursor' => 'c2'])
            ->success([Fixtures::shipment(['id' => 'shp_3'])], meta: ['next_cursor' => null]);

        $ids = [];
        foreach ($api->client->shipments()->iterate(['merchant_id' => 'mer_1'], 1) as $shipment) {
            $ids[] = $shipment->id;
        }

        self::assertSame(['shp_1', 'shp_2', 'shp_3'], $ids);
        self::assertSame(3, $api->requestCount());
        self::assertSame('merchant_id=mer_1&limit=1&cursor=c2', urldecode($api->lastRequest()->getUri()->getQuery()));
        self::assertStringNotContainsString('cursor', $api->history[0]['request']->getUri()->getQuery());
    }

    #[Test]
    public function iteration_stops_early_when_the_consumer_breaks(): void
    {
        $api = FakeApi::create()
            ->success([Fixtures::shipment(['id' => 'shp_1'])], meta: ['next_cursor' => 'c1'])
            ->success([Fixtures::shipment(['id' => 'shp_2'])], meta: ['next_cursor' => 'c2']);

        foreach ($api->client->shipments()->iterate() as $shipment) {
            if ($shipment->id === 'shp_1') {
                break;
            }
        }

        self::assertSame(1, $api->requestCount());
    }

    #[Test]
    public function paginator_can_cap_the_number_of_pages(): void
    {
        $pages = 0;
        $paginator = new Paginator(function (?string $cursor) use (&$pages): Page {
            $pages++;

            return new Page([$cursor ?? 'prima'], 'cursore-'.$pages);
        }, maxPages: 2);

        self::assertSame(['prima', 'cursore-1'], $paginator->toArray());
        self::assertSame(2, $pages);
    }

    #[Test]
    public function limit_outside_the_api_bounds_is_rejected_locally(): void
    {
        $api = FakeApi::create();

        $this->expectException(\InvalidArgumentException::class);
        $api->client->merchants()->list(201);
    }
}
