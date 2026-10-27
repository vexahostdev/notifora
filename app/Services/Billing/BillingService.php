<?php

namespace App\Services\Billing;

use App\Exceptions\ApiException;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Payments\XenditService;
use App\Services\SystemNotifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingService
{
    public function __construct(private XenditService $xendit, private SystemNotifier $notifier) {}

    /** @return array<string,array> plan berbayar */
    public function plans(): array
    {
        return config('notifora.plans');
    }

    public function periods(): array
    {
        return config('notifora.billing.periods');
    }

    public function quote(string $plan, int $months): int
    {
        if (! isset($this->plans()[$plan])) {
            throw new ApiException('validation_failed', 'Plan tidak dikenal.', 422);
        }
        if (! in_array($months, $this->periods(), true)) {
            throw new ApiException('validation_failed', 'Durasi tidak valid.', 422);
        }

        return (int) ($this->plans()[$plan]['price'] * $months);
    }

    /** Plan aktif berbayar dan belum lewat masa berlaku? */
    public function hasActivePaidPlan(Workspace $ws): bool
    {
        return $ws->effectivePlanKey() !== 'free' && (! $ws->plan_expires_at || $ws->plan_expires_at->isFuture());
    }

    /** Selama plan berbayar masih aktif, hanya boleh memperpanjang plan yang sama atau naik ke plan lebih tinggi. */
    public function assertCanPurchase(Workspace $ws, string $plan): void
    {
        if (! $this->hasActivePaidPlan($ws)) {
            return;
        }
        $current = $this->plans()[$ws->plan]['price'] ?? 0;
        if (($this->plans()[$plan]['price'] ?? 0) < $current) {
            throw new ApiException('validation_failed', 'Plan lebih rendah dari plan aktif Anda tidak dapat dibeli sebelum masa berlaku berakhir.', 422);
        }
    }

    /** Buat Payment + invoice Xendit. Payment tetap tersimpan bila Xendit gagal (status FAILED). */
    public function createCheckout(Workspace $ws, User $user, string $plan, int $months, string $method): Payment
    {
        $amount = $this->quote($plan, $months);
        $this->assertCanPurchase($ws, $plan);

        $number = Payment::nextNumber();
        $payment = Payment::create([
            'number' => $number,
            'workspace_id' => $ws->id,
            'user_id' => $user->id,
            'plan' => $plan,
            'months' => $months,
            'amount' => $amount,
            'status' => Payment::PENDING,
            'external_id' => $number.'-'.Str::upper(Str::random(4)),  // prefix "NTF-" = routing di VexaHost
            'expires_at' => now()->addSeconds((int) config('notifora.billing.invoice_ttl')),
        ]);

        $res = $this->xendit->createInvoice($payment, $user, $ws, $method);
        if (! $res['success']) {
            $payment->update(['status' => Payment::FAILED, 'failure_reason' => mb_substr($res['error'] ?? 'invoice_failed', 0, 120)]);
            throw new ApiException('payment_unavailable', $res['error'] ?? 'Gagal membuat invoice.', 502);
        }

        AuditLog::record($ws->id, 'billing.checkout_created', 'payment', $payment->id, ['number' => $number, 'plan' => $plan, 'months' => $months, 'amount' => $amount]);
        $this->notifier->invoiceCreated($ws, $payment->fresh(), $user);

        return $payment->fresh();
    }

    /**
     * Tandai lunas dan terapkan plan. Idempoten: pemanggilan ulang (webhook ganda / sinkronisasi) tidak
     * menambah masa berlaku dua kali.
     */
    public function settle(Payment $payment, ?string $channel = null, ?array $raw = null): bool
    {
        $applied = false;

        DB::transaction(function () use ($payment, $channel, $raw, &$applied) {
            $p = Payment::lockForUpdate()->find($payment->id);
            if ($p->applied_at) {
                return;
            }
            $p->update([
                'status' => Payment::PAID,
                'paid_at' => $p->paid_at ?? now(),
                'payment_channel' => $channel ?: $p->payment_channel,
                'applied_at' => now(),
                'failure_reason' => null,
                'raw' => $raw ?? $p->raw,
            ]);
            $this->applyPlan($p);
            $applied = true;
            AuditLog::record($p->workspace_id, 'billing.payment_paid', 'payment', $p->id, ['number' => $p->number, 'plan' => $p->plan, 'months' => $p->months]);
        });

        if ($applied) {
            $payment->refresh();
            $this->notifier->paymentSuccess($payment->workspace, $payment);
        }

        return $applied;
    }

    /** Perpanjang bila plan sama & masih aktif; selain itu mulai dari sekarang (upgrade / mulai baru). */
    private function applyPlan(Payment $p): void
    {
        $ws = Workspace::lockForUpdate()->find($p->workspace_id);
        $sameActive = $ws->plan === $p->plan && $ws->plan_expires_at && $ws->plan_expires_at->isFuture();
        $base = $sameActive ? $ws->plan_expires_at->copy() : now();

        $ws->update([
            'plan' => $p->plan,
            'plan_started_at' => $sameActive ? $ws->plan_started_at : now(),
            'plan_expires_at' => $base->addMonths($p->months),
        ]);
    }

    /** EXPIRED / FAILED dari Xendit. Tidak mengubah payment yang sudah lunas. */
    public function markUnpaid(Payment $payment, string $status, string $reason): void
    {
        if ($payment->status !== Payment::PENDING) {
            return;
        }
        $payment->update(['status' => $status, 'failure_reason' => $reason]);
        $this->notifier->paymentFailed($payment->workspace, $payment->fresh());
    }

    public function cancel(Payment $payment): void
    {
        if ($payment->status === Payment::PENDING) {
            $payment->update(['status' => Payment::CANCELED, 'failure_reason' => 'canceled_by_user']);
            AuditLog::record($payment->workspace_id, 'billing.payment_canceled', 'payment', $payment->id, ['number' => $payment->number]);
        }
    }

    /** Sinkronkan payment PENDING dengan Xendit (dipakai saat pengguna kembali dari halaman pembayaran). */
    public function sync(Payment $payment): Payment
    {
        if (! $payment->isPending() || ! $payment->provider_invoice_id) {
            return $payment;
        }
        $inv = $this->xendit->getInvoice($payment->provider_invoice_id);
        if (! $inv) {
            return $payment;
        }
        $status = strtoupper((string) ($inv['status'] ?? ''));

        if (in_array($status, ['PAID', 'SETTLED'], true) && (int) round((float) ($inv['paid_amount'] ?? $inv['amount'] ?? 0)) === $payment->amount) {
            $this->settle($payment, $inv['payment_channel'] ?? $inv['payment_method'] ?? null);
        } elseif ($status === 'EXPIRED') {
            $this->markUnpaid($payment, Payment::EXPIRED, 'expired');
        }

        return $payment->fresh();
    }

    /**
     * Pekerjaan berkala (per jam): kedaluwarsakan invoice lama, kirim pengingat, mulai/akhiri masa tenggang.
     *
     * @return array{expired:int, reminders:int, graceStarted:int, downgraded:int}
     */
    public function sweep(): array
    {
        $out = ['expired' => 0, 'reminders' => 0, 'graceStarted' => 0, 'downgraded' => 0];

        Payment::where('status', Payment::PENDING)->where('expires_at', '<', now()->subMinutes(5))->get()
            ->each(function (Payment $p) use (&$out) {
                $p = $this->sync($p);
                if ($p->isPending()) {
                    $this->markUnpaid($p, Payment::EXPIRED, 'expired');
                }
                $out['expired']++;
            });

        Workspace::whereNotNull('plan_expires_at')->whereNotIn('plan', ['free'])->get()->each(function (Workspace $ws) use (&$out) {
            $key = $ws->plan_expires_at->timestamp;

            if ($ws->plan_expires_at->isFuture()) {
                $days = (int) ceil(now()->diffInHours($ws->plan_expires_at, false) / 24);
                if (in_array($days, config('notifora.billing.reminder_days'), true)
                    && Cache::add("sub-remind:{$ws->id}:$key:$days", 1, now()->addDays(10))) {
                    $this->notifier->subscriptionExpiring($ws, $days);
                    $out['reminders']++;
                }

                return;
            }

            if (! $ws->graceEndsAt()->isPast()) {
                if (Cache::add("sub-expired:{$ws->id}:$key", 1, now()->addDays(15))) {
                    $this->notifier->subscriptionExpired($ws);
                    $out['graceStarted']++;
                }

                return;
            }

            // Masa tenggang habis -> plan free
            $ws->update(['plan' => 'free', 'plan_expires_at' => null]);
            AuditLog::record($ws->id, 'billing.downgraded', 'workspace', $ws->id, ['reason' => 'expired']);
            $this->notifier->subscriptionDowngraded($ws->fresh());
            $out['downgraded']++;
        });

        return $out;
    }
}
