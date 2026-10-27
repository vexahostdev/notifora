<?php

namespace App\Providers;

use App\Listeners\LogEmailSent;
use App\Services\PlanCatalog;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
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
        PlanCatalog::apply(); // gabungkan plan dari DB (Admin → Plans) ke config
        Event::listen(MessageSent::class, [LogEmailSent::class, 'onSent']);
    }
}
