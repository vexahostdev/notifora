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

## Menu sistem & operasional (tambahan)

| Menu | Rute | Fungsi |
|---|---|---|
| Workers & scheduler | `/admin/system/workers` | Status supervisor, denyut worker per antrean, scheduler, antrean menunggu; tombol restart worker, proses antrean sekarang, jalankan scheduler, bersihkan cache, optimize |
| Kesiapan production | `/admin/system/readiness` | ±26 pemeriksaan (debug, HTTPS, cookie, 2FA, DB, queue, ekstensi PHP, worker, scheduler, backup, SMTP, Xendit) dengan saran perbaikan; tanpa menampilkan rahasia |
| Backup database | `/admin/system/backups` | Cadangkan sekarang, unduh, hapus; jadwal harian 03:15 (`notifora:backup`), simpan N terbaru |
| Log aplikasi | `/admin/system/logs` | Penampil `storage/logs` (filter level & teks, unduh) |
| Branding & kontak | `/admin/system/branding` | Nama, tagline, logo, favicon, warna aksen, footer (`{year}`), email/WA dukungan, sosial media, profil perusahaan. Berlaku di situs, Console, docs, dan email |
| SMTP & uji kirim | `/admin/emails/smtp` | Host/port/login/enkripsi/pengirim disimpan di DB (password terenkripsi APP_KEY), uji koneksi+login tanpa kirim, kirim email uji |

### Peran operator
`admin_role` pada pengguna operator (kosong = **super**, kompatibel dengan operator lama). Hanya super yang dapat memberi/mencabut akses dan mengubah peran; minimal satu super harus tersisa. Peran → bagian panel (`config/admin.php` → `roles`):

| Peran | Bagian |
|---|---|
| Super | semua |
| Finance | pembayaran, plan & kupon, laporan, tenants |
| Support | tenants, pengguna, abuse, komunikasi, email (tanpa SMTP), audit |
| Operasional | ops (job gagal, provider, webhook, log pesan), sistem, abuse, audit |

Menu sidebar menyesuaikan peran; akses langsung ke bagian terlarang → 403.

### Supervisor, worker, watchdog
- `php artisan notifora:supervise` menjalankan semua worker (jumlah per antrean: `WORKER_PROCS_HIGH|DEFAULT|CHANNELS|WEBHOOKS`) + `schedule:run` tiap menit, me-restart proses mati dengan backoff, dan menulis status ke cache. `--dry-run` menampilkan rencana.
- Setiap worker menulis denyut per antrean; Admin → Workers membaca denyut itu. **Restart worker** memakai `queue:restart` (worker selesai dulu dari job berjalan, supervisor menghidupkannya kembali dengan kode baru).
- `notifora:watchdog` (tiap 5 menit) memeriksa antrean berisi tanpa worker, job tertua menunggu, scheduler mati, job gagal ≥ 25, backup basi, dan komponen health `fail`, lalu **mengirim email ke semua operator** (dedupe 30 menit; angka tidak dianggap masalah baru).
- Hosting tanpa daemon: `WORKER_CRON_FALLBACK=true` menjadwalkan worker singkat tiap menit (cukup cron `* * * * * php artisan schedule:run`).
