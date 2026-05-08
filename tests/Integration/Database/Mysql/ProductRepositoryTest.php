<?php

declare(strict_types=1);

namespace Tests\Integration\Database\Mysql;

use App\Database\Mysql\ProductRepository;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DatabaseTestCase;

final class ProductRepositoryTest extends DatabaseTestCase
{
    private ProductRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ProductRepository($this->pdo);
    }

    public function testFindByIdReturnsHydratedProduct(): void
    {
        $id = $this->seedProduct('Coke', '3.99', 20);

        $product = $this->repo->findById($id);

        $this->assertNotNull($product);
        $this->assertSame($id, $product->id);
        $this->assertSame('Coke', $product->name);
        $this->assertSame('3.990', $product->price);
        $this->assertSame(20, $product->quantityAvailable);
    }

    public function testFindByIdReturnsNullForMissing(): void
    {
        $this->assertNull($this->repo->findById(999_999));
    }

    public function testCreateReturnsInsertedId(): void
    {
        $id = $this->repo->create('Pepsi', '6.885', 20);

        $this->assertGreaterThan(0, $id);
        $product = $this->repo->findById($id);
        $this->assertNotNull($product);
        $this->assertSame('Pepsi', $product->name);
        $this->assertSame('6.885', $product->price);
        $this->assertSame(20, $product->quantityAvailable);
    }

    public function testUpdateChangesValuesAndReportsTrue(): void
    {
        $id = $this->seedProduct('Water', '0.500', 50);

        $changed = $this->repo->update($id, 'Spring Water', '0.600', 40);

        $this->assertTrue($changed);
        $product = $this->repo->findById($id);
        $this->assertNotNull($product);
        $this->assertSame('Spring Water', $product->name);
        $this->assertSame('0.600', $product->price);
        $this->assertSame(40, $product->quantityAvailable);
    }

    public function testUpdateReportsFalseForMissingRow(): void
    {
        $this->assertFalse($this->repo->update(999_999, 'x', '1.000', 0));
    }

    public function testDeleteRemovesRowAndReportsTrue(): void
    {
        $id = $this->seedProduct('Throwaway', '1.000', 1);

        $this->assertTrue($this->repo->delete($id));
        $this->assertNull($this->repo->findById($id));
    }

    public function testDeleteReportsFalseForMissingRow(): void
    {
        $this->assertFalse($this->repo->delete(999_999));
    }

    public function testPaginateClampsAndOrdersByDefault(): void
    {
        $this->seedProduct('Apple', '1.000', 1);
        $this->seedProduct('Banana', '2.000', 2);
        $this->seedProduct('Cherry', '3.000', 3);

        $page = $this->repo->paginate();

        $this->assertSame(1, $page['page']);
        $this->assertSame(20, $page['perPage']);
        $this->assertSame(3, $page['total']);
        $this->assertCount(3, $page['items']);
        $this->assertSame('Apple', $page['items'][0]->name);
        $this->assertSame('Cherry', $page['items'][2]->name);
    }

    public function testPaginateAppliesPageOffset(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->seedProduct("Item{$i}", '1.000', $i);
        }

        $first = $this->repo->paginate(page: 1, perPage: 2);
        $second = $this->repo->paginate(page: 2, perPage: 2);
        $third = $this->repo->paginate(page: 3, perPage: 2);

        $this->assertCount(2, $first['items']);
        $this->assertCount(2, $second['items']);
        $this->assertCount(1, $third['items']);
        $this->assertSame(5, $first['total']);
        $this->assertNotEquals($first['items'][0]->id, $second['items'][0]->id);
    }

    public function testPaginateOutOfRangePageReturnsEmpty(): void
    {
        $this->seedProduct('Solo', '1.000', 1);

        $page = $this->repo->paginate(page: 999);

        $this->assertSame([], $page['items']);
        $this->assertSame(1, $page['total']);
    }

    /**
     * @param array{page: int, perPage: int} $input
     * @param array{page: int, perPage: int} $expected
     */
    #[DataProvider('clampingCases')]
    public function testPaginateClampsPageAndPerPage(array $input, array $expected): void
    {
        $page = $this->repo->paginate(page: $input['page'], perPage: $input['perPage']);

        $this->assertSame($expected['page'], $page['page']);
        $this->assertSame($expected['perPage'], $page['perPage']);
    }

    /**
     * @return iterable<string, array{0: array{page: int, perPage: int}, 1: array{page: int, perPage: int}}>
     */
    public static function clampingCases(): iterable
    {
        yield 'zero page clamps to 1'       => [['page' => 0,    'perPage' => 20], ['page' => 1, 'perPage' => 20]];
        yield 'negative page clamps to 1'   => [['page' => -5,   'perPage' => 20], ['page' => 1, 'perPage' => 20]];
        yield 'zero perPage clamps to 1'    => [['page' => 1,    'perPage' => 0],  ['page' => 1, 'perPage' => 1]];
        yield 'huge perPage clamps to max'  => [['page' => 1,    'perPage' => 999], ['page' => 1, 'perPage' => 100]];
    }

    /**
     * @param list<array{name: string, price: string, qty: int}> $seed
     * @param list<string> $expectedNameOrder
     */
    #[DataProvider('sortingCases')]
    public function testPaginateSortsByAllowedColumn(
        array $seed,
        string $sort,
        string $direction,
        array $expectedNameOrder,
    ): void {
        foreach ($seed as $item) {
            $this->seedProduct($item['name'], $item['price'], $item['qty']);
        }

        $page = $this->repo->paginate(sort: $sort, direction: $direction);
        $names = array_map(static fn ($p) => $p->name, $page['items']);

        $this->assertSame($expectedNameOrder, $names);
    }

    /**
     * @return iterable<string, array{
     *     0: list<array{name: string, price: string, qty: int}>,
     *     1: string,
     *     2: string,
     *     3: list<string>
     * }>
     */
    public static function sortingCases(): iterable
    {
        $seed = [
            ['name' => 'Banana', 'price' => '2.000', 'qty' => 5],
            ['name' => 'Apple',  'price' => '3.500', 'qty' => 1],
            ['name' => 'Cherry', 'price' => '1.000', 'qty' => 10],
        ];

        yield 'name asc'  => [$seed, 'name',  'asc',  ['Apple', 'Banana', 'Cherry']];
        yield 'name desc' => [$seed, 'name',  'desc', ['Cherry', 'Banana', 'Apple']];
        yield 'price asc' => [$seed, 'price', 'asc',  ['Cherry', 'Banana', 'Apple']];
        yield 'price desc' => [$seed, 'price', 'desc', ['Apple', 'Banana', 'Cherry']];
        yield 'quantity_available asc' => [$seed, 'quantity_available', 'asc', ['Apple', 'Banana', 'Cherry']];
        yield 'direction case-insensitive' => [$seed, 'name', 'DESC', ['Cherry', 'Banana', 'Apple']];
    }

    public function testPaginateRejectsDisallowedSortColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Disallowed sort column: password_hash/');

        $this->repo->paginate(sort: 'password_hash');
    }

    public function testPaginateRejectsDisallowedDirection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Disallowed sort direction: drop/');

        $this->repo->paginate(direction: 'drop');
    }
}
