<?php

namespace App\Providers;

use App\Ai\ClaudeGateway;
use App\Ai\SdkClaudeGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ClaudeGateway::class, SdkClaudeGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
