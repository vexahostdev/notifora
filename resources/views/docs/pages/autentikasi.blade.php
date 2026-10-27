<x-docs.article id="api-key" title="API Key & Header Authorization">
  <p>Semua endpoint <code>/v1</code> (kecuali webhook status provider) memakai API key sebagai Bearer token.</p>
  <x-docs.code lang="http">
@verbatim
Authorization: Bearer ntf_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
@endverbatim
  </x-docs.code>
  <table>
    <thead><tr><th>Awalan</th><th>Environment</th></tr></thead>
    <tbody>
      <tr><td><code>ntf_test_</code></td><td>DEVELOPMENT</td></tr>
      <tr><td><code>ntf_live_</code></td><td>PRODUCTION</td></tr>
    </tbody>
  </table>
  <ul>
    <li>Key dibuat di Console → <em>Settings → API Keys</em> dan <strong>hanya tampil sekali</strong>. Notifora hanya menyimpan hash SHA-256, awalan, dan 4 karakter terakhir.</li>
    <li>Key terikat ke <strong>satu environment</strong>. Key <code>ntf_test_</code> tidak bisa memicu workflow di Production.</li>
    <li>Mencabut key berlaku langsung (HTTP 401 pada panggilan berikutnya).</li>
    <li>Pembuatan dan pencabutan key dicatat di audit log, dan admin workspace menerima email peringatan.</li>
  </ul>
  <x-docs.callout type="danger">Simpan key di secret manager atau variabel environment server. Jangan taruh di kode klien (browser/aplikasi mobile), repositori, atau log. Untuk widget inbox, gunakan <a href="{{ route('docs.show', 'inbox-realtime') }}#alur-token">token subscriber</a>, bukan API key.</x-docs.callout>
</x-docs.article>

<x-docs.article id="environment" title="Environment Development & Production">
  <p>Setiap workspace punya dua environment dengan data terpisah penuh. Gunakan Development untuk integrasi dan pengujian, lalu daftarkan ulang workflow dan provider di Production.</p>
  <table>
    <thead><tr><th></th><th>Development</th><th>Production</th></tr></thead>
    <tbody>
      <tr><td>Kuota event</td><td>1.000 / bulan (gratis)</td><td>Mengikuti plan</td></tr>
      <tr><td>Awalan API key</td><td><code>ntf_test_</code></td><td><code>ntf_live_</code></td></tr>
      <tr><td>Pengalih di Console</td><td colspan="2">Dropdown di bar atas; Production ditandai banner merah</td></tr>
    </tbody>
  </table>
  <x-docs.callout type="tip">Console memiliki <em>simulator</em> workflow dengan mode dry-run, aman dijalankan di environment mana pun.</x-docs.callout>
</x-docs.article>

<x-docs.article id="rate-limit" title="Rate Limit & Kuota Event">
  <p>Dua batas berbeda berlaku: <strong>rate limit</strong> (request per menit per API key) dan <strong>kuota event</strong> (jumlah trigger per bulan per environment).</p>
  <table>
    <thead><tr><th>Plan</th><th>Rate limit</th><th>Kuota event / bulan</th></tr></thead>
    <tbody>
      @foreach (\App\Services\PlanCatalog::purchasable() as $p)
      <tr><td>{{ $p['label'] }}</td><td>{{ number_format($p['rpm'], 0, ',', '.') }} req/menit</td><td>{{ number_format($p['events'], 0, ',', '.') }}</td></tr>
      @endforeach
      <tr><td>Free</td><td>{{ number_format(config('notifora.free_plan.rpm'), 0, ',', '.') }} req/menit</td><td>{{ number_format(config('notifora.free_plan.events'), 0, ',', '.') }}</td></tr>
    </tbody>
  </table>
  <p>Setiap respons memuat header:</p>
  <x-docs.code lang="http">
@verbatim
X-RateLimit-Limit: 600
X-RateLimit-Remaining: 598
X-RateLimit-Reset: 1793097600     # epoch detik
@endverbatim
  </x-docs.code>
  <ul>
    <li>Melewati rate limit → <code>429 rate_limited</code> dengan header <code>Retry-After</code> (detik). Coba lagi dengan exponential backoff.</li>
    <li>Kuota event: peringatan email di 80% dan 100%. Setelah 100% ada toleransi 10%; setelahnya event baru ditolak dengan <code>402 quota_exceeded</code> sampai bulan berikutnya atau plan di-upgrade.</li>
    <li>Pemakaian aktual: Console → <em>Settings → Usage</em>.</li>
  </ul>
</x-docs.article>

<x-docs.article id="idempotency" title="Idempotency Key">
  <p>Kirim header <code>X-Idempotency-Key</code> (maks. 191 karakter) pada <code>POST /events/trigger</code> agar retry jaringan tidak mengirim notifikasi dua kali. Berlaku 24 jam per environment.</p>
  <table>
    <thead><tr><th>Situasi</th><th>Hasil</th></tr></thead>
    <tbody>
      <tr><td>Key baru</td><td>Diproses normal (<code>202</code>)</td></tr>
      <tr><td>Key sama, body sama, sudah selesai</td><td>Respons asli diputar ulang + header <code>Idempotent-Replay: true</code></td></tr>
      <tr><td>Key sama, body <em>berbeda</em></td><td><code>409 idempotency_conflict</code></td></tr>
      <tr><td>Key sama, request pertama masih berjalan</td><td><code>409 idempotency_conflict</code> + <code>Retry-After: 1</code></td></tr>
    </tbody>
  </table>
  <x-docs.callout type="tip">Gunakan ID bisnis yang stabil sebagai key, mis. <code>order-9901</code> atau <code>otp-{userId}-{timestamp}</code>, bukan UUID acak per percobaan.</x-docs.callout>
</x-docs.article>
