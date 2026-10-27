# Panel Admin Notifora (`/admin`)

Panel untuk **operator platform** (bukan admin workspace pelanggan). Non-operator yang membuka `/admin` mendapat 404.

## Akses

```bash
# operator pertama (akun sudah ada)
php artisan notifora:make-admin you@example.com
# akun belum ada: dibuat dengan password acak (tampil sekali)
php artisan notifora:make-admin you@example.com --create --name="Nama Anda"
# cabut akses
php artisan notifora:make-admin you@example.com --revoke
```

Development: `php artisan db:seed` membuat `admin@notifora.test` (password dev di output seeder).

Setelah login, aktifkan 2FA di `/security/2fa` (aplikasi authenticator + 8 kode pemulihan). Selama `ADMIN_2FA_REQUIRED=true`
(default; wajib di staging/production) panel tidak bisa dibuka tanpa 2FA. Set `false` hanya di `.env` development.

## Env

| Variabel | Fungsi |
|---|---|
| `ADMIN_2FA_REQUIRED` | Wajibkan 2FA operator (`true` staging/production) |
| `SOCKET_HEALTH_URL` | Endpoint `/health` socket engine untuk halaman Kesehatan sistem |

## Modul

| Menu | Isi |
|---|---|
| Dashboard / Laporan | KPI, MRR/ARR, grafik 30 hari, alert, pendapatan 12 bulan, tenant teratas |
| Tenants | filter, detail, suspend, grant plan, batas kustom, impersonasi, transfer owner, ekspor, hapus |
| Pengguna | suspend, reset 2FA/password, verifikasi email, jadikan operator |
| Pembayaran | mark-paid, void, refund, resend, sync Xendit, CSV, event webhook |
| Plan & Kupon | editor plan di DB (langsung berlaku), kupon |
| Operasional | health, job gagal, provider, webhook, log pesan global |
| Abuse | blokir IP / domain email, pantauan rasio gagal |
| Komunikasi | pengumuman, broadcast email |
| Email | log, template, uji SMTP |
| Audit | audit operator + audit tenant |
| Pengaturan | maintenance, signup, checkout |

## Jaminan perilaku

- Workspace ditangguhkan: API key → `403 workspace_suspended`, Console → halaman penangguhan, job workflow dibatalkan.
- Setiap aksi operator tercatat di `platform_audit_logs`; aksi atas tenant juga muncul di Audit Log tenant sebagai "Notifora Support".
- Impersonasi menampilkan banner di Console dan dicatat saat mulai/berhenti. Sesama operator tidak dapat diimpersonasi.
- Perubahan plan di editor langsung memengaruhi checkout, landing, docs, dan batas workspace (tanpa deploy). Plan bawaan tidak dapat dihapus, hanya dinonaktifkan.
- Email keamanan inti (reset password, undangan, API key baru, dll.) tidak dapat dimatikan dari template.

## Pengujian

`tests/Feature/AdminPanelTest.php` (36 tes): akses & 2FA, TOTP vektor RFC 6238, smoke test semua halaman, suspend, impersonasi,
plan/limit, pembayaran, kupon, editor plan, blokir, maintenance, flag, email, broadcast, job gagal, health, pencarian.
