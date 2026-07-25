<?php

namespace App\Contracts;

use App\Models\Sale;
use App\Models\SaleItem;

interface SaleRepositoryInterface
{
    public function create(array $data): Sale;
    public function find(int $id): Sale;
    public function addItem(Sale $sale, array $itemData): SaleItem;
    public function updateFiscalProtocol(int $saleId, string $protocol): void;
}
