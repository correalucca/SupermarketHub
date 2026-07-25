<?php

namespace App\Contracts;

interface FiscalProviderInterface
{
    public function emitInvoice(array $saleData): string;
}
