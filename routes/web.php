<?php

use App\Http\Controllers\Api\XenditCallbackController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Console\ApiKeyController;
use App\Http\Controllers\Console\BillingController;
use App\Http\Controllers\Console\ChannelProviderController;
use App\Http\Controllers\Console\DashboardController;
use App\Http\Controllers\Console\EnvironmentController;
use App\Http\Controllers\Console\MessageLogController;
use App\Http\Controllers\Console\SettingsController;
use App\Http\Controllers\Console\SubscriberController;
use App\Http\Controllers\Console\TeamController;
use App\Http\Controllers\Console\WebhookController;
use App\Http\Controllers\Console\WorkflowController;
use App\Http\Controllers\Console\WorkflowTestController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\MarketingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MarketingController::class, 'landing'])->name('landing');

// --- Dokumentasi publik ---
Route::get('/docs', [DocsController::class, 'index'])->name('docs.index');
Route::get('/docs/search.json', [DocsController::class, 'search'])->name('docs.search');
Route::get('/docs/{slug}', [DocsController::class, 'show'])->where('slug', '[a-z0-9\-]+')->name('docs.show');

// --- Callback pembayaran Xendit (diteruskan router webhook pusat VexaHost, prefix external_id NTF-) ---
Route::match(['get', 'post'], '/api/payment/xendit/callback', [XenditCallbackController::class, 'handle'])->middleware('throttle:240,1')->name('api.payment.xendit.callback');
Route::match(['get', 'post'], '/api/payment/xendit/webhook', [XenditCallbackController::class, 'handle'])->middleware('throttle:240,1');
Route::match(['get', 'post'], '/api/webhooks/xendit', [XenditCallbackController::class, 'handle'])->middleware('throttle:240,1');

// --- Auth ---------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.attempt');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register.attempt');
    Route::get('/forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendReset'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->name('password.update');
});
Route::get('/invitations/{token}', [AuthController::class, 'acceptInvitation'])->name('invitation.accept');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// --- Console ------------------------------------------------------------
Route::middleware(['auth', 'env.set'])->prefix('console')->name('console.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/dashboard', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::post('/environment', [EnvironmentController::class, 'switch'])->name('environment.switch');

    // Baca (VIEWER+)
    Route::get('/workflows', [WorkflowController::class, 'index'])->name('workflows.index');
    Route::get('/workflows/{workflow}/edit', [WorkflowController::class, 'edit'])->name('workflows.edit');
    Route::get('/subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');
    Route::get('/subscribers/{subscriber}', [SubscriberController::class, 'show'])->name('subscribers.show');
    Route::get('/channels', [ChannelProviderController::class, 'index'])->name('channels.index');
    Route::get('/logs', [MessageLogController::class, 'index'])->name('logs.index');
    Route::get('/logs/{id}', [MessageLogController::class, 'show'])->name('logs.show');
    Route::get('/settings/account', [SettingsController::class, 'account'])->name('account');
    Route::put('/settings/account', [SettingsController::class, 'updateAccount'])->name('account.update');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('account.password');

    // Tulis (DEVELOPER+)
    Route::middleware('role:DEVELOPER')->group(function () {
        Route::post('/workflows', [WorkflowController::class, 'store'])->name('workflows.store');
        Route::put('/workflows/{workflow}', [WorkflowController::class, 'update'])->name('workflows.update');
        Route::post('/workflows/{workflow}/publish', [WorkflowController::class, 'publish'])->name('workflows.publish');
        Route::post('/workflows/{workflow}/toggle', [WorkflowController::class, 'toggle'])->name('workflows.toggle');
        Route::post('/workflows/{workflow}/archive', [WorkflowController::class, 'archive'])->name('workflows.archive');
        Route::delete('/workflows/{workflow}', [WorkflowController::class, 'destroy'])->name('workflows.destroy');
        Route::get('/workflows/{workflow}/test', [WorkflowTestController::class, 'show'])->name('workflows.test');
        Route::post('/workflows/{workflow}/test', [WorkflowTestController::class, 'send'])->name('workflows.test.send');
        Route::get('/api/events/{eventId}', [WorkflowTestController::class, 'status'])->name('events.status');
        Route::post('/api/templates/preview', [WorkflowTestController::class, 'preview'])->name('templates.preview');

        Route::post('/subscribers/{subscriber}/preferences', [SubscriberController::class, 'preferences'])->name('subscribers.preferences');
        Route::delete('/subscribers/{subscriber}', [SubscriberController::class, 'destroy'])->name('subscribers.destroy');

        Route::get('/channels/{channel}', [ChannelProviderController::class, 'edit'])->name('channels.edit');
        Route::put('/channels/{channel}', [ChannelProviderController::class, 'update'])->name('channels.update');
        Route::post('/channels/{channel}/test', [ChannelProviderController::class, 'test'])->name('channels.test');
        Route::delete('/channels/{channel}', [ChannelProviderController::class, 'destroy'])->name('channels.destroy');

        Route::get('/settings/api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
        Route::post('/settings/api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
        Route::delete('/settings/api-keys/{id}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');

        Route::get('/settings/webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
        Route::post('/settings/webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
        Route::post('/settings/webhooks/{webhook}/toggle', [WebhookController::class, 'toggle'])->name('webhooks.toggle');
        Route::delete('/settings/webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');
        Route::post('/settings/webhooks/deliveries/{delivery}/replay', [WebhookController::class, 'replay'])->name('webhooks.replay');
    });

    // Pembelian plan (OWNER)
    Route::middleware('role:OWNER')->group(function () {
        Route::get('/billing/checkout/{plan}', [BillingController::class, 'checkout'])->name('billing.checkout');
        Route::post('/billing/checkout', [BillingController::class, 'store'])->name('billing.store');
        Route::post('/billing/payments/{id}/cancel', [BillingController::class, 'cancel'])->name('billing.payments.cancel');
        Route::post('/billing/payments/{id}/simulate', [BillingController::class, 'simulate'])->name('billing.payments.simulate');
    });

    // Administrasi (ADMIN+)
    Route::middleware('role:ADMIN')->group(function () {
        Route::get('/settings/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('/settings/team/invite', [TeamController::class, 'invite'])->name('team.invite');
        Route::put('/settings/team/{member}', [TeamController::class, 'update'])->name('team.update');
        Route::delete('/settings/team/{member}', [TeamController::class, 'destroy'])->name('team.destroy');
        Route::delete('/settings/team/invitations/{id}', [TeamController::class, 'cancelInvite'])->name('team.invite.cancel');
        Route::get('/settings/usage', [SettingsController::class, 'usage'])->name('usage');
        Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
        Route::get('/billing/payments/{id}', [BillingController::class, 'show'])->name('billing.payments.show');
        Route::get('/billing/payments/{id}/status', [BillingController::class, 'status'])->name('billing.payments.status');
        Route::get('/settings/audit', [SettingsController::class, 'audit'])->name('audit');
    });
});
