<?php

namespace App\Services;

use App\Contracts\ProductRepositoryInterface;
use App\Contracts\SaleRepositoryInterface;
use App\Contracts\StockServiceInterface;
use App\Enums\StockMovementType;
use App\Jobs\IssueFiscalDocumentJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleService
{
    public function __construct(
        private readonly SaleRepositoryInterface $saleRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StockServiceInterface $stockService,
    ) {}

    public function createSale(array $items): array
    {
        DB::beginTransaction();
        try {
            $prepared = $this->stockService->verifyAndPrepare($items, lock: true);

            $sale = $this->saleRepository->create([
                'total' => $prepared['total'],
                'status' => 'completed',
            ]);

            foreach ($prepared['items'] as $data) {
                $this->saleRepository->addItem($sale, [
                    'product_id' => $data['product']->id,
                    'quantity' => $data['quantity'],
                    'unit_price' => $data['unit_price'],
                    'subtotal' => $data['subtotal'],
                ]);

                $this->productRepository->decrementStock(
                    $data['product']->id,
                    $data['quantity']
                );

                $this->productRepository->createStockMovement(
                    $data['product']->id,
                    [
                        'type' => StockMovementType::Out,
                        'quantity' => $data['quantity'],
                        'unit_price' => $data['unit_price'],
                        'reference_type' => 'sale',
                        'reference_id' => $sale->id,
                        'notes' => "Venda #{$sale->id} - {$data['product']->name}",
                    ]
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        Log::info('Venda finalizada', [
            'sale_id' => $sale->id,
            'total' => $prepared['total'],
            'itens' => count($items),
        ]);

        IssueFiscalDocumentJob::dispatch($sale->id, $prepared['total']);

        $sale->load('items.product');

        return $sale->toArray();
    }

    public function calculateTotal(array $items): float
    {
        return $this->stockService->calculateTotal($items);
    }
}
