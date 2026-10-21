<?php

namespace App\Providers;

use App\Contracts\ServiceEvidenceSource;
use App\Services\Requirements\LocalAttachmentEvidenceSource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ServiceEvidenceSource::class, LocalAttachmentEvidenceSource::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
