<?php

namespace App\Providers;

use App\Listeners\LogEmailSent;
use App\Listeners\RecordWorkerHeartbeat;
use App\Services\Brand;
use App\Services\MailSettings;
use App\Services\PlanCatalog;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
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
        Brand::apply();       // nama, email dukungan, nama pengirim (Admin → Branding)
        MailSettings::apply(); // SMTP dari Admin → Email → SMTP bila diaktifkan
        Event::listen(MessageSent::class, [LogEmailSent::class, 'onSent']);
        Event::listen([Looping::class, JobProcessing::class, JobProcessed::class, JobFailed::class], RecordWorkerHeartbeat::class);
    }
}
