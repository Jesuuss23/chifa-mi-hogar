<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer([
            'storefront.index', 'storefront.show',
            'storefront.combos.index', 'storefront.combos.show',
        ], function ($view) {
            $view->with('seo', app(\App\Services\StorefrontSeoService::class)
                ->forView($view->name(), $view->getData()));
        });

        \Illuminate\Support\Facades\View::composer(['layouts.storefront', 'layouts.admin'], function ($view) {
            $settings = \App\Models\StoreSetting::current()->load('logo');
            $view->with('brandLogoUrl', $settings->logoUrl());
            $view->with('legalPagesAvailable', $settings->legalPagesAvailable());
        });
    }
}
