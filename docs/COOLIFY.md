# Panduan Deploy Notifora ke Coolify (+ Stalwart Mail Server)

Mengikuti pola proyek VexaHost dan Flustra: satu VPS ber-Coolify, tiga environment dari tiga branch Git (`main`, `staging`, `dev`), file `.env.<env>` disalin ke Coolify, domain di bawah `notifora.my.id`.

Bedanya dengan VexaHost: Notifora butuh **worker antrean, scheduler, socket engine, MySQL, dan Redis** sekaligus, jadi dipasang dengan build pack **Docker Compose** memakai `docker-compose.yml` di repo (bukan Nixpacks satu-aplikasi).

---

## 1. Ringkasan

| Item | Nilai |
|---|---|
| Repository | `vexahostdev/notifora` |
| Stack | Laravel 12 · PHP 8.2 · MySQL 8 · Redis 7 · Node (socket engine) |
| Build pack | **Docker Compose** (`docker-compose.yml`) |
| Container | `notifora-app` (web, port 8040), `notifora-worker`, `notifora-scheduler`, `notifora-socket` (8042), `notifora-mysql`, `notifora-redis` |

| Environment | Branch | App | API | WebSocket | Database | Template env |
|---|---|---|---|---|---|---|
| Production | `main` | `https://notifora.my.id` | `https://api.notifora.my.id` | `wss://ws.notifora.my.id` | `notifora_db` | `.env.production` |
| Staging | `staging` | `https://staging.notifora.my.id` | `https://api-staging.notifora.my.id` | `wss://ws-staging.notifora.my.id` | `notifora_staging` | `.env.staging` |
| Development | `dev` | lokal `http://localhost:8040` | – | – | `notifora` | `.env.development` |

Development tetap dijalankan lokal (XAMPP + `npm run all`); yang dideploy ke server hanya **production** dan **staging**.

---

## 2. Kebutuhan server

### 2.1 Spesifikasi VPS

| Skenario | CPU | RAM | Disk | Keterangan |
|---|---|---|---|---|
| Staging saja | 2 vCPU | 4 GB | 40 GB SSD | Coolify (±1 GB) + stack Notifora |
| **Production + staging (1 VPS)** | **4 vCPU** | **8 GB** | **80 GB SSD** | Direkomendasikan untuk mulai |
| + Stalwart mail server | +1 vCPU | +1–2 GB | +20 GB | Disarankan VPS **terpisah** untuk reputasi IP email |
| Trafik tinggi | 8 vCPU | 16 GB | 160 GB | Pisahkan MySQL ke server sendiri |

OS: **Ubuntu 22.04/24.04 LTS** (bersih, tanpa Nginx/Apache/MySQL bawaan yang memakai port 80/443/3306). Aktifkan swap 2 GB.

### 2.2 Port yang dibuka (firewall)

| Port | Untuk | Catatan |
|---|---|---|
| 22 | SSH | Batasi ke IP Anda / pakai key saja |
| 80, 443 | Traefik Coolify (HTTP/HTTPS) | Wajib, diperlukan Let's Encrypt |
| 8000 | Panel Coolify | Tutup setelah domain panel dipasang (`coolify.notifora.my.id`) |
| 25, 465, 587, 993, 143 | **Stalwart** (SMTP/IMAP) | Hanya bila memasang Stalwart (bagian 8) |
| 3306, 6379, 8040, 8042 | MySQL/Redis/app/socket | **Jangan dibuka ke internet**; hanya jaringan Docker |

```bash
ufw default deny incoming && ufw default allow outgoing
ufw allow 22,80,443/tcp
ufw allow 8000/tcp        # sementara, sebelum domain panel
# hanya jika Stalwart di server ini:
ufw allow 25,465,587,993,143/tcp
ufw enable
```

### 2.3 Perangkat lunak di server
Hanya ini yang perlu dipasang manual; sisanya dibawa container:

```bash
apt update && apt install -y curl ufw fail2ban unattended-upgrades
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | sudo bash   # memasang Docker + Coolify
```

Container sudah membawa: PHP 8.2-FPM, Nginx, Supervisor, `mysqldump` (backup), Node (socket engine), MySQL 8, Redis 7.

