<x-docs.article id="format-respons" title="Format Respons & Kode Error">
  <p>Base URL: <code>{{ config('notifora.api_url') }}/v1</code>. Semua body JSON UTF-8.</p>
  <x-docs.code lang="json" title="Sukses">
@verbatim
{ "data": { }, "meta": { "request_id": "req_8f3a…" } }
@endverbatim
  </x-docs.code>
  <x-docs.code lang="json" title="Error">
@verbatim
{
  "error": {
    "code": "validation_failed",
    "message": "The given data was invalid.",
    "details": { "to.subscriberId": ["The to.subscriber id field is required."] },
    "request_id": "req_zkaannwbmt9j9gmv"
  }
}
@endverbatim
  </x-docs.code>
  <table>
    <thead><tr><th>HTTP</th><th>code</th><th>Arti & tindakan</th></tr></thead>
    <tbody>
      <tr><td>401</td><td><code>unauthenticated</code></td><td>API key hilang, salah, atau dicabut</td></tr>
      <tr><td>402</td><td><code>quota_exceeded</code></td><td>Kuota event bulanan habis; upgrade plan</td></tr>
      <tr><td>403</td><td><code>forbidden</code></td><td>Tidak punya akses</td></tr>
      <tr><td>404</td><td><code>not_found</code></td><td>Workflow, event, atau subscriber tidak ditemukan</td></tr>
      <tr><td>409</td><td><code>idempotency_conflict</code></td><td>Lihat <a href="{{ route('docs.show', 'autentikasi') }}#idempotency">Idempotency</a></td></tr>
      <tr><td>422</td><td><code>validation_failed</code></td><td>Input tidak valid; lihat <code>details</code></td></tr>
      <tr><td>422</td><td><code>workflow_inactive</code></td><td>Workflow belum dipublish / dinonaktifkan</td></tr>
      <tr><td>429</td><td><code>rate_limited</code></td><td>Tunggu sesuai <code>Retry-After</code></td></tr>
      <tr><td>5xx</td><td><code>server_error</code></td><td>Coba lagi dengan backoff; sertakan <code>request_id</code> saat menghubungi support</td></tr>
    </tbody>
  </table>
  <p>Header yang selalu ada: <code>X-Request-Id</code>, <code>X-RateLimit-*</code>. Paginasi memakai cursor: <code>?limit=50&amp;cursor=…</code> (maks. 100); cursor berikutnya ada di <code>meta.next_cursor</code>.</p>
</x-docs.article>

<x-docs.article id="trigger" title="POST /events/trigger">
  <x-docs.endpoint method="POST" path="/v1/events/trigger" />
  <p>Memicu workflow untuk satu penerima. Event disimpan durable sebelum respons, lalu diproses di background.</p>
  <table>
    <thead><tr><th>Field</th><th>Tipe</th><th>Wajib</th><th>Keterangan</th></tr></thead>
    <tbody>
      <tr><td><code>workflow</code></td><td>string</td><td>Ya</td><td><code>trigger key</code> workflow yang sudah aktif</td></tr>
      <tr><td><code>to</code></td><td>string | object</td><td>Ya</td><td>String = <code>subscriberId</code>. Objek: <code>subscriberId</code> (wajib), <code>email</code>, <code>phone</code>, <code>telegramChatId</code>, <code>firstName</code>, <code>lastName</code>, <code>locale</code>, <code>timezone</code>, <code>data</code>. Hanya field yang dikirim yang diperbarui.</td></tr>
      <tr><td><code>payload</code></td><td>object</td><td>Tidak</td><td>Data untuk template (<code>@{{ payload.* }}</code>). Maks. 64 KB.</td></tr>
      <tr><td><code>overrides</code></td><td>object</td><td>Tidak</td><td>Ganti subject/isi per channel; lihat <a href="{{ route('docs.show', 'workflow-template') }}#override">Override Template</a></td></tr>
    </tbody>
  </table>
  <x-docs.code lang="json" title="Request">
@verbatim
{
  "workflow": "order-confirmed",
  "to": {
    "subscriberId": "usr_102",
    "email": "budi@contoh.com",
    "phone": "0812-3456-7890",
    "telegramChatId": "123456789",
    "firstName": "Budi",
    "locale": "id",
    "timezone": "Asia/Jakarta",
    "data": { "plan": "gold" }
  },
  "payload": { "invoiceId": "INV-9901", "amount": 450000 }
}
@endverbatim
  </x-docs.code>
  <x-docs.code lang="json" title="202 Accepted">
