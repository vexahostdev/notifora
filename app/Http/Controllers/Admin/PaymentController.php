<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\AdminAudit;
use App\Services\Billing\BillingService;
use App\Services\SystemNotifier;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(private BillingService $billing, private SystemNotifier $notifier) {}

    private function filtered(Request $request)
    {
        $q = Payment::query()->with('workspace:id,name,slug', 'user:id,name,email');
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhere('external_id', 'like', "%$s%")
                ->orWhere('provider_invoice_id', $s)
                ->orWhereHas('workspace', fn ($x) => $x->where('name', 'like', "%$s%"))
                ->orWhereHas('user', fn ($x) => $x->where('email', 'like', "%$s%")));
        }
        foreach (['status', 'plan'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        if ($from = $request->query('from')) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('created_at', '<=', $to);
        }

        return $q;
    }

    public function index(Request $request)
    {
        $base = $this->filtered($request);
        $paidTotal = (clone $base)->where('status', Payment::PAID)->sum('amount');

        return view('admin.payments.index', [
            'payments' => $base->latest()->paginate(30)->withQueryString(),
            'paidTotal' => (int) $paidTotal,
            'statuses' => [Payment::PENDING, Payment::PAID, Payment::EXPIRED, Payment::FAILED, Payment::CANCELED, Payment::REFUNDED],
        ]);
    }

    public function show(string $id)
    {
        $payment = Payment::with('workspace', 'user')->findOrFail($id);

        return view('admin.payments.show', [
            'p' => $payment,
            'events' => PaymentEvent::where('payment_id', $payment->id)->latest('id')->get(),
        ]);
    }

    /** Tandai lunas manual (mis. transfer manual / rekonsiliasi). Menerapkan plan seperti pembayaran normal. */
    public function markPaid(Request $request, string $id)
    {
        $payment = Payment::findOrFail($id);
        $data = $request->validate(['note' => ['required', 'string', 'max:255']]);
        if ($payment->isPaid() || $payment->status === Payment::REFUNDED) {
            return back()->with('error', 'Pembayaran ini sudah lunas / sudah dikembalikan.');
        }

        $payment->update(['manual_note' => $data['note']]);
        $this->billing->settle($payment, 'MANUAL');
        AdminAudit::record('payment.marked_paid', 'payment', $payment->id, $payment->workspace_id, ['number' => $payment->number, 'note' => $data['note']]);

        return back()->with('success', 'Ditandai lunas dan plan diterapkan.');
    }

    public function void(Request $request, string $id)
    {
        $payment = Payment::findOrFail($id);
        if (! $payment->isPending()) {
            return back()->with('error', 'Hanya invoice PENDING yang bisa dibatalkan.');
        }
        $note = $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;
        $payment->update(['status' => Payment::CANCELED, 'failure_reason' => 'voided_by_admin', 'manual_note' => $note]);
        AdminAudit::record('payment.voided', 'payment', $payment->id, $payment->workspace_id, ['number' => $payment->number, 'note' => $note]);

        return back()->with('success', 'Invoice dibatalkan.');
    }

    /**
     * Catat refund. Dana dikembalikan lewat Xendit/rekening secara manual di luar sistem;
     * di sini status dicatat dan (opsional) masa berlaku plan dikurangi.
     */
    public function refund(Request $request, string $id)
    {
        $payment = Payment::with('workspace')->findOrFail($id);
        $data = $request->validate(['note' => ['required', 'string', 'max:255'], 'revoke_plan' => ['nullable', 'boolean']]);
        if (! $payment->isPaid()) {
            return back()->with('error', 'Hanya pembayaran berstatus LUNAS yang dapat direfund.');
        }

        $payment->update(['status' => Payment::REFUNDED, 'refunded_at' => now(), 'refund_note' => $data['note']]);

        if ($request->boolean('revoke_plan') && $payment->workspace && $payment->workspace->plan === $payment->plan) {
            $ws = $payment->workspace;
            $until = $ws->plan_expires_at?->copy()->subMonths($payment->months);
            if (! $until || $until->isPast()) {
                $ws->update(['plan' => 'free', 'plan_expires_at' => null]);
            } else {
                $ws->update(['plan_expires_at' => $until]);
            }
        }
        AdminAudit::record('payment.refunded', 'payment', $payment->id, $payment->workspace_id, ['number' => $payment->number, 'amount' => $payment->amount, 'note' => $data['note'], 'revoke_plan' => $request->boolean('revoke_plan')]);

        return back()->with('success', 'Refund dicatat. Pastikan dana dikembalikan ke pelanggan.');
    }

    public function resend(string $id)
    {
        $payment = Payment::with('workspace', 'user')->findOrFail($id);
        if (! $payment->isPending() || ! $payment->workspace) {
            return back()->with('error', 'Hanya invoice PENDING yang dapat dikirim ulang.');
        }
        $this->notifier->invoiceCreated($payment->workspace, $payment, $payment->user);
        AdminAudit::record('payment.invoice_resent', 'payment', $payment->id, $payment->workspace_id);

        return back()->with('success', 'Email invoice dikirim ulang.');
    }

    public function sync(string $id)
    {
        $payment = $this->billing->sync(Payment::findOrFail($id));
        AdminAudit::record('payment.synced', 'payment', $payment->id, $payment->workspace_id, ['status' => $payment->status]);

        return back()->with('success', "Disinkronkan dengan Xendit. Status: {$payment->status}.");
    }

    public function export(Request $request): StreamedResponse
    {
        $q = $this->filtered($request)->latest();
        AdminAudit::record('payment.exported', null, null, null, $request->query());

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['number', 'workspace', 'user_email', 'plan', 'months', 'subtotal', 'discount', 'coupon', 'amount', 'status', 'channel', 'created_at', 'paid_at']);
            $q->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $p) {
                    fputcsv($out, [$p->number, $p->workspace?->name, $p->user?->email, $p->plan, $p->months, $p->subtotal, $p->discount, $p->coupon_code, $p->amount, $p->status, $p->payment_channel, $p->created_at, $p->paid_at]);
                }
            });
            fclose($out);
        }, 'notifora-payments-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function events(Request $request)
    {
        $q = PaymentEvent::query()->latest('id');
        if ($s = $request->query('status')) {
            $q->where('processing_status', $s);
        }
        if ($e = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('event_id', 'like', "%$e%")->orWhere('payment_id', $e));
        }

        return view('admin.payments.events', ['events' => $q->paginate(30)->withQueryString()]);
    }
}
