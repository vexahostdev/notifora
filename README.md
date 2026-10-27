# notifora

Gateway notifikasi multi-channel — WhatsApp, Email, Telegram, In-App Inbox — dengan workflow, retry, fallback,
inbox real-time, webhook, dan billing. Laravel 12 · Blade + Alpine + Tailwind 4 · MySQL/Redis · Node socket-engine.

**Domain:** `notifora.my.id` (situs + console + docs) · `api.notifora.my.id` (API) · `ws.notifora.my.id` (WebSocket)
**Dokumentasi pengguna:** `/docs` · **Support:** support@notifora.my.id

## Dokumen perancangan
| Dokumen | Isi |
|---|---|
| [PRD_NOTIFORA_FULL.md](PRD_NOTIFORA_FULL.md) | Produk, arsitektur, modul + acceptance criteria, API contract, Decision Log (prioritas 1) |
| [BACKEND_SPEC.md](BACKEND_SPEC.md) | Skema DB, jobs, provider, engine, idempotency, socket (prioritas 2) |
| [FRONTEND_SPEC.md](FRONTEND_SPEC.md) | Design system, halaman console, komponen (prioritas 3) |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Domain, file env, Brevo, branch & alur rilis |
| [docs/VEXAHOST_XENDIT_ROUTING.md](docs/VEXAHOST_XENDIT_ROUTING.md) | Patch routing webhook Xendit prefix `NTF-` di VexaHost |

## Menjalankan lokal (tanpa Docker, tanpa MySQL/Redis)
```bash
composer install
npm install
cp .env.development.example .env.development   # isi APP_KEY: php artisan key:generate --show
cp .env.development .env
touch database/database.sqlite
php artisan migrate --seed       # akun demo + workflow contoh + API key DEV (tampil sekali)
npm run all                      # Laravel :8040 + Vite (pola sama dengan project Flustra)
```
Login demo: `demo@notifora.test` / `Password123`. Lokal memakai `QUEUE_CONNECTION=sync`, email ditulis ke log, dan
checkout memakai **sandbox** (tombol “Simulasikan pembayaran” di halaman invoice) selama `XENDIT_SECRET_KEY` kosong.

```bash
php vendor/bin/pest      # test suite
npm run all:full         # + queue worker + socket-engine (butuh Redis, QUEUE_CONNECTION=redis)
```

## Environment & branch

| Branch | Environment | File env | Domain |
|---|---|---|---|
| `dev` | development | `.env.development` | `localhost:8040` |
| `staging` | staging | `.env.staging` | `staging.notifora.my.id` |
| `main` | production | `.env.production` | `notifora.my.id` |

Template `.env.*.example` ada di git; file `.env*` asli di-gitignore. Detail: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

## Status implementasi

**Ada dan teruji:** trigger API (+bulk, cancel), idempotency atomik, rate limit & kuota per plan, workflow engine
(filter, delay, fallback, opt-out, versioning), template engine aman, provider WhatsApp/Email/Telegram/In-App + circuit
breaker, webhook status masuk & keluar (HMAC, anti-SSRF), inbox + token JWT + socket-engine, console lengkap
(dashboard, editor workflow + simulator, subscriber, channel, log, API key, webhook, team + undangan, usage, audit, akun),
**checkout & billing Xendit** (plan, durasi, perpanjang/upgrade, masa berlaku, masa tenggang), **email sistem Brevo**
(lihat docs), **halaman docs** `/docs` dengan pencarian Ctrl+K, mode terang/gelap, landing + auth, Docker, CI.

**Belum / perlu tindakan Anda:**
- **Kredensial & akun eksternal**: Brevo (SMTP + verifikasi domain), Xendit (kunci API & token), dan **patch routing `NTF-` di VexaHost** (belum diterapkan, lihat docs/VEXAHOST_XENDIT_ROUTING.md).
- Callback Xendit & SMTP Brevo **belum diuji terhadap layanan asli** (diuji dengan stub HTTP/Mail fake); lakukan satu pembayaran uji di staging.
- Adapter VexaHost WA & Postivo mengasumsikan bentuk request/respons umum; cocokkan dengan gateway asli.
- Teruji di SQLite + queue sync; jalankan di staging dengan MySQL 8 + Redis + worker (build Docker belum dicoba).
- **2FA TOTP** dan **verifikasi email** belum ada.
- Belum: SDK resmi, Push/SMS/Slack, digest, cabang DAG, billing otomatis berulang (perpanjangan manual per periode).
