<?php

namespace App\Contracts;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

interface ProductRepositoryInterface
{
    public function all(): Collection;
    public function find(int $id): Product;
    public function create(array $data): Product;
    public function update(int $id, array $data): Product;
    public function delete(int $id): void;
    public function decrementStock(int $id, float $quantity): void;
    public function findWithLock(int $id): Product;
    public function createStockMovement(int $productId, array $data): void;
}
