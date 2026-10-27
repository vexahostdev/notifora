<x-docs.article id="membuat-workflow" title="Membuat & Mempublish Workflow">
  <ol>
    <li>Console → <em>Workflows</em> → <strong>Workflow baru</strong>. Isi nama, <code>trigger key</code> (huruf kecil, angka, <code>-</code>, <code>_</code>; unik per environment), dan prioritas.</li>
    <li>Tambahkan step di editor, atur isi pesan, dan lihat <em>preview live</em> memakai sample payload.</li>
    <li><strong>Simpan draft</strong> berulang kali dengan aman; draft tidak mempengaruhi trafik.</li>
    <li><strong>Publish</strong> membuat versi baru yang tidak bisa diubah dan mengaktifkan workflow. Publish ditolak dengan daftar masalah bila ada step yang tidak valid.</li>
  </ol>
  <table>
    <thead><tr><th>Status</th><th>Perilaku</th></tr></thead>
    <tbody>
      <tr><td><code>DRAFT</code></td><td>Belum bisa dipicu API (<code>422 workflow_inactive</code>)</td></tr>
      <tr><td><code>ACTIVE</code></td><td>Menerima trigger. Bisa dinonaktifkan/diaktifkan dari daftar workflow.</td></tr>
      <tr><td><code>ARCHIVED</code></td><td>Disembunyikan; tidak bisa dipicu</td></tr>
    </tbody>
  </table>
  <ul>
    <li><strong>Versioning:</strong> eksekusi memakai versi yang berlaku saat event diterima. Mengedit &amp; publish ulang tidak mengubah eksekusi yang sedang berjalan (mis. masih menunggu delay).</li>
    <li><strong>Prioritas HIGH</strong> atau tanda <em>Kritis</em> (mis. OTP) memakai antrean prioritas.</li>
    <li>Batas: maksimal 20 step per workflow.</li>
  </ul>
  <p>Aturan validasi saat publish: minimal satu step <code>CHANNEL</code>; step <code>FILTER</code> tidak boleh terakhir; <code>DELAY</code> 1 detik sampai 30 hari; email wajib punya subject; sintaks template harus valid; fallback harus menunjuk step channel lain.</p>
</x-docs.article>

<x-docs.article id="tipe-step" title="Tipe Step: Channel, Delay, Filter">
  <p>Step dijalankan <strong>berurutan</strong> dari atas ke bawah.</p>
  <h3>CHANNEL — kirim pesan</h3>
  <p>Pilih channel (WhatsApp, Email, Telegram, In-App), provider (default atau pilihan), subject (Email/In-App), isi pesan, dan fallback. Dilewati (<code>SKIPPED</code>) bila subscriber tidak punya tujuan untuk channel itu (<code>no_destination</code>) atau memilih opt-out (<code>opted_out</code>); workflow tetap lanjut ke step berikutnya.</p>
  <h3>DELAY — tunggu</h3>
  <p>Menjeda eksekusi (detik sampai hari, maks. 30 hari) lalu melanjutkannya otomatis. Selama menunggu, eksekusi berstatus <code>WAITING</code> dan bisa dibatalkan lewat <code>DELETE /events/{id}</code>.</p>
  <h3>FILTER — kondisi</h3>
  <p>Daftar rule yang digabung <strong>AND</strong>. Jika ada yang tidak terpenuhi, <em>sisa workflow dihentikan</em> (step ditandai <code>SKIPPED: filter_false</code>) dan eksekusi dianggap selesai normal.</p>
  <table>
    <thead><tr><th>Operator</th><th>Arti</th><th>Contoh nilai</th></tr></thead>
    <tbody>
      <tr><td><code>eq</code> / <code>neq</code></td><td>sama / tidak sama</td><td><code>gold</code></td></tr>
      <tr><td><code>gt</code> <code>gte</code> <code>lt</code> <code>lte</code></td><td>perbandingan angka</td><td><code>1000000</code></td></tr>
      <tr><td><code>in</code></td><td>salah satu dari daftar (pisah koma)</td><td><code>gold,platinum</code></td></tr>
      <tr><td><code>contains</code></td><td>teks mengandung</td><td><code>@contoh.com</code></td></tr>
      <tr><td><code>exists</code></td><td>nilai tidak kosong</td><td>—</td></tr>
    </tbody>
  </table>
  <p>Path memakai awalan <code>payload.</code> atau <code>subscriber.</code>, mis. <code>payload.amount</code> atau <code>subscriber.plan</code>.</p>
</x-docs.article>

<x-docs.article id="fallback" title="Fallback & Retry">
  <p><strong>Retry otomatis.</strong> Kegagalan sementara (timeout, 5xx, 429) dicoba ulang hingga 3 kali dengan jeda bertahap (5 detik, 15 detik, 60 detik). Kegagalan permanen (nomor tidak valid, kredensial salah, 4xx lain) tidak dicoba ulang.</p>
  <p><strong>Fallback.</strong> Pada step channel, pilih <em>Fallback jika gagal</em> ke step channel lain di workflow yang sama. Step fallback <strong>tidak berjalan di alur normal</strong>; ia hanya dijalankan bila step utama gagal permanen, lalu workflow lanjut ke step setelah step utama.</p>
  <x-docs.code lang="text" title="Contoh">