@verbatim
{ "data": { "event_id": "01m5y…", "status": "QUEUED", "workflow": "order-confirmed" }, "meta": { "request_id": "req_…" } }
@endverbatim
  </x-docs.code>
  <ul>
    <li>Nomor telepon dinormalisasi ke E.164 (<code>0812-3456-7890</code> → <code>+6281234567890</code>).</li>
    <li>Subscriber dibuat otomatis bila belum ada, dan diperbarui bila sudah.</li>
    <li>Body maksimum 256 KB. Sertakan <code>X-Idempotency-Key</code> pada pemanggilan yang bisa di-retry.</li>
  </ul>
</x-docs.article>

<x-docs.article id="bulk" title="POST /events/bulk">
  <x-docs.endpoint method="POST" path="/v1/events/bulk" />
  <p>Hingga <strong>100</strong> trigger dalam satu request. Setiap item divalidasi sendiri; satu item gagal tidak membatalkan yang lain.</p>
  <x-docs.code lang="json" title="Request">
@verbatim
{ "events": [
  { "workflow": "order-confirmed", "to": "usr_1", "payload": { "invoiceId": "A" } },
  { "workflow": "order-confirmed", "to": "usr_2", "payload": { "invoiceId": "B" } }
] }
@endverbatim
  </x-docs.code>
  <x-docs.code lang="json" title="202 — hasil per item">
@verbatim
{ "data": [
  { "index": 0, "status": "QUEUED", "event_id": "01m5y…" },
  { "index": 1, "status": "REJECTED", "error": { "code": "workflow_inactive", "message": "…" } }
] }
@endverbatim
  </x-docs.code>
</x-docs.article>

<x-docs.article id="status-event" title="GET & DELETE /events/{id}">
  <x-docs.endpoint method="GET" path="/v1/events/{event_id}" />
  <p>Status eksekusi dan ringkasan tiap step.</p>
  <x-docs.code lang="json" title="200 OK">
@verbatim
{ "data": {
  "event_id": "01m5y…", "workflow": "order-confirmed", "status": "COMPLETED",
  "created_at": "2026-10-27T10:31:30+00:00", "finished_at": "2026-10-27T10:31:31+00:00",
  "steps": [
    { "order": 1, "name": "Inbox", "type": "CHANNEL", "status": "COMPLETED", "skip_reason": null, "message_id": "01m5…", "message_status": "DELIVERED" },
    { "order": 2, "name": "WhatsApp", "type": "CHANNEL", "status": "FAILED", "skip_reason": null, "message_id": "01m5…", "message_status": "FAILED" }
  ]
} }
@endverbatim
  </x-docs.code>
  <x-docs.endpoint method="DELETE" path="/v1/events/{event_id}" />
  <p>Membatalkan eksekusi yang belum selesai (mis. masih menunggu <em>delay</em>). Step yang sedang diproses dibiarkan selesai; sisanya dilewati dengan alasan <code>canceled</code>. Eksekusi yang sudah final → <code>422</code>.</p>
</x-docs.article>

<x-docs.article id="subscribers" title="Subscribers">
  <x-docs.endpoint method="PUT" path="/v1/subscribers/{subscriberId}" />
  <p>Membuat atau memperbarui (upsert) penerima. Field opsional: <code>email</code>, <code>phone</code>, <code>telegramChatId</code>, <code>firstName</code>, <code>lastName</code>, <code>locale</code>, <code>timezone</code>, <code>data</code> (digabung dengan data yang ada).</p>
  <x-docs.code lang="bash">
@verbatim
curl -X PUT https://api.notifora.my.id/v1/subscribers/usr_102 \
  -H "Authorization: Bearer $NOTIFORA_KEY" -H "Content-Type: application/json" \
  -d '{"email":"budi@contoh.com","phone":"0812-3456-7890","firstName":"Budi"}'
@endverbatim
  </x-docs.code>
  <x-docs.endpoint method="GET" path="/v1/subscribers/{subscriberId}" />
  <x-docs.endpoint method="DELETE" path="/v1/subscribers/{subscriberId}" />
  <p><code>DELETE</code> menghapus profil, preferensi, pesan inbox, dan event terkait secara permanen (hak penghapusan data). Tidak dapat dibatalkan.</p>
</x-docs.article>

<x-docs.article id="preferensi" title="Preferensi Notifikasi">
  <x-docs.endpoint method="GET" path="/v1/subscribers/{subscriberId}/preferences" />
  <x-docs.endpoint method="PUT" path="/v1/subscribers/{subscriberId}/preferences" />
  <x-docs.code lang="json" title="PUT body">
