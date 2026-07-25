<?php

namespace App\Repositories;

use App\Models\Sale;
use App\Models\SaleItem;

class SaleRepository
{
    public function create(array $data): Sale
    {
        return Sale::create($data);
    }

    public function find(int $id): Sale
    {
        return Sale::with('items.product')->findOrFail($id);
    }

    public function addItem(Sale $sale, array $itemData): SaleItem
    {
        return $sale->items()->create($itemData);
    }

    public function updateFiscalProtocol(int $saleId, string $protocol): void
    {
        Sale::where('id', $saleId)->update(['fiscal_protocol' => $protocol]);
    }
}
