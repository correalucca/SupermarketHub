<?php

namespace App\Providers;

use App\Contracts\FiscalProviderInterface;
use App\Contracts\ProductRepositoryInterface;
use App\Contracts\SaleRepositoryInterface;
use App\Contracts\StockServiceInterface;
use App\Repositories\ProductRepository;
use App\Repositories\SaleRepository;
use App\Services\MockFiscalProvider;
use App\Services\StockService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FiscalProviderInterface::class, MockFiscalProvider::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(SaleRepositoryInterface::class, SaleRepository::class);
        $this->app->bind(StockServiceInterface::class, StockService::class);
    }

    public function boot(): void
    {
        //
    }
}