@verbatim
1. WhatsApp   fallback → #3      ← dicoba pertama
2. In-App                       ← selalu berjalan
3. Email                        ← hanya jika WhatsApp gagal permanen
@endverbatim
  </x-docs.code>
  <p>Hasil akhir eksekusi: <code>COMPLETED</code> (semua berhasil atau tergantikan fallback), <code>PARTIAL</code> (sebagian berhasil, sebagian gagal), atau <code>FAILED</code> (semua pengiriman gagal).</p>
  <x-docs.callout type="info">Bila provider gagal berulang, <em>circuit breaker</em> menjeda pengiriman ke provider itu selama 60 detik dan menjadwal ulang pesan tanpa menghabiskan jatah percobaan. Admin menerima email peringatan.</x-docs.callout>
</x-docs.article>

<x-docs.article id="sintaks-template" title="Sintaks Template">
  <p>Template memakai sintaks variabel sederhana yang aman: <strong>tidak ada eksekusi kode</strong>. Teks seperti <code>@php</code> atau <code>system()</code> diperlakukan sebagai teks biasa atau ditolak saat validasi.</p>
  <x-docs.code lang="text">
@verbatim
Halo {{ subscriber.first_name|default:"Pelanggan" }}, pesanan {{ payload.invoiceId }}
sebesar {{ payload.amount|currency:"IDR" }} sudah kami terima pada {{ payload.paid_at|date:"d M Y H:i" }}.
@endverbatim
  </x-docs.code>
  <table>
    <thead><tr><th>Root</th><th>Isi</th></tr></thead>
    <tbody>
      <tr><td><code>payload</code></td><td>Objek <code>payload</code> dari trigger (akses bersarang: <code>payload.items.0.name</code>)</td></tr>
      <tr><td><code>subscriber</code></td><td><code>first_name</code>, <code>last_name</code>, <code>full_name</code>, <code>email</code>, <code>phone</code>, <code>locale</code>, <code>timezone</code>, <code>id</code>, serta semua field di <code>data</code></td></tr>
      <tr><td><code>event</code></td><td><code>id</code>, <code>created_at</code></td></tr>
      <tr><td><code>workspace</code></td><td><code>name</code></td></tr>
    </tbody>
  </table>
  <h3>Filter</h3>
  <table>
    <thead><tr><th>Filter</th><th>Fungsi</th><th>Contoh → hasil</th></tr></thead>
    <tbody>
      <tr><td><code>upper</code> <code>lower</code> <code>title</code></td><td>Ubah huruf</td><td><code>budi|upper</code> → BUDI</td></tr>
      <tr><td><code>trim</code></td><td>Hapus spasi tepi</td><td></td></tr>
      <tr><td><code>default:"x"</code></td><td>Nilai cadangan jika kosong</td><td><code>default:"-"</code></td></tr>
      <tr><td><code>truncate:N</code></td><td>Potong dan tambah …</td><td><code>truncate:20</code></td></tr>
      <tr><td><code>currency:"IDR"</code></td><td>Format mata uang</td><td>450000 → Rp 450.000</td></tr>
      <tr><td><code>number:N</code></td><td>Format angka, N desimal</td><td><code>number:2</code></td></tr>
      <tr><td><code>date:"format"</code></td><td>Format tanggal di zona waktu subscriber</td><td><code>date:"d M Y"</code></td></tr>
      <tr><td><code>e</code></td><td>Escape HTML</td><td></td></tr>
      <tr><td><code>json</code></td><td>Serialisasi JSON</td><td></td></tr>
    </tbody>
  </table>
  <ul>
    <li>Variabel yang tidak ditemukan dirender kosong (peringatan muncul di preview editor).</li>
    <li>Konten HTML email di-escape otomatis. Output dibatasi 100 KB; template maksimum 20.000 karakter.</li>
    <li>Sintaks yang rusak (mis. <code>@{{</code> tanpa penutup) ditolak saat <strong>Publish</strong>.</li>
  </ul>
</x-docs.article>

<x-docs.article id="override" title="Override Template per Trigger">
  <p>Ganti subject atau isi untuk satu trigger tanpa mengubah workflow, lewat <code>overrides.channels.&lt;channel&gt;</code> (nama channel huruf kecil: <code>whatsapp</code>, <code>email</code>, <code>telegram</code>, <code>in_app</code>). Override dirender dengan engine template yang sama.</p>
  <x-docs.code lang="json">
@verbatim
{
  "workflow": "order-confirmed",
  "to": "usr_102",
  "payload": { "invoiceId": "INV-9901" },
  "overrides": {
    "channels": {
      "email": { "subject": "Pesanan {{ payload.invoiceId }} siap", "body": "Teks khusus untuk pesanan ini." },
      "whatsapp": { "body": "Pesanan {{ payload.invoiceId }} dikonfirmasi ✅" }
    }
  }
}
@endverbatim
  </x-docs.code>
</x-docs.article>

<x-docs.article id="preferensi-optout" title="Preferensi & Opt-out">
  <p>Sebelum mengirim, Notifora memeriksa preferensi subscriber untuk channel tersebut.</p>
  <ol>
    <li><strong>Opt-out global</strong> (channel dimatikan untuk semua workflow) <em>selalu berlaku</em>, termasuk untuk workflow kritis.</li>
    <li><strong>Workflow kritis</strong> (mis. OTP, tanda <em>Kritis</em> di editor) mengabaikan opt-out per-workflow.</li>
    <li>Selain itu, opt-out per-workflow dihormati; tanpa preferensi, default-nya <em>diizinkan</em>.</li>
  </ol>
  <p>Step yang dilewati karena opt-out berstatus <code>SKIPPED: opted_out</code>. Kelola lewat <a href="{{ route('docs.show', 'referensi-api') }}#preferensi">API preferensi</a> atau halaman subscriber di Console.</p>
</x-docs.article>
