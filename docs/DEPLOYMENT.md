# Deployment & Environment

## Domain

| Environment | Situs + Console + Docs | API (`/v1`) | WebSocket |
|---|---|---|---|
| development | `http://localhost:8040` | sama | `ws://localhost:8042` |
| staging | `https://staging.notifora.my.id` | `https://api-staging.notifora.my.id` | `wss://ws-staging.notifora.my.id` |
| production | `https://notifora.my.id` | `https://api.notifora.my.id` | `wss://ws.notifora.my.id` |

`api.*` dan situs utama menunjuk ke **aplikasi yang sama** (port 8040); hanya `ws.*` menunjuk ke `notifora-socket` (port 8042).
Buat record DNS A/AAAA untuk ketiga host per environment dan terbitkan sertifikat TLS (mis. lewat Coolify/Let's Encrypt).

## File environment

| File | Fungsi | Di git? |
|---|---|---|
| `.env.development.example` / `.env.staging.example` / `.env.production.example` | template | ya |
| `.env.development` / `.env.staging` / `.env.production` | nilai asli per environment | **tidak** (gitignore) |
| `.env` | file aktif yang dibaca Laravel | **tidak** |

```bash
cp .env.development .env            # lokal
docker compose --env-file .env.staging    up -d --build   # staging   (set ENV_FILE=.env.staging)
docker compose --env-file .env.production up -d --build   # production (set ENV_FILE=.env.production)
```

`ENV_FILE` menentukan `env_file` container; `--env-file` menentukan interpolasi `${...}` di `docker-compose.yml`.
Gunakan keduanya dengan file yang sama:

```bash
ENV_FILE=.env.production docker compose --env-file .env.production up -d --build
```

Nilai rahasia (`APP_KEY`, `SOCKET_JWT_SECRET`, `INTERNAL_API_SECRET`, password DB/Redis) sudah di-generate acak di file
lokal. **Isi manual**: kredensial Brevo (`MAIL_USERNAME`, `MAIL_PASSWORD`), `XENDIT_SECRET_KEY`,
`XENDIT_WEBHOOK_TOKEN`, `INTERNAL_WEBHOOK_SECRET` (harus sama dengan VexaHost).

## Email (Brevo SMTP)

1. Daftar Brevo, verifikasi domain `notifora.my.id` (tambahkan DKIM, SPF, DMARC di DNS).
2. Tambahkan pengirim `noreply@notifora.my.id` dan `support@notifora.my.id`.
3. Buat **SMTP key** → isi `MAIL_USERNAME` (login SMTP Brevo) dan `MAIL_PASSWORD` (SMTP key). Host `smtp-relay.brevo.com`, port `587`.
4. Uji: reset password dari halaman login, atau `php artisan tinker` → `app(\App\Services\SystemNotifier::class)->welcome(\App\Models\User::first())`.

Selama kredensial kosong di staging/production, kegagalan kirim dicatat ke log dan **tidak** mengganggu pengguna.
Di development `MAIL_MAILER=log` menulis email ke `storage/logs/laravel.log`.

## Pembayaran (Xendit via VexaHost)

Lihat [VEXAHOST_XENDIT_ROUTING.md](VEXAHOST_XENDIT_ROUTING.md). Ringkas: terapkan patch routing prefix `NTF-` di VexaHost, isi
`INTERNAL_WEBHOOK_SECRET` yang sama di kedua aplikasi, uji endpoint health, lalu lakukan satu pembayaran uji dengan kunci test Xendit di staging.

## Branch & alur rilis

| Branch | Environment | Aturan |
|---|---|---|
| `dev` | development / integrasi | kerja harian, PR kecil |
| `staging` | staging | merge dari `dev` setelah CI hijau; uji end-to-end |
| `main` | production | merge dari `staging` setelah lolos uji staging |

CI (`.github/workflows/ci.yml`) berjalan di ketiga branch dan semua PR.

## Setelah deploy

```bash
docker compose exec notifora-app php artisan migrate --force
docker compose exec notifora-app php artisan db:seed --force   # HANYA development; seeder menolak production
```

Scheduler (`notifora-scheduler`) menjalankan: retensi log harian, penyapuan billing tiap jam (invoice basi, pengingat plan,
masa tenggang, turun ke Free), dan pembersihan job gagal.
