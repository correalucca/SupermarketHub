<?php

namespace App\Services;

use App\Contracts\FiscalProviderInterface;
use App\Contracts\ProductRepositoryInterface;
use App\Contracts\SaleRepositoryInterface;
use App\Enums\StockMovementType;
use App\Jobs\IssueFiscalDocumentJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleService
{
    public function __construct(
        private readonly SaleRepositoryInterface $saleRepository,
        private readonly ProductRepositoryInterface $productRepository,
    ) {}

    public function createSale(array $items): array
    {
        $total = 0;
        $productData = [];

        foreach ($items as $item) {
            $product = $this->productRepository->find($item['product_id']);

            if ($product->stock_quantity < $item['quantity']) {
                throw new \RuntimeException(
                    "Estoque insuficiente para o produto '{$product->name}' (SKU: {$product->sku}). "
                    . "Disponível: {$product->stock_quantity}, Solicitado: {$item['quantity']}"
                );
            }

            $subtotal = $product->price * $item['quantity'];
            $total += $subtotal;

            $productData[] = [
                'product' => $product,
                'quantity' => $item['quantity'],
                'unit_price' => $product->price,
                'subtotal' => $subtotal,
            ];
        }

        DB::beginTransaction();
        try {
            $sale = $this->saleRepository->create([
                'total' => $total,
                'status' => 'completed',
            ]);

            foreach ($productData as $data) {
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

                $data['product']->stockMovements()->create([
                    'type' => StockMovementType::Out,
                    'quantity' => $data['quantity'],
                    'unit_price' => $data['unit_price'],
                    'reference_type' => 'sale',
                    'reference_id' => $sale->id,
                    'notes' => "Venda #{$sale->id} - {$data['product']->name}",
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        Log::info('Venda finalizada', [
            'sale_id' => $sale->id,
            'total' => $total,
            'itens' => count($items),
        ]);

        IssueFiscalDocumentJob::dispatch($sale->id, $total);

        $sale->load('items.product');

        return $sale->toArray();
    }

    public function calculateTotal(array $items): float
    {
        $total = 0;

        foreach ($items as $item) {
            $product = $this->productRepository->find($item['product_id']);
            $total += $product->price * $item['quantity'];
        }

        return $total;
    }
}
