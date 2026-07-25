<?php

namespace App\Services;

use App\Contracts\ProductRepositoryInterface;
use App\Contracts\StockServiceInterface;

class StockService implements StockServiceInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {}

    public function verifyAndPrepare(array $items): array
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

        return [
            'total' => $total,
            'items' => $productData,
        ];
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
