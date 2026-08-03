<?php

namespace Tests\Unit\Jobs;

use App\Contracts\FiscalProviderInterface;
use App\Contracts\SaleRepositoryInterface;
use App\Jobs\IssueFiscalDocumentJob;
use Illuminate\Foundation\Testing\TestCase;
use Mockery;

class IssueFiscalDocumentJobTest extends TestCase
{
    public function test_handle_emits_invoice_and_updates_fiscal_protocol(): void
    {
        $fiscalProvider = Mockery::mock(FiscalProviderInterface::class);
        $fiscalProvider->shouldReceive('emitInvoice')
            ->once()
            ->with(['sale_id' => 42, 'total' => 150.5])
            ->andReturn('NF-ABC123');

        $saleRepository = Mockery::mock(SaleRepositoryInterface::class);
        $saleRepository->shouldReceive('updateFiscalProtocol')
            ->once()
            ->with(42, 'NF-ABC123');

        $job = new IssueFiscalDocumentJob(42, 150.5);

        $job->handle($fiscalProvider, $saleRepository);

        // As expectativas do Mockery (emitInvoice/updateFiscalProtocol) são
        // validadas no tearDown via Mockery::close().
        $this->addToAssertionCount(1);
    }

    public function test_handle_persists_protocol_returned_by_provider(): void
    {
        $fiscalProvider = Mockery::mock(FiscalProviderInterface::class);
        $fiscalProvider->shouldReceive('emitInvoice')
            ->once()
            ->andReturn('NF-XYZ789');

        $saleRepository = Mockery::mock(SaleRepositoryInterface::class);
        $saleRepository->shouldReceive('updateFiscalProtocol')
            ->once()
            ->with(7, 'NF-XYZ789');

        $job = new IssueFiscalDocumentJob(7, 99.99);

        $job->handle($fiscalProvider, $saleRepository);

        // As expectativas do Mockery são validadas no tearDown via Mockery::close().
        $this->addToAssertionCount(1);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
