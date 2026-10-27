<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Klien Xendit Invoice. Invoice dibuat langsung oleh Notifora (secret key sendiri);
 * callback status pembayaran datang lewat router webhook pusat VexaHost (prefix external_id "NTF-").
 */
class XenditService
{
    /** Channel yang bisa dipilih di checkout => kode payment_methods Xendit. */
    public const METHODS = [
        'all' => ['label' => 'Semua metode (QRIS, VA, Indomaret)', 'codes' => ['QRIS', 'INDOMARET', 'MANDIRI', 'BNI', 'BRI', 'CIMB', 'PERMATA']],
        'qris' => ['label' => 'QRIS (semua e-wallet & m-banking)', 'codes' => ['QRIS']],
        'mandiri_va' => ['label' => 'Virtual Account Mandiri', 'codes' => ['MANDIRI']],
        'bni_va' => ['label' => 'Virtual Account BNI', 'codes' => ['BNI']],
        'bri_va' => ['label' => 'Virtual Account BRI', 'codes' => ['BRI']],
        'cimb_va' => ['label' => 'Virtual Account CIMB Niaga', 'codes' => ['CIMB']],
        'permata_va' => ['label' => 'Virtual Account Permata / bank lain', 'codes' => ['PERMATA']],
        'indomaret' => ['label' => 'Indomaret', 'codes' => ['INDOMARET']],
    ];

    public function secretKey(): ?string
    {
        return config('services.xendit.secret_key') ?: null;
    }

    public function isConfigured(): bool
    {
        return (bool) $this->secretKey();
    }

    /** Mode uji lokal tanpa Xendit: invoice dibuat internal dan dilunasi lewat tombol simulasi. Tidak pernah aktif di staging/production. */
    public function isLocalSandbox(): bool
    {
        return ! $this->isConfigured() && app()->environment(['local', 'development']);
    }

    private function base(): string
    {
        return rtrim(config('services.xendit.base_url'), '/');
    }

    /**
     * Buat invoice Xendit untuk sebuah Payment dan simpan id/url-nya.
     *
     * @return array{success:bool, error?:string}
     */
    public function createInvoice(Payment $p, User $user, Workspace $ws, string $method = 'all'): array
    {
        if ($this->isLocalSandbox()) {
            $p->update([
                'provider_invoice_id' => 'sandbox_'.$p->id,
                'invoice_url' => route('console.billing.payments.show', $p->id),
                'payment_method' => $method,
            ]);

            return ['success' => true];
        }
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Pembayaran belum dikonfigurasi (XENDIT_SECRET_KEY kosong).'];
        }
        $codes = (self::METHODS[$method] ?? self::METHODS['all'])['codes'];
        $ttl = (int) config('notifora.billing.invoice_ttl');
        $back = route('console.billing.payments.show', $p->id);

        $payload = [
            'external_id' => $p->external_id,
            'amount' => $p->amount,
            'currency' => 'IDR',
            'description' => "Notifora {$p->planLabel()} — {$p->months} bulan ({$ws->name})",
            'invoice_duration' => $ttl,
            'payer_email' => $user->email,
            'payment_methods' => $codes,
            'success_redirect_url' => $back,
            'failure_redirect_url' => $back,
            'customer' => ['given_names' => $user->name, 'email' => $user->email],
            'items' => [[
                'name' => "Notifora {$p->planLabel()} ({$p->months} bulan)",
                'quantity' => 1,
                'price' => $p->amount,
                'category' => 'Software Subscription',
            ]],
            'metadata' => ['app' => 'notifora', 'payment_id' => $p->id, 'workspace_id' => $ws->id, 'number' => $p->number],
        ];

        try {
            $res = Http::withBasicAuth($this->secretKey(), '')->acceptJson()->timeout(15)
                ->post($this->base().'/v2/invoices', $payload);
        } catch (Throwable $e) {
            Log::error('xendit.invoice_exception', ['payment' => $p->number, 'error' => $e->getMessage()]);

            return ['success' => false, 'error' => 'Tidak dapat terhubung ke Xendit. Coba lagi sebentar lagi.'];
        }

        if (! $res->successful()) {
            Log::error('xendit.invoice_failed', ['payment' => $p->number, 'status' => $res->status(), 'body' => $res->json()]);

            return ['success' => false, 'error' => $res->json('message') ?: 'Xendit menolak pembuatan invoice.'];
        }

        $p->update([
            'provider_invoice_id' => $res->json('id'),
            'invoice_url' => $res->json('invoice_url'),
            'payment_method' => $method,
            'raw' => $res->json(),
        ]);

        return ['success' => true];
    }

    /** Ambil status invoice langsung dari Xendit (untuk verifikasi webhook & sinkronisasi). */
    public function getInvoice(string $invoiceId): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }
        try {
            $res = Http::withBasicAuth($this->secretKey(), '')->acceptJson()->timeout(10)
                ->get($this->base().'/v2/invoices/'.$invoiceId);

            return $res->successful() ? $res->json() : null;
        } catch (Throwable $e) {
            Log::error('xendit.get_invoice_exception', ['invoice' => $invoiceId, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
