<?php

namespace App\Http\Controllers\Console;

use App\Enums\MemberRole;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Billing\BillingService;
use App\Services\Payments\XenditService;
use App\Services\PlatformSettings;
use App\Services\UsageMeter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function __construct(private BillingService $billing, private XenditService $xendit) {}

    public function index(UsageMeter $usage)
    {
        $ws = app('current.workspace');
        $env = app('current.environment');
        $prod = $ws->environments()->where('type', 'PRODUCTION')->first();

        return view('console.billing.index', [
            'ws' => $ws,
            'plans' => $this->billing->plans(),
            'effective' => $ws->effectivePlanKey(),
            'payments' => Payment::where('workspace_id', $ws->id)->latest()->limit(25)->get(),
            'configured' => $this->xendit->isConfigured(),
            'usedProd' => $prod ? $usage->monthlyEvents($prod) : 0,
            'limitProd' => $ws->planConfig()['events'],
            'canBuy' => app('current.role')->atLeast(MemberRole::OWNER),
            'active' => $this->billing->hasActivePaidPlan($ws),
        ]);
    }

    public function checkout(Request $request, string $plan)
    {
        $ws = app('current.workspace');
        abort_unless(isset($this->billing->plans()[$plan]), 404);

        $blocked = null;
        try {
            $this->billing->assertCanPurchase($ws, $plan);
        } catch (ApiException $e) {
            $blocked = $e->getMessage();
        }

        if (! $blocked && ! PlatformSettings::bool('checkout_enabled')) {
            $blocked = 'Checkout sedang dinonaktifkan sementara. Coba lagi nanti.';
        }

        $months = (int) $request->query('months', 1);

        return view('console.billing.checkout', [
            'ws' => $ws,
            'planKey' => $plan,
            'plan' => $this->billing->plans()[$plan],
            'periods' => $this->billing->periods(),
            'months' => in_array($months, $this->billing->periods(), true) ? $months : 1,
            'methods' => XenditService::METHODS,
            'configured' => $this->xendit->isConfigured() || $this->xendit->isLocalSandbox(),
            'blocked' => $blocked,
            // Tanggal dasar perhitungan masa berlaku (perpanjangan plan sama mulai dari tanggal berakhir)
            'baseTs' => ($ws->plan === $plan && $ws->plan_expires_at?->isFuture()) ? $ws->plan_expires_at->timestamp : now()->timestamp,
            'renewing' => $ws->plan === $plan && $ws->plan_expires_at?->isFuture(),
        ]);
    }

    /** Pratinjau harga dengan kupon (dipanggil halaman checkout). */
    public function couponPreview(Request $request)
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(array_keys($this->billing->plans()))],
            'months' => ['required', 'integer', Rule::in($this->billing->periods())],
            'coupon' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            return response()->json($this->billing->preview(app('current.workspace'), $data['plan'], (int) $data['months'], $data['coupon'] ?? null));
        } catch (ApiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request)
    {
        if (! PlatformSettings::bool('checkout_enabled')) {
            return back()->withInput()->withErrors(['checkout' => 'Checkout sedang dinonaktifkan sementara. Coba lagi nanti atau hubungi support.']);
        }

        $data = $request->validate([
            'plan' => ['required', Rule::in(array_keys($this->billing->plans()))],
            'months' => ['required', 'integer', Rule::in($this->billing->periods())],
            'method' => ['required', Rule::in(array_keys(XenditService::METHODS))],
            'coupon' => ['nullable', 'string', 'max:40'],
            'terms' => ['accepted'],
        ], ['terms.accepted' => 'Anda harus menyetujui syarat pembayaran.']);

        try {
            $payment = $this->billing->createCheckout(app('current.workspace'), $request->user(), $data['plan'], (int) $data['months'], $data['method'], $data['coupon'] ?? null);
        } catch (ApiException $e) {
            return back()->withInput()->withErrors(['checkout' => $e->getMessage()]);
        }

        // Kupon 100%: sudah lunas tanpa invoice Xendit
        if (! $payment->invoice_url) {
            return redirect()->route('console.billing.payments.show', $payment->id)->with('success', 'Plan aktif. Tidak ada pembayaran yang diperlukan.');
        }

        return redirect()->away($payment->invoice_url);
    }

    public function show(string $id)
    {
        $payment = $this->find($id);
        $payment = $this->billing->sync($payment); // kembali dari Xendit: pastikan status terbaru

        return view('console.billing.payment', [
            'payment' => $payment,
            'ws' => app('current.workspace')->fresh(),
            'canSimulate' => $this->canSimulate($payment),
            'canBuy' => app('current.role')->atLeast(MemberRole::OWNER),
        ]);
    }

    public function status(string $id)
    {
        $payment = $this->billing->sync($this->find($id));

        return response()->json(['status' => $payment->status, 'paid' => $payment->isPaid(), 'final' => ! $payment->isPending()]);
    }

    public function cancel(string $id)
    {
        $this->billing->cancel($this->find($id));

        return redirect()->route('console.billing.index')->with('success', 'Invoice dibatalkan.');
    }

    /** Hanya development/staging-lokal: selesaikan pembayaran tanpa Xendit untuk menguji alur end-to-end. */
    public function simulate(string $id)
    {
        $payment = $this->find($id);
        abort_unless($this->canSimulate($payment), 404);
        $this->billing->settle($payment, 'SIMULATED');

        return redirect()->route('console.billing.payments.show', $payment->id)->with('success', 'Pembayaran disimulasikan (hanya development).');
    }

    private function canSimulate(Payment $p): bool
    {
        return $p->isPending() && app()->environment(['local', 'development', 'testing']);
    }

    private function find(string $id): Payment
    {
        return Payment::where('workspace_id', app('current.workspace')->id)->findOrFail($id);
    }
}