### 2.4 Akun & layanan eksternal
- Domain `notifora.my.id` (DNS di Cloudflare) · repo GitHub `vexahostdev/notifora` (GitHub App di Coolify)
- Brevo SMTP (email, atau Stalwart sebagai pengganti/utama) · Xendit (lewat router webhook VexaHost, prefix `NTF-`)
- Penyimpanan luar server untuk backup (S3 / Backblaze / Google Drive via rclone)

---

## 3. DNS

Arahkan ke IP VPS (`<IP_VPS>`):

| Tipe | Nama | Nilai | Proxy |
|---|---|---|---|
| A | `@` | `<IP_VPS>` | Proxied/DNS only |
| A | `www` | `<IP_VPS>` | Proxied/DNS only |
| A | `api` | `<IP_VPS>` | Proxied/DNS only |
| A | `ws` | `<IP_VPS>` | **DNS only** (WebSocket lebih aman tanpa proxy) |
| A | `staging`, `api-staging`, `ws-staging` | `<IP_VPS>` | sama seperti di atas |
| A | `coolify` | `<IP_VPS>` | DNS only (panel Coolify) |
| A | `mail` | `<IP_VPS_MAIL>` | **DNS only — wajib** (bagian 8) |

> Cloudflare SSL/TLS mode harus **Full** atau **Full (Strict)** agar tidak terjadi `ERR_TOO_MANY_REDIRECTS`. Record email **tidak boleh** di-proxy.

---

## 4. Database & kunci rahasia

MySQL ikut dalam compose, jadi tidak perlu membuat database manual: container `notifora-mysql` membuat `${DB_DATABASE}` dan user `${DB_USERNAME}` otomatis dari environment variable.

Siapkan nilai acak (jangan pakai contoh):

```bash
php -r "echo bin2hex(random_bytes(24)),PHP_EOL;"   # DB_PASSWORD, DB_ROOT_PASSWORD, REDIS_PASSWORD
php -r "echo bin2hex(random_bytes(32)),PHP_EOL;"   # SOCKET_JWT_SECRET, INTERNAL_API_SECRET
php artisan key:generate --show                    # APP_KEY
```

**Simpan `APP_KEY` di tempat terpisah (password manager).** Kredensial provider milik tenant dienkripsi dengan key ini; kalau hilang, semuanya tidak terbaca.

---

## 5. Install Coolify & hubungkan GitHub

