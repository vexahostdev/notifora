# NOTIFORA — Frontend Technical Specification
**Version:** 2.1.0 (Development-Ready)
**Stack:** Laravel 12 Blade, Alpine.js 3.14+, Tailwind CSS 4 (`@tailwindcss/vite`), Vite 7, Chart.js 4.4, Socket.io client 4.7
**Prioritas dokumen:** [PRD](PRD_NOTIFORA_FULL.md) → [BACKEND_SPEC](BACKEND_SPEC.md) → dokumen ini
**Status:** diimplementasikan (lihat README → Status implementasi)
**Aset referensi:** `templates/notifora_landing.html`, `templates/notifora_auth.html` (prototype visual; lihat §3)

---

## DAFTAR ISI
1. [Tech Stack & Asset Pipeline](#1-tech-stack--asset-pipeline)
2. [Design System](#2-design-system)
3. [Strategi Prototype → Blade](#3-strategi-prototype--blade)
4. [Hierarki View & Routing Console](#4-hierarki-view--routing-console)
5. [Spesifikasi Halaman Console](#5-spesifikasi-halaman-console)
6. [Komponen Alpine & State](#6-komponen-alpine--state)
7. [Workflow Editor](#7-workflow-editor)
8. [Template Editor](#8-template-editor)
9. [Inbox Widget (`x-notifora-inbox`)](#9-inbox-widget)
10. [Logs & Telemetry](#10-logs--telemetry)
11. [Aksesibilitas, Responsif, Performa](#11-aksesibilitas-responsif-performa)
12. [Testing Frontend](#12-testing-frontend)
13. [Aturan untuk Developer / AI Agent](#13-aturan-untuk-developer--ai-agent)

---

## 1. TECH STACK & ASSET PIPELINE

| Layer | Teknologi | Versi | Catatan |
|---|---|---|---|
| View | Blade + Blade Components | Laravel 12 | `<x-…>` anonymous components |
| Interaktivitas | Alpine.js | 3.14+ | + plugin `@alpinejs/collapse`, `@alpinejs/focus` |
| CSS | Tailwind CSS | **4.x** (satu versi; tidak ada v3) | Konfigurasi via `@theme` di CSS |
| Build | Vite | **6.4.x** + `laravel-vite-plugin` 1.x | Upgrade ke 7 menunggu peer-dependency `laravel-vite-plugin` |
| Chart | Chart.js | 4.4+ | di-import dinamis hanya di halaman dashboard |
| Icon | Lucide (SVG inline via Blade component `<x-icon name="…"/>`) | 0.4xx | stroke 1.75 |
| Realtime | `socket.io-client` | 4.7+ | dibundel via NPM (bukan CDN) |
| Font | Archivo Black, Inter, JetBrains Mono, Noto Sans JP | self-host via `@fontsource/*` | tidak memakai Google Fonts di produksi (privasi + performa) |

`vite.config.js`:
```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/css/marketing.css', 'resources/js/app.js', 'resources/js/marketing.js'],
      refresh: true,
    }),
    tailwindcss(),
  ],
  server: { host: '0.0.0.0', port: 5173, hmr: { host: 'localhost' } },
});
```
Dua bundle: **console** (`app.*`, atmosfer gelap) dan **marketing/auth** (`marketing.*`, atmosfer brutalist). Chart.js dan socket.io hanya di-import di halaman yang memerlukan (dynamic `import()`).

---

## 2. DESIGN SYSTEM

### 2.1 Dual-Atmosphere
| Area | Gaya | Layout Blade |
|---|---|---|
| Marketing + Auth | *Cyber-Brutalist + Japanese Graffiti*: beton abu, biru kobalt, border 2–3px hitam, bayangan keras offset | `layouts.marketing`, `layouts.guest` |
| Console | *Dark Terminal Console*: zinc gelap, border 1px, aksen kobalt | `layouts.app` |

### 2.1b Mode terang / gelap
Dua tema di semua area (console, landing, auth) lewat atribut `<html data-theme="light|dark">`.
- Tema dipasang sebelum render pertama oleh `partials/theme-init.blade.php` (tanpa kedipan). Pilihan tersimpan di `localStorage` kunci `notifora_theme` dan dipakai bersama oleh console dan situs publik; bila belum memilih, mengikuti `prefers-color-scheme`.
- Komponen `<x-theme-toggle>` ada di header console, landing, dan auth; memicu event `themechange` (Chart.js membaca ulang warna dari CSS variable).
- Implementasi: variabel warna dibalik per tema (skala `zinc` dan `white` di console; `ink`/`concrete`/`white` di marketing) sehingga class yang sama benar di kedua tema. Teks putih di atas warna solid ditulis `text-[#fff]` agar tidak ikut berbalik. Blok kode/terminal dan footer landing selalu gelap.

### 2.2 Token (Tailwind 4 `@theme`) — `resources/css/app.css`
```css
@import "tailwindcss";
@theme {
  --color-cobalt: #0033FF;   --color-cobalt-hover: #0026CC;
  --color-ink: #0D0D0D;      --color-concrete: #E3E7EE;  --color-concrete-2: #DCE1E9;
  --color-surface: #09090B;  --color-surface-2: #121216; --color-line: #27272A;
  --color-ok: #10B981; --color-warn: #F59E0B; --color-err: #EF4444; --color-info: #3B82F6;
  --font-display: "Archivo Black", Inter, sans-serif;
  --font-sans: Inter, "Noto Sans JP", sans-serif;
  --font-mono: "JetBrains Mono", ui-monospace, monospace;
  --shadow-brutal: 6px 6px 0 var(--color-cobalt);
  --shadow-brutal-ink: 4px 4px 0 var(--color-ink);
}
```
Console: body `bg-surface text-zinc-200`, kartu `bg-surface-2 border border-line rounded-lg`. Kontras teks minimum WCAG AA (zinc-400 ke atas di atas surface).

### 2.3 Warna semantik status
| Status | Warna | Label |
|---|---|---|
| QUEUED / PENDING | zinc-400 | Queued |
| PROCESSING / WAITING | info | Processing / Waiting |
| SENT | cobalt (terang) | Sent |
| DELIVERED / COMPLETED | ok | Delivered |
| READ | ok + ikon centang ganda | Read |
| PARTIAL / SKIPPED | warn | Partial / Skipped |
| FAILED | err | Failed |
Status **selalu** disertai teks/ikon, tidak hanya warna.

### 2.4 Komponen dasar (`resources/views/components/`)
`button` (varian: `primary|secondary|ghost|danger|brutal`), `input`, `select`, `textarea`, `toggle`, `badge`, `status-badge`, `channel-icon`, `card`, `modal`, `drawer`, `dropdown`, `tabs`, `table` (wrapper `overflow-x-auto`), `empty-state`, `skeleton`, `toast` (store Alpine), `copy-button`, `code-block`, `stat-tile`, `pagination` (cursor), `confirm` (dialog hapus), `icon`.

---

## 3. STRATEGI PROTOTYPE → BLADE

File `templates/*.html` adalah **prototype visual** (React via CDN + Babel). Sebelum produksi harus dikonversi karena Babel-standalone & Tailwind-CDN tidak boleh dipakai di production (lambat, tanpa CSP, FOUC).

| Prototype | Target | Catatan konversi |
|---|---|---|
| `notifora_landing.html` | `resources/views/marketing/landing.blade.php` + `resources/js/marketing.js` | Animasi: Framer Motion → CSS keyframes + `IntersectionObserver` + Alpine; SVG graffiti & filter dipindah ke partial `marketing/partials/svg-defs.blade.php`; parallax mouse → ringan (`requestAnimationFrame`, nonaktif bila `prefers-reduced-motion`) |
| `notifora_auth.html` | `auth/login.blade.php`, `auth/register.blade.php` + form POST nyata | Hilangkan mock (API key palsu, `alert`); register tidak menampilkan API key — key dibuat di Console (§5.9) |
| Spray canvas easter-egg | Alpine component `sprayCanvas` | Tetap opsional, lazy-init |

**Perbaikan yang sudah berlaku pada prototype di folder `templates/` (v2.1):** tautan antar halaman benar (`notifora_landing.html` ↔ `notifora_auth.html`), `user-select: none` tidak lagi global, tombol copy memakai Clipboard API, key mock tidak lagi diklaim "provisioned", curl contoh sesuai API (`/v1/events/trigger`), batas rate-limit disebut "sesuai plan", penyebutan MongoDB dihapus, pricing sesuai PRD §12.2.

**Footer standar (semua layout):** `© {tahun} notifora. All rights reserved. Created by notifora` (`partials/credit.blade.php`).

Aturan konten marketing: setiap klaim angka harus merujuk PRD §12 (latensi = ack ingress, bukan pengiriman akhir). Salinan bahasa: Inggris untuk headline, Jepang sebagai aksen dekoratif (disertai `lang="ja"`), Indonesia untuk modal/docs.

---

## 4. HIERARKI VIEW & ROUTING CONSOLE

```
resources/views/
├── layouts/ {marketing, guest, app}.blade.php
├── components/ …(§2.4) + notifora-inbox.blade.php
├── marketing/ {landing, pricing, docs-index}.blade.php (+ partials/)
├── auth/ {login, register, forgot-password, reset-password, two-factor-challenge, verify-email, accept-invite}.blade.php
└── console/
    ├── dashboard.blade.php
    ├── workflows/ {index, create, edit, test, executions}.blade.php
    ├── subscribers/ {index, show}.blade.php
    ├── channels/ {index, edit}.blade.php
    ├── logs/ {index, show}.blade.php
    ├── inbox-preview.blade.php
    └── settings/ {api-keys, webhooks, team, usage, profile, security, audit}.blade.php
```

| Route (notifora.my.id/console) | Controller | Role minimum |
|---|---|---|
| `/` dashboard | DashboardController | VIEWER |
| `/workflows` `/workflows/{id}/edit` `/workflows/{id}/test` | WorkflowController | VIEWER (lihat) / DEVELOPER (ubah) |
| `/subscribers` `/subscribers/{id}` | SubscriberController | VIEWER |
| `/channels` `/channels/{id}/edit` | ChannelProviderController | DEVELOPER |
| `/logs` `/logs/{id}` | MessageLogController | VIEWER |
| `/settings/api-keys` | ApiKeyController | DEVELOPER |
| `/settings/webhooks` | WebhookController | DEVELOPER |
| `/settings/team` | TeamController | ADMIN |
| `/settings/usage` | UsageController | ADMIN |
| `/settings/audit` | AuditController | ADMIN |
Environment switcher (DEV/PROD) di header: `POST /environment/switch` (disimpan di session; **bukan** hanya localStorage + reload seperti desain lama). Banner merah tipis permanen saat berada di PRODUCTION.

---

## 5. SPESIFIKASI HALAMAN CONSOLE

Setiap halaman wajib memiliki 4 state: **loading** (skeleton), **empty** (CTA), **error** (pesan + retry), **populated**.

### 5.1 Dashboard
KPI tile (24 jam): Total Events, Delivery Success %, Avg Latency (enqueue→SENT), Failed. Grafik garis throughput (24j/7h/30h toggle), donut channel share, tabel "20 kegagalan terbaru" (link ke log), kartu usage bulan ini (progress bar quota; ≥ 80% kuning, ≥ 100% merah). Data via endpoint JSON internal `/console/api/dashboard?range=` (cache 30 dtk).

### 5.2 Workflows — index
Tabel: Name, trigger key (mono + copy), Status (toggle Active/Draft), Channels (ikon), Last run, Executions 7d. Aksi: Create, Duplicate, Archive. Filter status, search.

### 5.3 Workflows — edit
Editor (§7) + panel kanan "Step settings" + tombol **Save draft**, **Publish** (konfirmasi menampilkan diff ringkas jumlah step), **Test**. Badge versi (`v3 published • draft changes`).

### 5.4 Workflows — test (simulator)
Pilih subscriber (existing / inline JSON), editor JSON payload (validasi JSON live), tombol **Send test** → `POST` internal yang menandai `is_test=true` dan mengirim ke provider **nyata** hanya jika centang "Send to real providers" (default off → provider memakai mode dry-run, hasil dirender saja). Hasil: timeline step dengan status & rendered output.

### 5.5 Subscribers
Index: search (external id/email/phone), filter channel yang tersedia, cursor pagination. Show: profil, destination per channel (masked, tombol reveal untuk ADMIN dengan audit log), preferensi (matrix toggle), 20 pesan terakhir, tombol Delete/Erase (konfirmasi ketik `external_id`).

### 5.6 Channels
Grid kartu per channel (WhatsApp, Email, Telegram, In-App) dengan status (Active/Degraded/Disabled/Not configured). Edit: form kredensial (field secret berupa `••••last4`; mengosongkan = tidak diubah), **Test connection** (modal: tujuan test → hasil real), toggle default, tampilan **Webhook URL** untuk disalin ke provider.

### 5.7 Logs
DataGrid (§10) + drawer detail. Show page: tab Overview (status timeline), Rendered, Attempts (tabel `delivery_attempts`), Raw trace (JSON redacted, `<x-code-block>`).

### 5.8 Settings
- **API Keys:** daftar (name, prefix…last4, env, last used, created by), **Create** → modal menampilkan key penuh **sekali** dengan tombol Copy + peringatan "tidak akan ditampilkan lagi"; Revoke dengan konfirmasi.
- **Webhooks:** URL, events (checkbox), secret (reveal sekali), status, tabel deliveries terakhir + Replay.
- **Team:** daftar member, ubah role (dropdown), invite via email, hapus; owner terakhir terkunci.
- **Usage:** grafik harian bulan ini, tabel per-env, plan saat ini, batas.
- **Security:** ganti password, aktifkan 2FA (QR + kode recovery), sesi aktif.
- **Audit:** tabel audit_logs, filter aksi/user.

### 5.9 Onboarding (pasca-register)
Checklist 4 langkah di dashboard: (1) Hubungkan 1 channel, (2) Buat workflow dari template, (3) Buat API key, (4) Kirim trigger pertama (menampilkan curl siap-salin dengan key placeholder). Checklist hilang setelah selesai.

---

## 6. KOMPONEN ALPINE & STATE

`resources/js/app.js`:
```javascript
import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import { workflowEditor } from './components/workflow-editor';
import { templateEditor } from './components/template-editor';
import { logTable } from './components/log-table';

Alpine.plugin(collapse); Alpine.plugin(focus);

Alpine.store('toast', {
  items: [],
  push(type, message) { const id = crypto.randomUUID(); this.items.push({ id, type, message }); setTimeout(() => this.remove(id), 5000); },
  remove(id) { this.items = this.items.filter(i => i.id !== id); },
});

Alpine.data('workflowEditor', workflowEditor);
Alpine.data('templateEditor', templateEditor);
Alpine.data('logTable', logTable);

window.Alpine = Alpine;
Alpine.start();
```
Aturan: state server (environment aktif, role) **dari Blade** (`@js(...)`), bukan localStorage. localStorage hanya untuk preferensi UI (tema tabel, rentang grafik). Semua request `fetch` lewat helper `api()` yang menyisipkan CSRF (`X-CSRF-TOKEN`), `Accept: application/json`, menangani 401 (redirect login), 403/422/429/5xx → toast.

---

## 7. WORKFLOW EDITOR

Linear step list (sesuai D5), bukan canvas bebas. Tiap kartu step: handle drag (urut ulang via HTML5 drag atau tombol ↑↓ untuk aksesibilitas), tipe, nama, ringkasan, tombol hapus, indikator error validasi.

Tipe step & field:
| Tipe | Field |
|---|---|
| CHANNEL | channel, provider (default / pilih), template (subject/body per locale), fallback step (dropdown step lain bertipe channel), kritis (centang) |
| DELAY | durasi + satuan (detik/menit/jam/hari), maks 30 hari |
| FILTER | daftar rule: path (autocomplete dari sample payload), operator, nilai; semua AND |
Batas 20 step; validasi client (+ ulang di server): tidak ada step CHANNEL tanpa body, fallback tidak boleh menunjuk dirinya, FILTER tidak boleh step terakhir.

```javascript
// resources/js/components/workflow-editor.js
export const workflowEditor = ({ workflow, steps, saveUrl, publishUrl }) => ({
  workflow, steps, selected: null, dirty: false, saving: false, errors: {},
  add(type, channel = null) {
    if (this.steps.length >= 20) return Alpine.store('toast').push('warn', 'Maksimal 20 step');
    const s = { uid: crypto.randomUUID(), type, channel, name: type === 'CHANNEL' ? `Kirim ${channel}` : type,
                config: type === 'DELAY' ? { seconds: 60 } : type === 'FILTER' ? { rules: [] } : {},
                template: { subject: '', body: '' }, fallback_uid: null };
    this.steps.push(s); this.selected = s.uid; this.dirty = true;
  },
  remove(uid) { this.steps = this.steps.filter(s => s.uid !== uid).map(s => s.fallback_uid === uid ? { ...s, fallback_uid: null } : s); this.dirty = true; },
  move(from, to) { if (to < 0 || to >= this.steps.length) return; const [s] = this.steps.splice(from, 1); this.steps.splice(to, 0, s); this.dirty = true; },
  async save() { /* PUT saveUrl, tangkap 422 → this.errors[uid][field] */ },
  async publish() { /* POST publishUrl setelah save */ },
  init() { window.addEventListener('beforeunload', e => { if (this.dirty) { e.preventDefault(); e.returnValue = ''; } }); },
});
```
Markup: gunakan `<template x-for="(step, i) in steps" :key="step.uid">` (**bukan** `Date.now()` sebagai id — bisa bentrok); konektor vertikal antar kartu; tombol tambah step di antara kartu.

---

## 8. TEMPLATE EDITOR

Split view: kiri textarea mono (subject + body, tab per locale), kanan **Live Preview** (render server: `POST /console/api/templates/preview` dengan debounce 400 ms, memakai `TemplateCompiler` yang sama dengan produksi sehingga hasil identik). Bantuan: panel "Variables" dari sample payload (klik untuk menyisipkan `{{ payload.x }}`), daftar filter, penghitung karakter (WhatsApp ±4096, SMS 160/70 ≥ fase 2), peringatan variabel tak terdefinisi. Email: tab Text/HTML, preview HTML di `<iframe sandbox>` tanpa script.

---

## 9. INBOX WIDGET

Dua bentuk: (a) Blade component `<x-notifora-inbox>` untuk aplikasi Laravel; (b) bundle JS mandiri `notifora-inbox.js` (Web Component `<notifora-inbox>`) untuk situs apa pun — dibuat di fase M2.

**Prinsip keamanan:** widget **tidak** menerima `subscriberId` mentah dari browser. Server klien memanggil `POST /v1/subscribers/{id}/inbox-token` (pakai API key, di sisi server) lalu meneruskan **token JWT** ke widget.

```html
{{-- resources/views/components/notifora-inbox.blade.php  props: token, socketUrl, apiUrl, subscriberId --}}
@props(['token', 'socketUrl', 'apiUrl', 'subscriberId'])
<div x-data="notiforaInbox({ token: @js($token), socketUrl: @js($socketUrl), apiUrl: @js($apiUrl), subscriberId: @js($subscriberId) })"
     x-init="connect()" @click.outside="open = false" class="relative">
  <button @click="open = !open" class="relative p-2 text-zinc-300 hover:text-white" aria-label="Notifications" :aria-expanded="open">
    <x-icon name="bell" class="w-6 h-6"/>
    <span x-show="unread > 0" x-text="unread > 99 ? '99+' : unread" class="absolute top-0 right-0 px-1.5 text-[10px] font-mono font-bold bg-cobalt text-white rounded-full"></span>
  </button>
  <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-80 max-h-[28rem] overflow-y-auto bg-surface-2 border border-line rounded-xl shadow-2xl z-50">
    <div class="flex items-center justify-between px-4 py-2 border-b border-line">
      <span class="text-sm font-semibold">Notifications</span>
      <button @click="markAll()" class="text-xs text-cobalt hover:underline">Mark all read</button>
    </div>
    <template x-for="m in messages" :key="m.id">
      <a :href="m.action_url || '#'" @click="markRead(m)" class="block px-4 py-3 border-b border-line/60 hover:bg-white/5" :class="m.read_at ? 'opacity-60' : ''">
        <div class="text-sm font-medium" x-text="m.title"></div>
        <div class="text-xs text-zinc-400 line-clamp-2" x-text="m.body"></div>
        <div class="text-[10px] font-mono text-zinc-500 mt-1" x-text="timeAgo(m.created_at)"></div>
      </a>
    </template>
    <div x-show="!messages.length" class="px-4 py-8 text-center text-sm text-zinc-500">No notifications yet</div>
  </div>
</div>
```
Perilaku `notiforaInbox`: `connect()` → `io(socketUrl, { auth: { token }, transports: ['websocket'] })`; handler `unread:count`, `notification:received` (unshift + toast opsional + `unread++`), `notification:read`, `auth:expired` (panggil `window.dispatchEvent('notifora:token-expired')` agar host mengambil token baru lalu `reconnect(newToken)`); `connect_error` → fallback polling `GET {apiUrl}/inbox` tiap 30 dtk; `loadMore()` cursor; semua teks dirender dengan `x-text` (tidak pernah `x-html`) untuk mencegah XSS dari payload.

---

## 10. LOGS & TELEMETRY

`logTable` Alpine: filter (status multi, channel, workflow, subscriber, rentang waktu preset 1j/24j/7h/custom), pencarian `external_message_id`, cursor pagination, auto-refresh toggle (polling 5 dtk, jeda saat tab hidden), baris klik → drawer detail (fetch `/logs/{id}` partial). Kolom: waktu (relatif + tooltip absolut), workflow, channel (ikon), destination (masked), status-badge, attempts, durasi. Ekspor CSV (≤ 10.000 baris, dibuat job, tautan unduh via notifikasi in-app). Tabel selalu dibungkus `overflow-x-auto`; sel teks panjang `truncate` + `title`.

---

## 11. AKSESIBILITAS, RESPONSIF, PERFORMA

- Breakpoint: mobile-first; console fungsional ≥ 360 px (sidebar jadi drawer, tabel scroll horizontal). Tidak ada horizontal overflow pada `body`.
- Keyboard: semua modal/drawer memakai `x-trap` (focus plugin), `Esc` menutup; fokus terlihat (`focus-visible:ring-2 ring-cobalt`); drag step punya alternatif tombol.
- ARIA: toast `role="status"` (`alert` untuk error), badge status berteks, `aria-live="polite"` untuk counter inbox.
- `prefers-reduced-motion`: matikan parallax, animasi stroke SVG, spray canvas auto-hide, `animate-ping/pulse`.
- Marketing: skor Lighthouse target Performance ≥ 85 mobile; filter SVG berat (`feTurbulence`) hanya pada elemen dekoratif dan dinonaktifkan di perangkat low-end / reduced motion; font di-preload; gambar/SVG besar lazy.
- Console: JS awal ≤ 150 KB gzip (tanpa Chart.js/socket.io); halaman server-rendered pertama < 1,5 dtk LCP.
- CSP (production): `default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; connect-src 'self' wss://ws.notifora.my.id https://api.notifora.my.id; img-src 'self' data:`. Alpine standar memerlukan `unsafe-eval`; build `@alpinejs/csp` melarang ekspresi inline kompleks, sehingga **keputusan 1.0:** pakai Alpine standar dan CSP `script-src 'self' 'unsafe-eval'`. Migrasi ke build CSP dicatat sebagai hardening pasca-1.0.
- i18n: console Bahasa Indonesia + Inggris via `lang/{id,en}`; default `id`. Marketing: Inggris (+ aksen JP).

---

## 12. TESTING FRONTEND
- **Feature test Blade** (Pest): tiap halaman render 200 untuk role yang berhak, 403 untuk yang tidak; komponen kritikal ter-snapshot (`status-badge`, `notifora-inbox`).
- **Playwright E2E:** register → onboarding → connect provider (fake) → buat & publish workflow → test trigger → log muncul; revoke API key; switch environment (banner PROD); inbox menerima pesan real-time (socket-engine lokal).
- **Visual/regresi:** screenshot Playwright untuk landing & auth di 375/768/1440 px.
- **a11y:** `axe-core` via Playwright pada halaman utama; nol pelanggaran serius.
- **Lint:** ESLint + Prettier (+ `prettier-plugin-tailwindcss`), `npm run build` bebas warning.

## 13. ATURAN UNTUK DEVELOPER / AI AGENT
1. Console = Blade + Alpine saja. Tidak ada React/Next/Vue di repo aplikasi (prototype di `templates/` hanya referensi visual).
2. Tailwind hanya lewat Vite plugin (tanpa `cdn.tailwindcss.com`); font self-host.
3. Jangan simpan secret/token di localStorage; jangan gunakan `x-html` untuk data pengguna/payload.
4. Semua tabel `overflow-x-auto`, teks panjang `truncate`; setiap halaman data punya state loading/empty/error.
5. Setiap form mengirim CSRF dan menampilkan error validasi server (422) per-field.
6. Perubahan token desain dilakukan di `@theme` — jangan hardcode hex di komponen.
7. Klaim performa/angka di UI marketing harus konsisten dengan PRD §12.
