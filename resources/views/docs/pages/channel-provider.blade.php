<x-docs.article id="whatsapp" title="WhatsApp (VexaHost)">
  <p>Terhubung ke <strong>VexaHost WA Gateway</strong>. Siapkan sesi WhatsApp di gateway, lalu isi di Console → <em>Channels → WhatsApp</em>.</p>
  <table>
    <thead><tr><th>Field</th><th>Isi</th></tr></thead>
    <tbody>
      <tr><td>Endpoint URL</td><td>URL gateway, mis. <code>https://wa.vexahostcloud.my.id</code></td></tr>
      <tr><td>API Key</td><td>Token gateway (disimpan terenkripsi, tidak pernah ditampilkan ulang)</td></tr>
      <tr><td>Session ID</td><td>ID sesi WhatsApp yang sudah terhubung</td></tr>
    </tbody>
  </table>
  <ul>
    <li>Nomor tujuan diambil dari <code>to.phone</code> subscriber, dinormalisasi ke format internasional tanpa <code>+</code> (mis. <code>6281234567890</code>).</li>
    <li>Gunakan <strong>Test connection</strong> untuk mengirim pesan uji sungguhan ke nomor Anda.</li>
    <li>Timeout 8 detik; 5xx/429/timeout dicoba ulang, 4xx dianggap permanen.</li>
  </ul>
  <x-docs.callout type="warn">Kirim hanya ke penerima yang telah memberi izin (opt-in) dan patuhi kebijakan WhatsApp agar nomor pengirim tidak diblokir.</x-docs.callout>
</x-docs.article>

<x-docs.article id="email" title="Email (Postivo)">
  <p>Pengiriman email transaksional lewat <strong>Postivo</strong> (REST). Isi di Console → <em>Channels → Email</em>:</p>
  <table>
    <thead><tr><th>Field</th><th>Isi</th></tr></thead>
    <tbody>
      <tr><td>Endpoint URL</td><td>URL Postivo (endpoint kirim: <code>/api/v1/emails/send</code>)</td></tr>
      <tr><td>API Key</td><td>Token Postivo</td></tr>
      <tr><td>From Email / From Name</td><td>Identitas pengirim (domain harus terverifikasi di Postivo)</td></tr>
    </tbody>
  </table>
  <ul>
    <li>Step email wajib punya <strong>subject</strong>. Isi teks wajib; isi HTML opsional (nilai variabel di-escape otomatis).</li>
    <li>Status delivery/baca/bounce masuk lewat <a href="#status-delivery">webhook status</a>.</li>
  </ul>
  <x-docs.callout type="info">Ini adalah email yang dikirim <em>atas nama aplikasi Anda</em> ke pelanggan Anda. Email sistem Notifora sendiri (reset password, undangan tim, invoice) memakai SMTP terpisah — lihat <a href="{{ route('docs.show', 'billing-plan') }}#email-sistem">Email Notifikasi Sistem</a>.</x-docs.callout>
</x-docs.article>

<x-docs.article id="telegram" title="Telegram Bot">
  <ol>
    <li>Buat bot lewat <code>@BotFather</code>, salin <strong>Bot Token</strong>.</li>
    <li>Isi di Console → <em>Channels → Telegram</em>.</li>
    <li>Penerima harus sudah memulai percakapan dengan bot Anda. Simpan <code>chat_id</code>-nya di <code>to.telegramChatId</code>.</li>
  </ol>
  <p>Jika pengguna memblokir bot, pengiriman gagal permanen dengan kode <code>blocked_by_user</code> (tidak dicoba ulang). Batas laju Telegram (429) dicoba ulang otomatis.</p>
</x-docs.article>

<x-docs.article id="in-app" title="In-App Inbox">
  <p>Channel bawaan: tanpa konfigurasi provider. Pesan disimpan di Notifora dan tersedia lewat <a href="{{ route('docs.show', 'referensi-api') }}#inbox-api">Inbox API</a> serta <a href="{{ route('docs.show', 'inbox-realtime') }}">widget real-time</a>. Status pesan langsung <code>DELIVERED</code>.</p>
  <p>Step In-App memakai <em>subject</em> sebagai judul dan <em>isi pesan</em> sebagai badan. Untuk tautan saat diklik, kirim <code>_action_url</code> di <code>payload</code>.</p>
</x-docs.article>

<x-docs.article id="status-delivery" title="Webhook Status Delivery">
  <p>Agar status <code>DELIVERED</code> dan <code>READ</code> tercatat, daftarkan URL webhook provider di dashboard provider. URL unik per provider tampil di Console → <em>Channels → (provider) → Webhook status delivery</em>.</p>
  <x-docs.code lang="text">
@verbatim
POST https://api.notifora.my.id/v1/hooks/{provider}/{token}
   provider = vexahost_wa | postivo
   token    = token rahasia unik per provider (40 karakter)
@endverbatim
  </x-docs.code>
  <p>Payload yang dipahami: <code>messageId</code> (atau <code>id</code>) dan <code>status</code> (<code>sent</code>, <code>delivered</code>, <code>read</code>/<code>opened</code>, <code>failed</code>/<code>bounced</code>).</p>
  <ul>
    <li>Status hanya <strong>maju</strong>; webhook terlambat yang mencoba memundurkan status diabaikan.</li>
    <li>Token yang salah menghasilkan <code>404</code>; jaga kerahasiaan URL ini.</li>
    <li>Setiap perubahan status memicu <a href="{{ route('docs.show', 'webhook-keluar') }}">webhook keluar</a> bila Anda berlangganan.</li>
  </ul>
</x-docs.article>
