<?php

namespace App\Contracts;

interface StockServiceInterface
{
    public function verifyAndPrepare(array $items): array;
    public function calculateTotal(array $items): float;
}
