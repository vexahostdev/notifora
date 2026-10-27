<x-docs.article id="status-eksekusi" title="Status Eksekusi & Step">
  <table>
    <thead><tr><th>Eksekusi</th><th>Arti</th></tr></thead>
    <tbody>
      <tr><td><code>PROCESSING</code></td><td>Sedang berjalan (termasuk menunggu delay)</td></tr>
      <tr><td><code>COMPLETED</code></td><td>Selesai; tidak ada kegagalan yang tidak tertangani fallback</td></tr>
      <tr><td><code>PARTIAL</code></td><td>Sebagian channel berhasil, sebagian gagal</td></tr>
      <tr><td><code>FAILED</code></td><td>Semua pengiriman gagal</td></tr>
      <tr><td><code>CANCELED</code></td><td>Dibatalkan lewat API</td></tr>
    </tbody>
  </table>
  <table>
    <thead><tr><th>Step</th><th>Arti</th></tr></thead>
    <tbody>
      <tr><td><code>QUEUED</code> / <code>PROCESSING</code></td><td>Menunggu / sedang dikirim</td></tr>
      <tr><td><code>WAITING</code></td><td>Menunggu delay berakhir</td></tr>
      <tr><td><code>COMPLETED</code></td><td>Berhasil</td></tr>
      <tr><td><code>FAILED</code></td><td>Gagal permanen (cek <code>error_code</code> di log pesan)</td></tr>
      <tr><td><code>SKIPPED</code></td><td>Dilewati; alasan di <code>skip_reason</code>: <code>opted_out</code>, <code>no_destination</code>, <code>filter_false</code>, <code>not_needed</code> (fallback tidak diperlukan), <code>canceled</code></td></tr>
    </tbody>
  </table>
</x-docs.article>

<x-docs.article id="troubleshooting" title="Troubleshooting">
  <table>
    <thead><tr><th>Gejala</th><th>Penyebab umum & solusi</th></tr></thead>
    <tbody>
      <tr><td><code>401 unauthenticated</code></td><td>Key salah/dicabut, atau memakai key <code>ntf_test_</code> di environment Production (atau sebaliknya). Periksa header <code>Authorization: Bearer …</code>.</td></tr>
      <tr><td><code>422 workflow_inactive</code></td><td>Workflow belum dipublish atau dinonaktifkan. Publish dari editor.</td></tr>
      <tr><td><code>404 not_found</code> saat trigger</td><td><code>workflow</code> harus berisi <em>trigger key</em>, bukan nama workflow; periksa juga environment key.</td></tr>
      <tr><td><code>402 quota_exceeded</code></td><td>Kuota event habis. Cek Console → Usage, lalu upgrade di Billing.</td></tr>
      <tr><td><code>429 rate_limited</code></td><td>Terlalu banyak request. Hormati <code>Retry-After</code>, gunakan <code>/events/bulk</code>.</td></tr>
      <tr><td>Step <code>SKIPPED: no_destination</code></td><td>Subscriber tidak punya tujuan untuk channel itu (mis. tanpa <code>phone</code>). Kirim field di <code>to</code> atau lewat <code>PUT /subscribers</code>.</td></tr>
      <tr><td>Step <code>SKIPPED: opted_out</code></td><td>Subscriber menonaktifkan channel. Periksa preferensi.</td></tr>
      <tr><td>Log <code>no_provider_configured</code></td><td>Belum ada provider aktif untuk channel di environment ini. Hubungkan di Channels.</td></tr>
      <tr><td>Log <code>circuit_open</code> / provider “degraded”</td><td>Provider gagal berulang; pengiriman dijeda 60 detik dan dijadwal ulang otomatis. Periksa kredensial/endpoint.</td></tr>
      <tr><td>Log <code>connection_error</code></td><td>Provider tidak terjangkau dari server Notifora. Pastikan URL gateway publik dan sesi aktif.</td></tr>
      <tr><td>Status tidak pernah <code>DELIVERED</code></td><td>Webhook status provider belum didaftarkan. Lihat <a href="{{ route('docs.show', 'channel-provider') }}#status-delivery">Webhook Status Delivery</a>.</td></tr>
      <tr><td>Pembayaran sudah sukses tapi plan belum aktif</td><td>Buka halaman invoice di Console (status disinkronkan otomatis). Bila tetap pending, hubungi support dengan nomor invoice.</td></tr>
    </tbody>
  </table>
  <p>Saat menghubungi support, sertakan <code>request_id</code> dari respons error atau <code>event_id</code>/<code>message_id</code> dari log.</p>
</x-docs.article>

<x-docs.article id="keamanan" title="Praktik Keamanan">
  <ul>
    <li><strong>Rahasia:</strong> API key dan signing secret hanya di server. Rotasi dengan membuat key baru, mengganti di aplikasi, lalu mencabut key lama.</li>
    <li><strong>Kredensial provider</strong> disimpan terenkripsi dan tidak pernah ditampilkan ulang; kosongkan field saat edit untuk mempertahankan nilai lama.</li>
    <li><strong>Data pribadi:</strong> tujuan pesan di log disamarkan; <code>DELETE /subscribers/{id}</code> menghapus data penerima. Akses data asli di Console dibatasi Admin dan dicatat di audit log.</li>
    <li><strong>Peran tim:</strong> Owner, Admin, Developer, Viewer dengan hak berbeda; owner terakhir tidak bisa diturunkan atau dihapus.</li>
    <li><strong>Webhook:</strong> verifikasi signature, hanya https, dan buat penanganan idempoten.</li>
    <li><strong>Audit log</strong> mencatat pembuatan/pencabutan key, perubahan provider, peran, dan billing (Console → Settings → Audit).</li>
  </ul>
  <x-docs.callout type="tip">Aktifkan pemberitahuan email (sudah otomatis) dan tinjau halaman API Keys secara berkala untuk menghapus key yang tidak dipakai.</x-docs.callout>
</x-docs.article>
