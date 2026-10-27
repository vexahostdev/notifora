<x-docs.article id="plan-kuota" title="Plan & Kuota">
  <table>
    <thead><tr><th>Plan</th><th>Harga / bulan</th><th>Event / bulan</th><th>Retensi log</th><th>Rate limit</th></tr></thead>
    <tbody>
      <tr><td>Free</td><td>Rp 0</td><td>{{ number_format(config('notifora.free_plan.events'), 0, ',', '.') }}</td><td>{{ config('notifora.free_plan.retention_days') }} hari</td><td>{{ config('notifora.free_plan.rpm') }} req/menit</td></tr>
      @foreach (\App\Services\PlanCatalog::purchasable() as $p)
      <tr><td><strong>{{ $p['label'] }}</strong></td><td>Rp {{ number_format($p['price'], 0, ',', '.') }}</td><td>{{ number_format($p['events'], 0, ',', '.') }}</td><td>{{ $p['retention_days'] }} hari</td><td>{{ number_format($p['rpm'], 0, ',', '.') }} req/menit</td></tr>
      @endforeach
    </tbody>
  </table>
  <ul>
    <li><strong>1 event = 1 trigger</strong> yang diterima API (bukan per channel atau per step).</li>
    <li>Environment <code>DEVELOPMENT</code> selalu gratis hingga {{ number_format(config('notifora.dev_events_monthly'), 0, ',', '.') }} event/bulan.</li>
    <li>Harga dalam Rupiah. Pemakaian dan plan aktif dilihat di Console → <em>Billing</em> dan <em>Usage</em>.</li>
  </ul>
</x-docs.article>

<x-docs.article id="checkout" title="Checkout & Pembayaran">
  <p>Pembelian plan dilakukan oleh <strong>Owner</strong> workspace di Console → <em>Billing</em>. Pembayaran diproses <strong>Xendit</strong>.</p>
  <ol>
    <li>Pilih plan, lalu <strong>durasi</strong> ({{ implode(', ', config('notifora.billing.periods')) }} bulan) dan <strong>metode</strong> (QRIS, Virtual Account Mandiri/BNI/BRI/CIMB/Permata, atau Indomaret).</li>
    <li>Setujui syarat, klik <strong>Bayar sekarang</strong>. Invoice dibuat (nomor <code>NTF-INV-TAHUN-000001</code>) dan Anda diarahkan ke halaman pembayaran Xendit. Invoice berlaku <strong>24 jam</strong>.</li>
    <li>Setelah dibayar, Xendit mengirim callback; plan <strong>aktif otomatis</strong> dan bukti pembayaran tersedia di Console. Halaman invoice juga memperbarui status sendiri saat Anda kembali dari Xendit.</li>
  </ol>
  <table>
    <thead><tr><th>Situasi</th><th>Perilaku</th></tr></thead>
    <tbody>
      <tr><td>Beli plan yang sama saat masih aktif</td><td><strong>Perpanjangan</strong>: masa berlaku ditambah dari tanggal berakhir saat ini</td></tr>
      <tr><td>Beli plan lebih tinggi saat plan lain masih aktif</td><td><strong>Upgrade</strong>: plan baru aktif sekarang, masa berlaku dihitung dari hari ini (sisa waktu plan lama tidak dikonversi)</td></tr>
      <tr><td>Beli plan lebih rendah saat plan masih aktif</td><td>Ditolak sampai masa berlaku berakhir</td></tr>
      <tr><td>Invoice tidak dibayar dalam 24 jam</td><td>Berstatus <code>EXPIRED</code>; tidak ada dana yang ditagihkan. Buat invoice baru.</td></tr>
    </tbody>
  </table>
  <h3>Keamanan pembayaran</h3>
  <ul>
    <li>Data kartu/akun bayar tidak pernah melewati Notifora; semuanya ditangani halaman Xendit.</li>
    <li>Callback pembayaran diverifikasi: token internal/callback, nominal harus sama persis, dan status dikonfirmasi ulang langsung ke API Xendit sebelum plan diaktifkan.</li>
    <li>Callback ganda tidak menambah masa berlaku dua kali (idempoten).</li>
  </ul>
  <x-docs.callout type="info">Callback Xendit diterima lewat router webhook pusat VexaHost yang meneruskan transaksi berawalan <code>NTF-</code> ke Notifora. Pertanyaan pembayaran: <a href="mailto:{{ config('notifora.support_email') }}">{{ config('notifora.support_email') }}</a>.</x-docs.callout>
</x-docs.article>

<x-docs.article id="masa-berlaku" title="Masa Berlaku & Tenggang">
  <ul>
    <li>Pengingat email dikirim <strong>{{ implode(', ', array_map(fn ($d) => 'H-'.$d, config('notifora.billing.reminder_days'))) }}</strong> sebelum plan berakhir.</li>
    <li>Setelah berakhir, ada <strong>masa tenggang {{ config('notifora.billing.grace_days') }} hari</strong>: batas plan lama masih berlaku, dan Anda menerima email pemberitahuan.</li>
    <li>Lewat masa tenggang, workspace otomatis turun ke plan <strong>Free</strong> ({{ number_format(config('notifora.free_plan.events'), 0, ',', '.') }} event/bulan, {{ config('notifora.free_plan.rpm') }} req/menit, log {{ config('notifora.free_plan.retention_days') }} hari). Data, workflow, dan konfigurasi tetap aman; hanya batas yang berubah.</li>
    <li>Perpanjang kapan saja, termasuk setelah turun ke Free, lewat Console → <em>Billing</em>.</li>
  </ul>
</x-docs.article>

<x-docs.article id="email-sistem" title="Email Notifikasi Sistem">
  <p>Notifora mengirim email sistem dari <code>noreply@notifora.my.id</code> (balasan diarahkan ke <code>support@notifora.my.id</code>) lewat SMTP Brevo. Email dikirim di background; kegagalan SMTP tidak pernah menggagalkan aksi Anda di Console.</p>
  <table>
    <thead><tr><th>Kelompok</th><th>Email</th><th>Penerima</th></tr></thead>
    <tbody>
      <tr><td>Akun</td><td>Selamat datang · Atur ulang password · Password diubah</td><td>Pengguna</td></tr>
      <tr><td>Tim</td><td>Undangan bergabung · Anggota bergabung · Peran diubah · Akses dicabut</td><td>Calon anggota / pengundang / anggota</td></tr>
      <tr><td>Keamanan</td><td>API key dibuat · API key dicabut</td><td>Owner &amp; Admin</td></tr>
      <tr><td>Operasional</td><td>Kuota 80% · Kuota habis · Webhook dinonaktifkan · Provider bermasalah</td><td>Owner &amp; Admin</td></tr>
      <tr><td>Billing</td><td>Invoice dibuat · Pembayaran berhasil · Pembayaran gagal/kedaluwarsa · Plan akan berakhir · Masa tenggang · Turun ke Free</td><td>Owner &amp; Admin</td></tr>
    </tbody>
  </table>
  <p>Email peringatan kuota dikirim sekali per level per bulan, dan peringatan provider maksimal sekali per jam per provider agar tidak membanjiri kotak masuk.</p>
</x-docs.article>
