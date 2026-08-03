<?php

namespace Tests\Unit\Services;

use App\Contracts\ProductRepositoryInterface;
use App\Contracts\SaleRepositoryInterface;
use App\Contracts\StockServiceInterface;
use App\Enums\StockMovementType;
use App\Jobs\IssueFiscalDocumentJob;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SaleServiceTest extends TestCase
{
    private SaleRepositoryInterface $saleRepository;
    private ProductRepositoryInterface $productRepository;
    private StockServiceInterface $stockService;
    private SaleService $saleService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->saleRepository = Mockery::mock(SaleRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->stockService = Mockery::mock(StockServiceInterface::class);

        $this->saleService = new SaleService(
            $this->saleRepository,
            $this->productRepository,
            $this->stockService,
        );
    }

    public function test_create_sale_persists_items_decrements_stock_and_dispatches_job(): void
    {
        Queue::fake();

        $product = new Product([
            'sku' => 'SKU-1',
            'name' => 'Produto Teste',
            'price' => 25.00,
            'stock_quantity' => 100,
        ]);

        // 'id' não é fillable; atribuímos diretamente para simular um modelo persistido.
        $product->id = 10;

        $sale = Mockery::mock(Sale::class)->makePartial();
        $sale->fill(['total' => 75.00, 'status' => 'completed']);
        // 'id' não é fillable; atribuímos diretamente para simular um modelo persistido.
        $sale->id = 1;
        $sale->shouldReceive('load')
            ->once()
            ->with('items.product')
            ->andReturnSelf();
        $sale->shouldReceive('toArray')
            ->andReturn([
                'id' => 1,
                'total' => 75.00,
                'status' => 'completed',
                'items' => [],
            ]);

        $items = [['product_id' => 10, 'quantity' => 3]];

        $prepared = [
            'total' => 75.00,
            'items' => [
                [
                    'product' => $product,
                    'quantity' => 3,
                    'unit_price' => 25.00,
                    'subtotal' => 75.00,
                ],
            ],
        ];

        $this->stockService->shouldReceive('verifyAndPrepare')
            ->once()
            ->with($items, true)
            ->andReturn($prepared);

        $this->saleRepository->shouldReceive('create')
            ->once()
            ->with(['total' => 75.00, 'status' => 'completed'])
            ->andReturn($sale);

        $this->saleRepository->shouldReceive('addItem')
            ->once()
            ->with($sale, [
                'product_id' => 10,
                'quantity' => 3,
                'unit_price' => 25.00,
                'subtotal' => 75.00,
            ])
            ->andReturn(Mockery::mock(\App\Models\SaleItem::class));

        $this->productRepository->shouldReceive('decrementStock')
            ->once()
            ->with(10, 3);

        $this->productRepository->shouldReceive('createStockMovement')
            ->once()
            ->with(10, Mockery::on(function (array $data) {
                return $data['type'] === StockMovementType::Out
                    && $data['quantity'] === 3
                    && $data['unit_price'] === 25.00
                    && $data['reference_type'] === 'sale'
                    && $data['reference_id'] === 1
                    && str_contains($data['notes'], 'Venda #1');
            }));

        $result = $this->saleService->createSale($items);

        $this->assertSame(75.00, $result['total']);

        Queue::assertPushed(IssueFiscalDocumentJob::class, function (IssueFiscalDocumentJob $job) {
            return $job->saleId === 1 && $job->total === 75.00;
        });
    }

    public function test_create_sale_rolls_back_and_rethrows_when_preparation_fails(): void
    {
        $items = [['product_id' => 10, 'quantity' => 999]];

        $this->stockService->shouldReceive('verifyAndPrepare')
            ->once()
            ->andThrow(new \RuntimeException('Estoque insuficiente'));

        $this->saleRepository->shouldNotReceive('create');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Estoque insuficiente');

        $this->saleService->createSale($items);
    }

    public function test_create_sale_rolls_back_when_persistence_fails(): void
    {
        Queue::fake();

        $product = new Product([
            'sku' => 'SKU-1',
            'name' => 'Produto Teste',
            'price' => 25.00,
            'stock_quantity' => 100,
        ]);

        // 'id' não é fillable; atribuímos diretamente para simular um modelo persistido.
        $product->id = 10;

        $sale = Mockery::mock(Sale::class)->makePartial();
        $sale->fill(['id' => 1, 'total' => 75.00, 'status' => 'completed']);

        $items = [['product_id' => 10, 'quantity' => 3]];

        $prepared = [
            'total' => 75.00,
            'items' => [
                [
                    'product' => $product,
                    'quantity' => 3,
                    'unit_price' => 25.00,
                    'subtotal' => 75.00,
                ],
            ],
        ];

        $this->stockService->shouldReceive('verifyAndPrepare')
            ->once()
            ->andReturn($prepared);

        $this->saleRepository->shouldReceive('create')
            ->once()
            ->andReturn($sale);

        // Falha ao gravar o item: a transação deve ser revertida e a exceção propagada.
        $this->saleRepository->shouldReceive('addItem')
            ->once()
            ->andThrow(new \RuntimeException('Falha ao gravar item'));

        $this->productRepository->shouldNotReceive('decrementStock');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Falha ao gravar item');

        $this->saleService->createSale($items);

        Queue::assertNotPushed(IssueFiscalDocumentJob::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
