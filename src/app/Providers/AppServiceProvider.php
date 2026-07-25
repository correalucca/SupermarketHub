<?php

namespace App\Providers;

use App\Contracts\FiscalProviderInterface;
use App\Contracts\ProductRepositoryInterface;
use App\Contracts\SaleRepositoryInterface;
use App\Repositories\ProductRepository;
use App\Repositories\SaleRepository;
use App\Services\MockFiscalProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FiscalProviderInterface::class, MockFiscalProvider::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(SaleRepositoryInterface::class, SaleRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
