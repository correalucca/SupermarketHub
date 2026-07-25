<?php

namespace App\Repositories;

use App\Contracts\ProductRepositoryInterface;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository implements ProductRepositoryInterface
{
    public function all(): Collection
    {
        return Product::all();
    }

    public function find(int $id): Product
    {
        return Product::findOrFail($id);
    }

    public function findBySku(string $sku): ?Product
    {
        return Product::where('sku', $sku)->first();
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(int $id, array $data): Product
    {
        $product = $this->find($id);
        $product->update($data);
        return $product->fresh();
    }

    public function delete(int $id): void
    {
        $product = $this->find($id);
        $product->delete();
    }

    public function decrementStock(int $id, float $quantity): void
    {
        Product::where('id', $id)->decrement('stock_quantity', $quantity);
    }
}