@verbatim
{ "preferences": [
  { "channel": "WHATSAPP", "enabled": false },                        // global: matikan WhatsApp untuk semua workflow
  { "channel": "EMAIL", "workflow": "promo-weekly", "enabled": false } // hanya untuk workflow ini
] }
@endverbatim
  </x-docs.code>
  <p><code>channel</code>: <code>WHATSAPP</code>, <code>EMAIL</code>, <code>TELEGRAM</code>, <code>IN_APP</code>. Aturan lengkap ada di <a href="{{ route('docs.show', 'workflow-template') }}#preferensi-optout">Preferensi &amp; Opt-out</a>.</p>
</x-docs.article>

<x-docs.article id="inbox-api" title="Inbox API">
  <x-docs.endpoint method="GET" path="/v1/subscribers/{subscriberId}/inbox" />
  <p>Parameter: <code>unread=1</code>, <code>limit</code>, <code>cursor</code>. Respons memuat <code>meta.unread_count</code> dan <code>meta.next_cursor</code>.</p>
  <x-docs.code lang="json">
@verbatim
{ "data": [ { "id": "01m5…", "title": "Pesanan INV-9901 dikonfirmasi", "body": "Halo Budi…", "action_url": null, "data": null, "read_at": null, "created_at": "2026-10-27T10:31:30+00:00" } ],
  "meta": { "next_cursor": null, "unread_count": 1 } }
@endverbatim
  </x-docs.code>
  <x-docs.endpoint method="POST" path="/v1/subscribers/{subscriberId}/inbox/read" />
  <p>Body: <code>{"ids":["01m5…"]}</code> atau <code>{"all":true}</code>. Membalas jumlah ditandai dan unread terbaru, serta menyiarkan perubahan ke semua tab yang terhubung.</p>
  <x-docs.endpoint method="POST" path="/v1/subscribers/{subscriberId}/inbox-token" />
  <p>Menerbitkan token untuk widget real-time. Lihat <a href="{{ route('docs.show', 'inbox-realtime') }}#alur-token">Alur Token Subscriber</a>.</p>
  <p>Agar pesan In-App memiliki tautan, sertakan <code>_action_url</code> (dan <code>_data</code> opsional) di <code>payload</code> trigger.</p>
</x-docs.article>

<x-docs.article id="pesan" title="Log Pesan">
  <x-docs.endpoint method="GET" path="/v1/messages" />
  <p>Filter: <code>status</code> (<code>QUEUED|SENT|DELIVERED|READ|FAILED</code>), <code>channel</code>, <code>subscriberId</code>, <code>limit</code>, <code>cursor</code>.</p>
  <x-docs.endpoint method="GET" path="/v1/messages/{message_id}" />
  <p>Detail satu pengiriman, termasuk riwayat percobaan (<code>attempts[]</code>: nomor, sukses/gagal, HTTP status, durasi).</p>
  <x-docs.callout type="info">Tujuan pesan disimpan dalam bentuk tersamar (mis. <code>+62812****890</code>). Nilai asli tidak disimpan di log.</x-docs.callout>
  <table>
    <thead><tr><th>Status</th><th>Arti</th></tr></thead>
    <tbody>
      <tr><td><code>QUEUED</code></td><td>Menunggu giliran / sedang dicoba</td></tr>
      <tr><td><code>SENT</code></td><td>Diterima provider</td></tr>
      <tr><td><code>DELIVERED</code></td><td>Sampai ke perangkat penerima (dari webhook provider; In-App langsung)</td></tr>
      <tr><td><code>READ</code></td><td>Dibaca penerima</td></tr>
      <tr><td><code>FAILED</code></td><td>Gagal permanen; lihat <code>error_code</code></td></tr>
    </tbody>
  </table>
  <p>Status hanya bergerak maju (<code>QUEUED → SENT → DELIVERED → READ</code>); webhook terlambat tidak memundurkan status.</p>
</x-docs.article>

<x-docs.article id="daftar-workflow" title="GET /workflows">
  <x-docs.endpoint method="GET" path="/v1/workflows" />
  <p>Daftar workflow berstatus aktif beserta <code>trigger_key</code>, prioritas, versi, dan channel yang dipakai. Berguna untuk memvalidasi konfigurasi dari sisi aplikasi Anda. Pembuatan/penyuntingan workflow dilakukan di Console.</p>
</x-docs.article>
