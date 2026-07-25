<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Repositories\SaleRepository;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\TestCase;
use Mockery;

class SaleServiceTest extends TestCase
{
    private SaleService $saleService;
    private ProductRepository $productRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $saleRepository = Mockery::mock(SaleRepository::class);
        $this->productRepository = Mockery::mock(ProductRepository::class);

        $this->saleService = new SaleService(
            $saleRepository,
            $this->productRepository,
        );
    }

    public function test_calculate_total_with_multiple_items(): void
    {
        $items = [
            ['product_id' => 1, 'quantity' => 2],
            ['product_id' => 2, 'quantity' => 3],
        ];

        $this->productRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn(new Product(['price' => 10.50]));

        $this->productRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn(new Product(['price' => 5.25]));

        $total = $this->saleService->calculateTotal($items);

        $expected = (10.50 * 2) + (5.25 * 3);
        $this->assertEquals($expected, $total);
    }

    public function test_calculate_total_with_single_item(): void
    {
        $items = [
            ['product_id' => 1, 'quantity' => 5],
        ];

        $this->productRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn(new Product(['price' => 20.00]));

        $total = $this->saleService->calculateTotal($items);

        $this->assertEquals(100.00, $total);
    }

    public function test_calculate_total_returns_zero_for_empty_items(): void
    {
        $total = $this->saleService->calculateTotal([]);

        $this->assertEquals(0, $total);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
