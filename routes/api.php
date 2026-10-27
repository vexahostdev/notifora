<?php

use App\Http\Controllers\Api\V1\EventTriggerController;
use App\Http\Controllers\Api\V1\InboxController;
use App\Http\Controllers\Api\V1\InboxTokenController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\ProviderWebhookController;
use App\Http\Controllers\Api\V1\SubscriberController;
use App\Http\Controllers\Api\V1\SubscriberPreferenceController;
use App\Http\Controllers\Api\V1\WorkflowReadController;
use App\Http\Controllers\Internal\InternalInboxController;
use Illuminate\Support\Facades\Route;

// Prefix "v1" ditentukan di bootstrap/app.php.

// Webhook status delivery dari provider (autentikasi lewat token unik di URL)
Route::post('hooks/{provider}/{token}', ProviderWebhookController::class)->middleware('throttle:120,1');

// Dipanggil socket-engine
Route::prefix('internal/inbox')->middleware('internal')->group(function () {
    Route::get('unread', [InternalInboxController::class, 'unread']);
    Route::post('read', [InternalInboxController::class, 'read']);
});

Route::middleware(['api.key', 'api.rate'])->group(function () {
    Route::post('events/trigger', [EventTriggerController::class, 'store'])->middleware('api.idem');
    Route::post('events/bulk', [EventTriggerController::class, 'bulk']);
    Route::get('events/{id}', [EventTriggerController::class, 'show']);
    Route::delete('events/{id}', [EventTriggerController::class, 'destroy']);

    Route::put('subscribers/{externalId}', [SubscriberController::class, 'upsert']);
    Route::get('subscribers/{externalId}', [SubscriberController::class, 'show']);
    Route::delete('subscribers/{externalId}', [SubscriberController::class, 'destroy']);

    Route::get('subscribers/{externalId}/preferences', [SubscriberPreferenceController::class, 'show']);
    Route::put('subscribers/{externalId}/preferences', [SubscriberPreferenceController::class, 'update']);

    Route::post('subscribers/{externalId}/inbox-token', [InboxTokenController::class, 'store']);
    Route::get('subscribers/{externalId}/inbox', [InboxController::class, 'index']);
    Route::post('subscribers/{externalId}/inbox/read', [InboxController::class, 'read']);

    Route::get('messages', [MessageController::class, 'index']);
    Route::get('messages/{id}', [MessageController::class, 'show']);
    Route::get('workflows', [WorkflowReadController::class, 'index']);
});
