# Routing webhook Xendit: VexaHost → Notifora

Notifora **tidak** mendaftarkan webhook sendiri ke Xendit. Seperti WAGateway, BuildClient, dan Postivo, callback
Xendit masuk ke **router pusat VexaHost** (`/api/payment/xendit/webhook`), yang memvalidasi `x-callback-token`
lalu meneruskan berdasarkan prefix `external_id`:

| Prefix | Tujuan | Cara |
|---|---|---|
| `VH-` | diproses lokal di VexaHost | controller |
| `WAG-` | WAGateway | controller |
| `BC-` | BuildClient | controller |
| `PSV-` | Postivo | `config/xendit_forwards.php` |
| **`NTF-`** | **Notifora** | `config/xendit_forwards.php` |

Notifora membuat invoice dengan `external_id` berawalan `NTF-` (contoh `NTF-INV-2026-000001-AB12`) memakai
`XENDIT_SECRET_KEY`-nya sendiri, sehingga callback-nya otomatis diarahkan ke Notifora.

## Status penerapan di repo VexaHost (2026-10-27)

Diterapkan **tanpa menyentuh controller**, memakai mekanisme `XenditForwarder` yang dibuat pengerjaan Postivo
(aplikasi baru cukup menambah satu entri config, sehingga tidak bentrok):

- `config/xendit_forwards.php` — ditambah entri `'NTF-'` (nama Notifora, URL dari `NOTIFORA_WEBHOOK_URL`).
- `tests/Feature/NotiforaXenditForwardTest.php` — file test baru terpisah (5 test; test Postivo tetap lulus).
- **Belum di-commit/push**: file `XenditForwarder`, `xendit_forwards.php`, dan perubahan controller adalah pekerjaan Postivo yang belum
  di-commit. Entri `NTF-` ikut ter-commit saat pekerjaan itu di-commit; jangan commit sebagian tanpa koordinasi.
- `.env.example` VexaHost sengaja **tidak diubah** (menghindari bentrok); URL default produksi sudah ada di config.

Yang perlu diatur di `.env` VexaHost per environment (tidak wajib untuk production karena ada default):

```ini
NOTIFORA_WEBHOOK_URL=https://notifora.my.id/api/payment/xendit/callback
# Staging VexaHost: https://staging.notifora.my.id/api/payment/xendit/callback
```

## Rahasia bersama

| Variabel | Di VexaHost | Di Notifora |
|---|---|---|
| `XENDIT_WEBHOOK_TOKEN` | token callback dari dashboard Xendit | sama (opsional, jalur langsung) |
| `INTERNAL_WEBHOOK_SECRET` | `services.xendit.internal_secret` | **harus sama persis** |
| `XENDIT_SECRET_KEY` | kunci API Xendit | kunci API yang sama (membuat & memverifikasi invoice) |

### Temuan keamanan di VexaHost (disarankan diperbaiki)
`config/services.php` VexaHost memberi **nilai default hardcoded** untuk rahasia internal
(`env('INTERNAL_WEBHOOK_SECRET', 'vexahost_internal_xnd_token_38c92a')`). Jika `.env` production tidak mengisinya,
siapa pun yang membaca kode dapat memalsukan callback internal ke semua aplikasi tujuan. Notifora sengaja **tidak memakai default**
(menolak dengan HTTP 500 bila kosong). Isi `INTERNAL_WEBHOOK_SECRET` dengan nilai acak di semua aplikasi terkait
(`php -r "echo bin2hex(random_bytes(32));"`) dan hapus default dari kode. Ini tidak diubah karena menyentuh semua aplikasi dan berisiko memutus produksi.

## Verifikasi

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
