<?php

namespace App\Services;

use App\Contracts\FiscalProviderInterface;

class MockFiscalProvider implements FiscalProviderInterface
{
    public function emitInvoice(array $saleData): string
    {
        $protocol = 'NF-' . strtoupper(uniqid());

        \Illuminate\Support\Facades\Log::info('Nota fiscal emitida', [
            'protocolo' => $protocol,
            'venda_id' => $saleData['sale_id'] ?? null,
            'total' => $saleData['total'] ?? null,
        ]);

        return $protocol;
    }
}
