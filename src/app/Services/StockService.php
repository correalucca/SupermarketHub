<?php

namespace App\Services;

use App\Contracts\ProductRepositoryInterface;
use App\Contracts\StockServiceInterface;
use App\Exceptions\InsufficientStockException;

class StockService implements StockServiceInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {}

    public function verifyAndPrepare(array $items, bool $lock = false): array
    {
        $total = 0;
        $productData = [];

        foreach ($items as $item) {
            $product = $lock
                ? $this->productRepository->findWithLock($item['product_id'])
                : $this->productRepository->find($item['product_id']);

            if ($product->stock_quantity < $item['quantity']) {
                throw InsufficientStockException::forProduct(
                    $product->name,
                    $product->sku,
                    $product->stock_quantity,
                    $item['quantity'],
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
}
