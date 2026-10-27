# Routing webhook Xendit: VexaHost → Notifora

Notifora **tidak** mendaftarkan webhook sendiri ke Xendit. Seperti WAGateway dan BuildClient, callback
Xendit masuk ke **router pusat VexaHost** (`/api/payment/xendit/webhook`), yang memvalidasi `x-callback-token`
lalu meneruskan berdasarkan prefix `external_id`:

| Prefix | Tujuan |
|---|---|
| `VH-` | diproses lokal di VexaHost |
| `WAG-` | WAGateway |
| `BC-` | BuildClient |
| **`NTF-`** | **Notifora** (perubahan di bawah) |

Notifora membuat invoice dengan `external_id` berawalan `NTF-` (contoh `NTF-INV-2026-000001-AB12`) memakai
`XENDIT_SECRET_KEY`-nya sendiri, sehingga callback-nya otomatis diarahkan ke Notifora.

> Perubahan ini ada di **repositori VexaHost** (bukan repo ini) dan belum diterapkan. Terapkan lalu deploy VexaHost.

## 1. `config/services.php` (VexaHost) — di dalam array `'xendit'`

```php
'notifora_webhook_url' => env('NOTIFORA_WEBHOOK_URL', 'https://notifora.my.id/api/payment/xendit/callback'),
```

`.env` VexaHost:

```ini
NOTIFORA_WEBHOOK_URL=https://notifora.my.id/api/payment/xendit/callback
# Staging: https://staging.notifora.my.id/api/payment/xendit/callback
```

## 2. `app/Http/Controllers/Api/PaymentWebhookController.php` — method `handleXendit()`

Tambahkan blok ini **sebelum** `ROUTE 3: PROSES LOKAL VEXAHOST`, setelah blok `BC-`:

```php
// ==========================================
// ROUTE: FORWARD KE NOTIFORA (Prefix NTF-)
// ==========================================
if (str_starts_with($externalId, 'NTF-')) {
    $ntfUrl = config('services.xendit.notifora_webhook_url');
    try {
        $forwardRes = \Illuminate\Support\Facades\Http::timeout(12)
            ->withHeaders([
                'X-Internal-Token' => config('services.xendit.internal_secret'),
                'x-callback-token' => $callbackToken,
            ])
            ->post($ntfUrl, $payload);

        Log::info('Xendit webhook forwarded to Notifora', [
            'external_id' => $externalId,
            'status_code' => $forwardRes->status(),
        ]);

        // Teruskan kegagalan server (5xx) sebagai 502 agar Xendit mengirim ulang callback pembayaran.
        return response()->json([
            'success' => $forwardRes->successful(),
            'message' => 'Notification forwarded to Notifora.',
            'target_response_code' => $forwardRes->status(),
        ], $forwardRes->serverError() ? 502 : 200);
    } catch (\Throwable $e) {
        Log::error('Xendit webhook forward to Notifora failed', ['external_id' => $externalId, 'error' => $e->getMessage()]);

        return response()->json(['success' => false, 'message' => 'Failed to forward to Notifora: '.$e->getMessage()], 502);
    }
}
```

## 3. Rahasia bersama

| Variabel | Di VexaHost | Di Notifora |
|---|---|---|
| `XENDIT_WEBHOOK_TOKEN` | token callback dari dashboard Xendit | sama (opsional, jalur langsung) |
| `INTERNAL_WEBHOOK_SECRET` | `services.xendit.internal_secret` | **harus sama persis** |
| `XENDIT_SECRET_KEY` | kunci API Xendit | kunci API yang sama (untuk membuat & memverifikasi invoice) |

### Temuan keamanan di VexaHost (disarankan diperbaiki)
`config/services.php` VexaHost memberi **nilai default hardcoded** untuk rahasia internal
(`env('INTERNAL_WEBHOOK_SECRET', 'vexahost_internal_xnd_token_38c92a')`). Jika `.env` production tidak mengisinya,
siapa pun yang membaca kode dapat memalsukan callback internal ke WAGateway/BuildClient/Notifora.
Notifora sengaja **tidak memakai default** (menolak dengan HTTP 500 bila kosong). Isi
`INTERNAL_WEBHOOK_SECRET` dengan nilai acak (`php -r "echo bin2hex(random_bytes(32));"`) di semua aplikasi yang
terlibat, dan hapus nilai default dari kode.

## 4. Verifikasi

```bash
# Health check endpoint penerima Notifora
curl https://notifora.my.id/api/payment/xendit/callback
# → {"status":"ok","message":"Notifora Xendit callback receiver is active."}

# Dari server VexaHost, simulasi callback internal (ganti nilai):
curl -X POST https://notifora.my.id/api/payment/xendit/callback \
  -H "X-Internal-Token: $INTERNAL_WEBHOOK_SECRET" -H "Content-Type: application/json" \
  -d '{"status":"PING"}'
# → {"status":"success","message":"Ping acknowledged."}
```

Callback `PAID` selalu dikonfirmasi ulang ke API Xendit sebelum plan diaktifkan, dan nominalnya harus sama persis dengan invoice.
