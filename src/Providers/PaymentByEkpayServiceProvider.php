<?php

namespace Sayeed\PaymentByEkpay\Providers;

use Illuminate\Support\ServiceProvider;

class PaymentByEkpayServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
		$this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
		$this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
		$this->publishes([
			__DIR__ . '/../resources/views' => resource_path('views/'),
		]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
		$this->mergeConfigFrom(
			__DIR__.'/../config/ekpay.php', 'ekpay'
		);
    }
}
