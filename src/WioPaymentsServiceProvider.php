<?php

declare(strict_types=1);

namespace Wio\WioPayments;

use Illuminate\Support\ServiceProvider;
use Wio\WioPayments\Contracts\PaymentInterface;

class WioPaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/wiopayments.php',
            'wiopayments'
        );

        $this->app->singleton(WioPayments::class, function ($app) {
            return new WioPayments(
                config('wiopayments.api_key'),
                config('wiopayments.secret_key')
            );
        });

        $this->app->alias(WioPayments::class, PaymentInterface::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/wiopayments.php' => config_path('wiopayments.php'),
            ], 'wiopayments-config');
        }

        // Register custom validation rules if needed
        $this->registerValidationRules();
    }

    public function provides(): array
    {
        return [
            WioPayments::class,
            PaymentInterface::class,
        ];
    }

    private function registerValidationRules(): void
    {
        // Custom validation rules for currency, amount, etc.
        \Illuminate\Support\Facades\Validator::extend('supported_currency', function ($attribute, $value, $parameters, $validator) {
            return \Wio\WioPayments\Support\Currency::isSupported($value);
        });
    }
}
