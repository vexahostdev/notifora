<?php

use App\Http\Controllers\Admin\AbuseController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\CommsController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EmailController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\OpsController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\XenditCallbackController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\TwoFactorController;
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
    Route::get('/two-factor-challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorController::class, 'verify'])->middleware('throttle:5,1')->name('two-factor.verify');
});
Route::get('/invitations/{token}', [AuthController::class, 'acceptInvitation'])->name('invitation.accept');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// --- Keamanan akun (2FA) & impersonasi: untuk pengguna login mana pun ---------
Route::middleware('auth')->group(function () {
    Route::get('/security/2fa', [TwoFactorController::class, 'show'])->name('security.2fa');
    Route::post('/security/2fa/start', [TwoFactorController::class, 'start'])->name('security.2fa.start');
    Route::post('/security/2fa/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('security.2fa.confirm');
    Route::delete('/security/2fa', [TwoFactorController::class, 'disable'])->middleware('throttle:10,1')->name('security.2fa.disable');
    Route::post('/security/2fa/recovery', [TwoFactorController::class, 'regenerate'])->name('security.2fa.recovery');
    Route::post('/impersonation/stop', [ImpersonationController::class, 'stop'])->name('impersonation.stop');
});

// --- Panel operator platform (/admin) -----------------------------------------
Route::middleware(['auth', 'platform.admin', 'throttle:240,1'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/search', SearchController::class)->name('search');

    // Tenant (workspace)
    Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('/tenants/{workspace}', [TenantController::class, 'show'])->name('tenants.show');
    Route::post('/tenants/{workspace}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('/tenants/{workspace}/unsuspend', [TenantController::class, 'unsuspend'])->name('tenants.unsuspend');
    Route::put('/tenants/{workspace}/plan', [TenantController::class, 'plan'])->name('tenants.plan');
    Route::put('/tenants/{workspace}/limits', [TenantController::class, 'limits'])->name('tenants.limits');
    Route::put('/tenants/{workspace}/notes', [TenantController::class, 'notes'])->name('tenants.notes');
    Route::post('/tenants/{workspace}/members/{member}/owner', [TenantController::class, 'makeOwner'])->name('tenants.owner');
    Route::delete('/tenants/{workspace}/members/{member}', [TenantController::class, 'removeMember'])->name('tenants.member.remove');
    Route::post('/tenants/{workspace}/impersonate/{user}', [TenantController::class, 'impersonate'])->name('tenants.impersonate');
    Route::post('/tenants/{workspace}/revoke-keys', [TenantController::class, 'revokeKeys'])->name('tenants.revoke-keys');
    Route::get('/tenants/{workspace}/export', [TenantController::class, 'export'])->name('tenants.export');
    Route::delete('/tenants/{workspace}', [TenantController::class, 'destroy'])->name('tenants.destroy');

    // Pengguna & operator
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
    Route::post('/users/{user}/unsuspend', [UserController::class, 'unsuspend'])->name('users.unsuspend');
    Route::post('/users/{user}/verify-email', [UserController::class, 'verifyEmail'])->name('users.verify-email');
    Route::post('/users/{user}/reset-2fa', [UserController::class, 'reset2fa'])->name('users.reset-2fa');
    Route::post('/users/{user}/send-reset', [UserController::class, 'sendReset'])->name('users.send-reset');
    Route::post('/users/{user}/toggle-admin', [UserController::class, 'toggleAdmin'])->name('users.toggle-admin');
    Route::put('/users/{user}/role', [UserController::class, 'role'])->name('users.role');

    // Pembayaran, plan, kupon
    Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/export', [AdminPaymentController::class, 'export'])->name('payments.export');
    Route::get('/payments/{id}', [AdminPaymentController::class, 'show'])->name('payments.show');
    Route::post('/payments/{id}/mark-paid', [AdminPaymentController::class, 'markPaid'])->name('payments.mark-paid');
    Route::post('/payments/{id}/void', [AdminPaymentController::class, 'void'])->name('payments.void');
    Route::post('/payments/{id}/refund', [AdminPaymentController::class, 'refund'])->name('payments.refund');
    Route::post('/payments/{id}/resend', [AdminPaymentController::class, 'resend'])->name('payments.resend');
    Route::post('/payments/{id}/sync', [AdminPaymentController::class, 'sync'])->name('payments.sync');
    Route::get('/payment-events', [AdminPaymentController::class, 'events'])->name('payments.events');
    Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
    Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
    Route::put('/plans/{key}', [PlanController::class, 'update'])->name('plans.update');
    Route::delete('/plans/{key}', [PlanController::class, 'destroy'])->name('plans.destroy');
    Route::get('/coupons', [PlanController::class, 'coupons'])->name('coupons.index');
    Route::post('/coupons', [PlanController::class, 'couponStore'])->name('coupons.store');
    Route::put('/coupons/{coupon}', [PlanController::class, 'couponUpdate'])->name('coupons.update');
    Route::delete('/coupons/{coupon}', [PlanController::class, 'couponDestroy'])->name('coupons.destroy');

    // Operasional
    Route::get('/ops', [OpsController::class, 'index'])->name('ops.index');
    Route::get('/ops/failed-jobs', [OpsController::class, 'failed'])->name('ops.failed');
    Route::post('/ops/failed-jobs/retry-all', [OpsController::class, 'retryAll'])->name('ops.retry-all');
    Route::post('/ops/failed-jobs/flush', [OpsController::class, 'flush'])->name('ops.flush');
    Route::post('/ops/failed-jobs/{uuid}/retry', [OpsController::class, 'retry'])->name('ops.retry');
    Route::delete('/ops/failed-jobs/{uuid}', [OpsController::class, 'forget'])->name('ops.forget');
    Route::get('/ops/providers', [OpsController::class, 'providers'])->name('ops.providers');
    Route::get('/ops/webhooks', [OpsController::class, 'webhooks'])->name('ops.webhooks');
    Route::get('/ops/messages', [OpsController::class, 'messages'])->name('ops.messages');

    // Abuse & keamanan
    Route::get('/abuse', [AbuseController::class, 'index'])->name('abuse.index');
    Route::post('/abuse/blocks', [AbuseController::class, 'store'])->name('abuse.store');
    Route::delete('/abuse/blocks/{block}', [AbuseController::class, 'destroy'])->name('abuse.destroy');
    Route::get('/audit', [AuditController::class, 'index'])->name('audit');

    // Komunikasi & email
    Route::get('/announcements', [CommsController::class, 'announcements'])->name('comms.announcements');
    Route::post('/announcements', [CommsController::class, 'announcementStore'])->name('comms.announcement.store');
    Route::post('/announcements/{announcement}/toggle', [CommsController::class, 'announcementToggle'])->name('comms.announcement.toggle');
    Route::delete('/announcements/{announcement}', [CommsController::class, 'announcementDestroy'])->name('comms.announcement.destroy');
    Route::get('/broadcasts', [CommsController::class, 'broadcasts'])->name('comms.broadcasts');
    Route::post('/broadcasts', [CommsController::class, 'broadcastSend'])->name('comms.broadcast.send');
    Route::get('/emails', [EmailController::class, 'logs'])->name('emails.logs');
    Route::get('/emails/templates', [EmailController::class, 'templates'])->name('emails.templates');
    Route::put('/emails/templates/{kind}', [EmailController::class, 'templateUpdate'])->name('emails.template-update');
    Route::post('/emails/test', [EmailController::class, 'test'])->name('emails.test');
    Route::get('/emails/smtp', [EmailController::class, 'smtp'])->name('emails.smtp');
    Route::put('/emails/smtp', [EmailController::class, 'smtpUpdate'])->name('emails.smtp-update');
    Route::post('/emails/smtp/probe', [EmailController::class, 'smtpProbe'])->name('emails.smtp-probe');

    // Sistem: workers, kesiapan, backup, log, branding
    Route::get('/system/workers', [SystemController::class, 'workers'])->name('system.workers');
    Route::post('/system/workers/restart', [SystemController::class, 'restartWorkers'])->name('system.workers.restart');
    Route::post('/system/workers/drain', [SystemController::class, 'drain'])->name('system.workers.drain');
    Route::post('/system/workers/schedule', [SystemController::class, 'runSchedule'])->name('system.workers.schedule');
    Route::post('/system/tools/{tool}', [SystemController::class, 'tool'])->name('system.tool');
    Route::get('/system/readiness', [SystemController::class, 'readiness'])->name('system.readiness');
    Route::get('/system/backups', [SystemController::class, 'backups'])->name('system.backups');
    Route::post('/system/backups', [SystemController::class, 'backupRun'])->name('system.backups.run');
    Route::get('/system/backups/{name}', [SystemController::class, 'backupDownload'])->name('system.backups.download');
    Route::delete('/system/backups/{name}', [SystemController::class, 'backupDestroy'])->name('system.backups.destroy');
    Route::get('/system/logs', [SystemController::class, 'logs'])->name('system.logs');
    Route::get('/system/logs/download', [SystemController::class, 'logDownload'])->name('system.logs.download');
    Route::get('/system/branding', [SystemController::class, 'branding'])->name('system.branding');
    Route::put('/system/branding', [SystemController::class, 'brandingUpdate'])->name('system.branding.update');

    // Laporan & pengaturan
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/workspaces.csv', [ReportController::class, 'workspacesCsv'])->name('reports.workspaces-csv');
    Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings');
    Route::put('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/toggle-signup', [AdminSettingsController::class, 'toggleSignup'])->name('settings.toggle-signup');
});

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
        Route::post('/billing/coupon-preview', [BillingController::class, 'couponPreview'])->name('billing.coupon-preview');
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
