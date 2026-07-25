<?php

namespace App\Contracts;

interface StockServiceInterface
{
    public function verifyAndPrepare(array $items, bool $lock = false): array;
    public function calculateTotal(array $items): float;
}
