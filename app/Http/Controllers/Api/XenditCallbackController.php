<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\Billing\BillingService;
use App\Services\Payments\XenditService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Penerima callback pembayaran Xendit untuk Notifora.
 *
 * Alur produksi: Xendit -> router webhook pusat VexaHost (/api/payment/xendit/webhook) memvalidasi
 * x-callback-token, melihat prefix external_id "NTF-", lalu meneruskan ke endpoint ini dengan header
 * X-Internal-Token (INTERNAL_WEBHOOK_SECRET) + x-callback-token. Kontrak sama dengan BuildClient/WAGateway.
 *
 * Keamanan:
 *  1. Wajib salah satu token valid (internal secret ATAU callback token). Tanpa konfigurasi -> 500, bukan lolos.
 *  2. Bila XENDIT_SECRET_KEY terpasang, status & nominal DIVERIFIKASI ulang ke API Xendit sebelum plan diaktifkan.
 *  3. Nominal harus sama persis dengan payment.
 *  4. Idempoten: (provider, invoice id + status) unik; settle() sendiri idempoten lewat applied_at.
 *  5. Hanya external_id berawalan "NTF-" yang diproses.
 */
class XenditCallbackController extends Controller
{
    public function handle(Request $request, BillingService $billing, XenditService $xendit): JsonResponse
    {
        if ($request->isMethod('get')) {
            return response()->json(['status' => 'ok', 'message' => 'Notifora Xendit callback receiver is active.']);
        }

        $internal = (string) config('services.xendit.internal_secret');
        $callback = (string) config('services.xendit.webhook_token');
        if ($internal === '' && $callback === '') {
            Log::critical('xendit.callback_rejected: INTERNAL_WEBHOOK_SECRET / XENDIT_WEBHOOK_TOKEN belum dikonfigurasi');

            return response()->json(['status' => 'error', 'message' => 'Receiver not configured.'], 500);
        }

        $givenInternal = (string) $request->header('X-Internal-Token');
        $givenCallback = (string) ($request->header('x-callback-token') ?? $request->input('callback_token', ''));
        $authorized = ($internal !== '' && $givenInternal !== '' && hash_equals($internal, $givenInternal))
            || ($callback !== '' && $givenCallback !== '' && hash_equals($callback, $givenCallback));

        if (! $authorized) {
            Log::warning('xendit.callback_unauthorized', ['ip' => $request->ip()]);

            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        $payload = $request->all();
        $externalId = (string) ($payload['external_id'] ?? '');
        $invoiceId = (string) ($payload['id'] ?? '');
        $status = strtoupper((string) ($payload['status'] ?? ''));

        if (in_array(strtolower($status), ['test', 'ping', ''], true)) {
            return response()->json(['status' => 'success', 'message' => 'Ping acknowledged.']);
        }
        if (! str_starts_with($externalId, config('notifora.billing.external_prefix'))) {
            return response()->json(['status' => 'ignored', 'message' => 'Not a Notifora payment.']);
        }

        $payment = Payment::where('external_id', $externalId)->first()
            ?? ($invoiceId ? Payment::where('provider_invoice_id', $invoiceId)->first() : null);

        $eventId = ($invoiceId ?: $externalId).'-'.$status;
        try {
            $event = PaymentEvent::create([
                'provider' => 'xendit', 'event_id' => $eventId, 'event_type' => strtolower($status),
                'payment_id' => $payment?->id, 'ip' => $request->ip(), 'payload' => $payload,
            ]);
        } catch (UniqueConstraintViolationException) {
            $event = PaymentEvent::where('provider', 'xendit')->where('event_id', $eventId)->first();
            if ($event && $event->processing_status === 'processed') {
                return response()->json(['status' => 'already_processed', 'message' => 'Duplicate callback.']);
            }
        }

        if (! $payment) {
            $event->update(['processing_status' => 'ignored', 'processing_error' => 'payment not found', 'processed_at' => now()]);

            return response()->json(['status' => 'not_found', 'message' => 'Payment not found.']);
        }

        $done = fn (string $s, ?string $err = null) => $event->update(['processing_status' => $s, 'processing_error' => $err, 'processed_at' => now()]);

        try {
            if (in_array($status, ['PAID', 'SETTLED'], true)) {
                $paid = (float) ($payload['paid_amount'] ?? $payload['amount'] ?? 0);

                // Verifikasi ulang ke Xendit bila kunci tersedia (pertahanan berlapis terhadap token bocor).
                if ($xendit->isConfigured() && $payment->provider_invoice_id) {
                    $inv = $xendit->getInvoice($payment->provider_invoice_id);
                    if (! $inv) {
                        $done('failed', 'verification unavailable');

                        return response()->json(['status' => 'error', 'message' => 'Cannot verify with Xendit, retry later.'], 503);
                    }
                    if (! in_array(strtoupper((string) ($inv['status'] ?? '')), ['PAID', 'SETTLED'], true)) {
                        $done('failed', 'xendit status mismatch');
                        Log::critical('xendit.callback_status_mismatch', ['payment' => $payment->number]);

                        return response()->json(['status' => 'error', 'message' => 'Status mismatch.'], 422);
                    }
                    $paid = (float) ($inv['paid_amount'] ?? $inv['amount'] ?? $paid);
                }

                if (abs($paid - $payment->amount) > 0.01) {
                    $done('failed', "amount mismatch: {$paid} != {$payment->amount}");
                    Log::critical('xendit.callback_amount_mismatch', ['payment' => $payment->number, 'paid' => $paid, 'expected' => $payment->amount]);

                    return response()->json(['status' => 'error', 'message' => 'Amount mismatch.'], 422);
                }

                $billing->settle($payment, $payload['payment_channel'] ?? $payload['payment_method'] ?? null, $payload);
                $done('processed');

                return response()->json(['status' => 'success', 'message' => 'Payment settled.']);
            }

            if ($status === 'EXPIRED') {
                $billing->markUnpaid($payment, Payment::EXPIRED, 'expired');
                $done('processed');

                return response()->json(['status' => 'success', 'message' => 'Payment expired recorded.']);
            }

            if ($status === 'FAILED') {
                $billing->markUnpaid($payment, Payment::FAILED, 'failed');
                $done('processed');

                return response()->json(['status' => 'success', 'message' => 'Payment failure recorded.']);
            }

            $done('ignored', "status {$status} ignored");

            return response()->json(['status' => 'ignored', 'message' => "Status '{$status}' ignored."]);
        } catch (\Throwable $e) {
            Log::error('xendit.callback_error', ['payment' => $payment->number, 'error' => $e->getMessage()]);
            $done('failed', mb_substr($e->getMessage(), 0, 250));

            return response()->json(['status' => 'error', 'message' => 'Processing error.'], 500);
        }
    }
}
