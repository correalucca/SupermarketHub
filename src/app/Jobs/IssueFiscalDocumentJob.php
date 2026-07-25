<?php

namespace App\Jobs;

use App\Contracts\FiscalProviderInterface;
use App\Repositories\SaleRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IssueFiscalDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $saleId,
        public readonly float $total,
    ) {}

    public function handle(
        FiscalProviderInterface $fiscalProvider,
        SaleRepository $saleRepository,
    ): void {
        $protocol = $fiscalProvider->emitInvoice([
            'sale_id' => $this->saleId,
            'total' => $this->total,
        ]);

        $saleRepository->updateFiscalProtocol($this->saleId, $protocol);

        Log::info('Job de nota fiscal executado', [
            'sale_id' => $this->saleId,
            'protocolo' => $protocol,
        ]);
    }
}
