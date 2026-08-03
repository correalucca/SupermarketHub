<?php

namespace Tests\Unit\Services;

use App\Contracts\ProductRepositoryInterface;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Foundation\Testing\TestCase;
use Mockery;

class StockServiceTest extends TestCase
{
    private ProductRepositoryInterface $productRepository;
    private StockService $stockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->stockService = new StockService($this->productRepository);
    }

    private function product(int $id, string $sku, float $price, float $stock): Product
    {
        $product = new Product([
            'sku' => $sku,
            'name' => 'Produto ' . $sku,
            'price' => $price,
            'category' => 'Teste',
            'stock_quantity' => $stock,
        ]);

        // 'id' não é fillable; atribuímos diretamente para simular um modelo persistido.
        $product->id = $id;

        return $product;
    }

    public function test_verify_and_prepare_calculates_total_for_multiple_items(): void
    {
        $this->productRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($this->product(1, 'SKU-1', 10.50, 100));
        $this->productRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn($this->product(2, 'SKU-2', 5.25, 100));

        $result = $this->stockService->verifyAndPrepare([
            ['product_id' => 1, 'quantity' => 2],
            ['product_id' => 2, 'quantity' => 3],
        ]);

        $this->assertEquals((10.50 * 2) + (5.25 * 3), $result['total']);
        $this->assertCount(2, $result['items']);
        $this->assertEquals(21.0, $result['items'][0]['subtotal']);
        $this->assertEquals(15.75, $result['items'][1]['subtotal']);
        $this->assertSame(1, $result['items'][0]['product']->id);
    }

    public function test_verify_and_prepare_uses_locked_query_when_lock_is_true(): void
    {
        $this->productRepository
            ->shouldReceive('findWithLock')
            ->with(1)
            ->andReturn($this->product(1, 'SKU-1', 10.00, 50));

        $this->productRepository->shouldNotReceive('find');

        $result = $this->stockService->verifyAndPrepare(
            [['product_id' => 1, 'quantity' => 2]],
            lock: true,
        );

        $this->assertEquals(20.0, $result['total']);
    }

    public function test_verify_and_prepare_throws_when_stock_is_insufficient(): void
    {
        $this->productRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($this->product(1, 'SKU-1', 10.00, 5));

        $this->expectException(InsufficientStockException::class);
        $this->expectExceptionMessage(
            "Estoque insuficiente para o produto 'Produto SKU-1' (SKU: SKU-1). Disponível: 5, Solicitado: 999"
        );

        $this->stockService->verifyAndPrepare([
            ['product_id' => 1, 'quantity' => 999],
        ]);
    }

    public function test_verify_and_prepare_stops_at_first_insufficient_item(): void
    {
        $this->productRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($this->product(1, 'SKU-1', 10.00, 5));
        $this->productRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn($this->product(2, 'SKU-2', 5.00, 500));

        $this->expectException(InsufficientStockException::class);

        $this->stockService->verifyAndPrepare([
            ['product_id' => 1, 'quantity' => 10],
            ['product_id' => 2, 'quantity' => 1],
        ]);
    }

    public function test_calculate_total_sums_prices(): void
    {
        $this->productRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($this->product(1, 'SKU-1', 10.50, 100));
        $this->productRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn($this->product(2, 'SKU-2', 5.25, 100));

        $total = $this->stockService->calculateTotal([
            ['product_id' => 1, 'quantity' => 2],
            ['product_id' => 2, 'quantity' => 3],
        ]);

        $this->assertEquals((10.50 * 2) + (5.25 * 3), $total);
    }

    public function test_calculate_total_returns_zero_for_empty_items(): void
    {
        $this->productRepository->shouldNotReceive('find');

        $this->assertSame(0.0, $this->stockService->calculateTotal([]));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