1. Jalankan skrip install (bagian 2.3), buka `http://<IP_VPS>:8000`, buat akun admin Coolify.
2. *Settings → Instance*: isi domain `https://coolify.notifora.my.id`, simpan (Let's Encrypt otomatis). Setelah itu tutup port 8000.
3. *Sources → + Add → GitHub App*, pasang pada repo `vexahostdev/notifora`.
4. *Servers → localhost → Validate*, pastikan Docker terdeteksi.
5. Aktifkan backup konfigurasi Coolify ke S3 (*Settings → Backup*).

---

## 6. Deploy aplikasi (ulangi per environment)

1. *Projects → + Add* `Notifora`, lalu environment `production` (dan `staging`).
2. *+ Add New Resource → Private Repository (with GitHub App)* → repo `vexahostdev/notifora`.
3. Isi:
   - **Branch**: `main` (production) / `staging` (staging)
   - **Build Pack**: **Docker Compose**
   - **Docker Compose Location**: `/docker-compose.yml`
   - **Name**: `notifora-production` / `notifora-staging`
4. Klik **Continue**. Coolify membaca service dari compose.

### 6.1 Domain per service

| Service | Domain (production) | Domain (staging) |
|---|---|---|
| `notifora-app` | `https://notifora.my.id,https://api.notifora.my.id,https://www.notifora.my.id` | `https://staging.notifora.my.id,https://api-staging.notifora.my.id` |
| `notifora-socket` | `https://ws.notifora.my.id:8042` | `https://ws-staging.notifora.my.id:8042` |
| `notifora-worker`, `notifora-scheduler`, `notifora-mysql`, `notifora-redis` | *(kosongkan — internal)* | *(kosongkan)* |

Akhiran `:8042` memberi tahu Traefik port internal socket; klien tetap memakai `wss://ws.notifora.my.id` (443).

### 6.2 Environment Variables
Buka *Environment Variables* → mode **Developer view**, tempel isi file env yang sesuai (`.env.production` / `.env.staging`), lalu pastikan:

```env
APP_ENV=production                # staging: staging
APP_DEBUG=false
APP_URL=https://notifora.my.id
API_URL=https://api.notifora.my.id
DB_DATABASE=notifora_db           # staging: notifora_staging
DB_USERNAME=notifora_user
DB_PASSWORD=<acak>
DB_ROOT_PASSWORD=<acak>
REDIS_PASSWORD=<acak>
SOCKET_JWT_SECRET=<acak 64 hex>
INTERNAL_API_SECRET=<acak 64 hex>
SOCKET_PUBLIC_URL=wss://ws.notifora.my.id
SOCKET_ALLOWED_ORIGINS=https://notifora.my.id
SOCKET_HEALTH_URL=http://notifora-socket:8042/health
SESSION_SECURE_COOKIE=true
ADMIN_2FA_REQUIRED=true
ADMIN_EMAIL=admin@notifora.my.id
ADMIN_PASSWORD=<kuat, ganti setelah login pertama>
RUN_MIGRATIONS=true               # deploy pertama & tiap rilis yang punya migrasi
```

`DB_HOST`, `REDIS_HOST`, `QUEUE_CONNECTION=redis`, dan `CACHE_STORE=redis` sudah dipaksa oleh compose; tidak perlu diubah. Nilai rahasia (Xendit, Brevo, `APP_KEY`) ditempel di Coolify saja, jangan di repo.

### 6.3 Storage persisten
Compose sudah membuat volume bersama `app-storage` → `/var/www/html/storage/app` untuk **unggahan logo/favicon** dan **backup database** (dibuat scheduler, diunduh dari web), serta `mysql-data` dan `redis-data`. Tidak ada langkah tambahan; jangan menghapus volume saat *Redeploy*.

### 6.4 Deploy
Klik **Deploy**. Entrypoint otomatis: menyiapkan direktori, `storage:link`, `config/route/view:cache`, dan `migrate --force` bila `RUN_MIGRATIONS=true`.

Setelah container `notifora-app` sehat, buka *Terminal* service tersebut:

```bash
php artisan notifora:make-admin      # buat/sinkron operator dari ADMIN_EMAIL & ADMIN_PASSWORD
php artisan notifora:supervise --dry-run --force   # (opsional) lihat rencana worker
```

Login `https://notifora.my.id/login` dengan akun admin → aktifkan 2FA di `/security/2fa` → buka `/admin`.

### 6.5 Auto-deploy
Aktifkan *Webhooks → GitHub* pada resource: push ke `main` men-deploy production, push ke `staging` men-deploy staging. Pastikan CI GitHub hijau sebelum merge ke `main`.

---

## 7. Integrasi eksternal

### Xendit lewat VexaHost
Tambahkan di environment VexaHost production/staging:

```env
NOTIFORA_WEBHOOK_URL=https://notifora.my.id/api/payment/xendit/callback
# staging VexaHost: https://staging.notifora.my.id/api/payment/xendit/callback
```

`INTERNAL_WEBHOOK_SECRET` di Notifora harus **identik** dengan VexaHost (gunakan nilai acak panjang di keduanya; jangan pakai default). Detail: `docs/VEXAHOST_XENDIT_ROUTING.md`.

### Email — pilih salah satu
- **Brevo (siap pakai):** verifikasi domain di Brevo, pasang SPF/DKIM/DMARC, isi `MAIL_*` (atau lewat **Admin → SMTP & uji kirim**), `MAIL_MAILER=smtp`.
- **Stalwart sendiri (bagian 8)**, dengan Brevo sebagai cadangan lewat `MAIL_MAILER=failover`.

---

## 8. Stalwart Mail Server (opsional, email sendiri)

Pola yang sama dengan Postivo: **Stalwart sebagai relay utama, Brevo sebagai cadangan**. Notifora hanya perlu SMTP biasa, jadi Stalwart bisa dipasang kapan saja tanpa mengubah kode.

### 8.1 Syarat server (lebih ketat dari aplikasi web)
| Syarat | Alasan |
|---|---|
| **IP publik khusus, bersih dari blacklist** (cek mxtoolbox.com/blacklists) | Reputasi IP menentukan masuk inbox atau spam |
| **Provider mengizinkan port 25 keluar** | Stalwart mengirim ke Gmail/Yahoo lewat port 25. Banyak VPS memblokirnya; minta dibuka ke support atau gunakan relay |
| **Reverse DNS (PTR)** IP = `mail.notifora.my.id` | Diatur di panel provider VPS. Tanpa PTR email sering ditolak |
| Port 25, 465, 587, 993, 143 terbuka (bagian 2.2) | SMTP & IMAP |
| RAM ≥ 1 GB, disk ≥ 20 GB, backup volume data | Mailbox & antrean |
| Hostname `mail.notifora.my.id` dengan record A **DNS only** | Sertifikat TLS & PTR harus cocok |
| **Disarankan VPS terpisah** dari aplikasi | Isolasi reputasi dan beban |

Bila port 25 terblokir, jadikan **Brevo sebagai smarthost/relay di Stalwart** (atau pakai `failover`) — jangan memaksa.

### 8.2 Pasang di Coolify
1. *+ Add New Resource → Docker Image* → `stalwartlabs/stalwart:latest`, nama `notifora-mail`.
2. **Ports Mappings**: `25:25,465:465,587:587,993:993,143:143,4190:4190` (SMTP/IMAP/Sieve tidak lewat Traefik, jadi dipublikasikan langsung).
3. **Domain** panel admin: `https://mail.notifora.my.id:8080`.
4. **Persistent Storage**: volume `stalwart-data` → `/opt/stalwart`.
5. Deploy, lalu baca *Logs*: kata sandi admin awal tercetak sekali. Login ke panel, **ganti segera**.
6. **TLS**: Traefik memegang port 80/443, jadi HTTP-01 milik Stalwart tidak bisa. Aktifkan **ACME DNS-01 (Cloudflare API token)** di *Settings → TLS → ACME* untuk `mail.notifora.my.id`.

### 8.3 Domain, akun, dan DNS email
Di panel Stalwart: *Directory → Domains → Add* `notifora.my.id` (aktifkan DKIM, salin record), buat akun `noreply@notifora.my.id` dan `support@notifora.my.id` (password kuat atau app password).

| Tipe | Nama | Nilai |
|---|---|---|
| A | `mail` | `<IP_VPS_MAIL>` (DNS only) |
| MX | `@` | `10 mail.notifora.my.id` |
| TXT | `@` | `v=spf1 mx ip4:<IP_VPS_MAIL> include:spf.brevo.com ~all` |
| TXT | `<selector>._domainkey` | nilai DKIM dari Stalwart (RSA/Ed25519) |
| TXT | `_dmarc` | `v=DMARC1; p=quarantine; rua=mailto:dmarc@notifora.my.id` |
| TXT | `_mta-sts` / CNAME | opsional (MTA-STS, TLS-RPT) |
| PTR | *(di provider)* | `mail.notifora.my.id` |

`include:spf.brevo.com` ditambahkan karena Brevo tetap dipakai sebagai cadangan; hapus bila tidak dipakai. Uji dengan mail-tester.com (target ≥ 9/10) dan https://www.checktls.com.

### 8.4 Sambungkan Notifora ke Stalwart
Dua cara, bisa tanpa deploy ulang:

**A. Lewat panel (paling mudah):** Admin → *SMTP & uji kirim* → host `mail.notifora.my.id`, port `587`, enkripsi STARTTLS, login `noreply@notifora.my.id`, centang *Pakai konfigurasi ini* → *Uji koneksi SMTP* → *Kirim email uji*.

**B. Lewat environment (Stalwart utama + Brevo cadangan):**
```env
MAIL_MAILER=failover
STALWART_SMTP_HOST=mail.notifora.my.id      # atau nama service internal bila satu jaringan Docker
STALWART_SMTP_PORT=587
STALWART_SMTP_USERNAME=noreply@notifora.my.id
STALWART_SMTP_PASSWORD=<password akun>
# MAIL_HOST/USERNAME/PASSWORD tetap diisi kredensial Brevo (cadangan)
MAIL_FROM_ADDRESS=noreply@notifora.my.id
```
Urutan percobaan: **Stalwart → Brevo → log**. Admin → *Kesiapan production* dan *Kesehatan sistem* membaca kredensial relay yang aktif.

### 8.5 Perawatan Stalwart
- Backup volume `stalwart-data` bersama backup database; uji restore.
- Pantau antrean & bounce di panel Stalwart; daftarkan domain di Google Postmaster Tools.
- Naikkan volume kirim bertahap (warm-up IP baru): 50 → 200 → 1.000 email/hari selama 2–4 minggu.

---

## 9. Checklist setelah deploy

- [ ] `https://notifora.my.id` HTTPS valid, landing tampil dengan CSS (bukan HTML polos)
- [ ] `/up` 200 dan **Admin → Kesehatan sistem** semua hijau (Database, Redis, Queue, Scheduler, Socket)
- [ ] **Admin → Workers & scheduler**: semua antrean "aktif", scheduler "berdenyut"
- [ ] **Admin → Kesiapan production** tanpa item merah (skor ≥ 90%)
- [ ] Login admin berhasil, 2FA aktif, password awal diganti
- [ ] Email uji dari **Admin → SMTP & uji kirim** masuk inbox (bukan spam)
- [ ] **Admin → Backup database → Cadangkan sekarang** berhasil; salinan keluar server terjadwal (`deploy/scripts/backup-offsite.sh`)
- [ ] Widget inbox terhubung ke `wss://ws.notifora.my.id`
- [ ] Satu pembayaran Xendit mode test lewat VexaHost mengaktifkan plan
- [ ] Logo & branding di **Admin → Branding & kontak** tampil (unggahan tersimpan di volume)

---

## 10. Troubleshooting

| Gejala | Penyebab | Solusi |
|---|---|---|
| Halaman polos tanpa CSS | `APP_URL` bukan https / aset belum ter-build | Pastikan `APP_URL=https://…`; build ada di image (`npm run build` di Dockerfile) |
| 502 / *no available server* | Domain dipasang di service yang salah atau port salah | Domain hanya di `notifora-app` (tanpa port) dan `notifora-socket` (`:8042`) |
| `SQLSTATE[HY000] [2002]` | MySQL belum siap / password beda | Tunggu healthcheck; `DB_PASSWORD` harus sama untuk app dan `notifora-mysql` (volume lama menyimpan password lama) |
| Worker "tidak ada worker aktif" | Container `notifora-worker` mati atau `QUEUE_CONNECTION` bukan redis | Cek log service; env `REDIS_PASSWORD` harus sama di semua service |
| Scheduler "Mati" | Container `notifora-scheduler` mati | Cek log; `docker compose logs notifora-scheduler` |
| Logo hilang setelah redeploy | Volume `app-storage` terhapus | Jangan hapus volume; unggah ulang di Branding |
| Backup gagal "mysqldump" | Image lama tanpa `mariadb-client` | Rebuild tanpa cache |
| WebSocket putus-putus | `ws` di-proxy Cloudflare / origin salah | Record `ws` DNS only; `SOCKET_ALLOWED_ORIGINS` = origin app |
| Email ditolak/spam | SPF/DKIM/DMARC/PTR belum benar | Bagian 8.3; cek mail-tester |
| Port 25 timeout | Provider memblokir | Minta dibuka, atau relay via Brevo |
| Setelah ganti `.env` tak berubah | Config ter-cache | *Redeploy* (entrypoint men-cache ulang) atau Admin → Workers → Optimize |

---

## 11. Perbandingan dengan VexaHost / Flustra

| Hal | VexaHost | Notifora |
|---|---|---|
| Build pack | Nixpacks, `php artisan serve` | Docker Compose (multi-service) |
| Database | MySQL Coolify terpisah, dibuat manual | MySQL ikut compose, dibuat otomatis |
| Worker | `queue:work` opsional | Wajib: `notifora-worker` + `notifora-scheduler` (atau `ROLE=supervise`) |
| Env | salin `.env.<env>` | salin `.env.<env>` (sama) |
| Post-deploy | perintah artisan manual | otomatis di entrypoint (`RUN_MIGRATIONS=true`) |
| Storage persisten | `/app/storage/app/public` | volume `app-storage` (sudah di compose) |
| Email | Brevo | Brevo, atau Stalwart + Brevo cadangan |
