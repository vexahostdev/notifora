<x-docs.article id="pengenalan" title="Pengenalan Notifora">
  <p><strong>Notifora</strong> adalah gateway notifikasi untuk developer. Satu API menggantikan banyak SDK: Anda mengirim satu event, Notifora yang menangani template, preferensi penerima, jeda waktu, percobaan ulang, dan pengalihan antar-channel.</p>
  <table>
    <thead><tr><th>Channel</th><th>Provider</th><th>Keterangan</th></tr></thead>
    <tbody>
      <tr><td>WhatsApp</td><td>VexaHost WA Gateway</td><td>Nomor otomatis dinormalisasi ke format 62…</td></tr>
      <tr><td>Email</td><td>Postivo</td><td>Teks dan HTML, subject ber-template</td></tr>
      <tr><td>Telegram</td><td>Telegram Bot API</td><td>Kirim ke <code>chat_id</code> subscriber</td></tr>
      <tr><td>In-App Inbox</td><td>Bawaan</td><td>Bell notifikasi real-time di aplikasi Anda</td></tr>
    </tbody>
  </table>
  <p>Yang Anda dapatkan: respons <code>202 Accepted</code> cepat (event disimpan durable sebelum diproses), retry otomatis dengan backoff, fallback antar-channel, log pengiriman per pesan, dan webhook status ke server Anda.</p>
  <x-docs.callout type="info">Angka kinerja seperti “p95 &lt; 50 ms” merujuk pada <strong>waktu respons ingress API</strong> (menerima dan menyimpan event), bukan waktu pengiriman akhir yang bergantung pada provider channel.</x-docs.callout>
</x-docs.article>

<x-docs.article id="quickstart" title="Quickstart: Kirim Notifikasi Pertama">
  <p>Lima langkah dari akun kosong sampai pesan terkirim.</p>
  <ol>
    <li><strong>Daftar</strong> di <a href="{{ route('register') }}">halaman pendaftaran</a>. Workspace dengan environment <code>DEVELOPMENT</code> dan <code>PRODUCTION</code> dibuat otomatis.</li>
    <li><strong>Hubungkan channel</strong> di Console → <em>Channels</em> (mis. WhatsApp VexaHost). Untuk mencoba tanpa provider, gunakan channel <em>In-App</em> yang selalu aktif.</li>
    <li><strong>Buat workflow</strong> di Console → <em>Workflows</em>: beri nama dan <code>trigger key</code> (mis. <code>order-confirmed</code>), tambahkan step, lalu klik <strong>Publish</strong>.</li>
    <li><strong>Buat API key</strong> di Console → <em>Settings → API Keys</em>. Key hanya ditampilkan sekali.</li>
    <li><strong>Panggil API</strong>:</li>
  </ol>
  <x-docs.tabs>
    <x-docs.code tab="cURL" lang="bash">
@verbatim
curl -X POST https://api.notifora.my.id/v1/events/trigger \
  -H "Authorization: Bearer ntf_test_xxxxxxxx" \
  -H "Content-Type: application/json" \
  -H "X-Idempotency-Key: order-9901" \
  -d '{
    "workflow": "order-confirmed",
    "to": { "subscriberId": "usr_102", "phone": "0812-3456-7890", "email": "budi@contoh.com", "firstName": "Budi" },
    "payload": { "invoiceId": "INV-9901", "amount": 450000 }
  }'
@endverbatim
    </x-docs.code>
    <x-docs.code tab="PHP / Laravel" lang="php">
@verbatim
use Illuminate\Support\Facades\Http;

$res = Http::withToken(config('services.notifora.key'))
    ->withHeaders(['X-Idempotency-Key' => 'order-9901'])
    ->post('https://api.notifora.my.id/v1/events/trigger', [
        'workflow' => 'order-confirmed',
        'to' => ['subscriberId' => 'usr_102', 'phone' => '0812-3456-7890', 'firstName' => 'Budi'],
        'payload' => ['invoiceId' => 'INV-9901', 'amount' => 450000],
    ]);

$res->throw();                       // 4xx/5xx -> exception
$eventId = $res->json('data.event_id');
@endverbatim
    </x-docs.code>
    <x-docs.code tab="Node.js" lang="javascript">
@verbatim
const res = await fetch('https://api.notifora.my.id/v1/events/trigger', {
  method: 'POST',
  headers: {
    Authorization: `Bearer ${process.env.NOTIFORA_KEY}`,
    'Content-Type': 'application/json',
    'X-Idempotency-Key': 'order-9901',
  },
  body: JSON.stringify({
    workflow: 'order-confirmed',
    to: { subscriberId: 'usr_102', phone: '0812-3456-7890', firstName: 'Budi' },
    payload: { invoiceId: 'INV-9901', amount: 450000 },
  }),
});
if (!res.ok) throw new Error((await res.json()).error.message);
const { data } = await res.json(); // data.event_id
@endverbatim
    </x-docs.code>
    <x-docs.code tab="Python" lang="python">
@verbatim
import os, requests

r = requests.post(
    "https://api.notifora.my.id/v1/events/trigger",
    headers={"Authorization": f"Bearer {os.environ['NOTIFORA_KEY']}", "X-Idempotency-Key": "order-9901"},
    json={
        "workflow": "order-confirmed",
        "to": {"subscriberId": "usr_102", "phone": "0812-3456-7890", "firstName": "Budi"},
        "payload": {"invoiceId": "INV-9901", "amount": 450000},
    },
    timeout=10,
)
r.raise_for_status()
event_id = r.json()["data"]["event_id"]
@endverbatim
    </x-docs.code>
  </x-docs.tabs>
  <p>Respons sukses:</p>
  <x-docs.code lang="json" title="202 Accepted">
@verbatim
{
  "data": { "event_id": "01m5yemh4jpwr1caha7c396a9k", "status": "QUEUED", "workflow": "order-confirmed" },
  "meta": { "request_id": "req_mc0vfz44rnzuu4tj" }
}
@endverbatim
  </x-docs.code>
  <p>Lihat hasilnya di Console → <em>Message Logs</em>, atau lewat <a href="{{ route('docs.show', 'referensi-api') }}#status-event">GET /events/{id}</a>.</p>
  <x-docs.callout type="tip">Belum punya provider? Gunakan menu <strong>Workflows → Test</strong> di Console. Mode <em>dry-run</em> merender template dan menampilkan hasil tiap step tanpa mengirim pesan apa pun.</x-docs.callout>
</x-docs.article>

<x-docs.article id="konsep-inti" title="Konsep Inti">
  <table>
    <thead><tr><th>Konsep</th><th>Penjelasan</th></tr></thead>
    <tbody>
      <tr><td><strong>Workspace</strong></td><td>Organisasi/tim Anda. Memiliki anggota (Owner, Admin, Developer, Viewer), plan, dan penagihan.</td></tr>
      <tr><td><strong>Environment</strong></td><td><code>DEVELOPMENT</code> atau <code>PRODUCTION</code>. Subscriber, workflow, provider, dan API key <em>tidak dibagi</em> antar-environment.</td></tr>
      <tr><td><strong>Subscriber</strong></td><td>Penerima notifikasi, dikenali oleh <code>subscriberId</code> milik Anda. Menyimpan email, nomor telepon, chat Telegram, locale, dan data kustom.</td></tr>
      <tr><td><strong>Workflow</strong></td><td>Alur notifikasi berurutan, dipicu oleh <code>trigger key</code>. Dipublish sebagai versi yang tidak bisa berubah.</td></tr>
      <tr><td><strong>Step</strong></td><td><code>CHANNEL</code> (kirim), <code>DELAY</code> (tunggu), atau <code>FILTER</code> (kondisi).</td></tr>
      <tr><td><strong>Event</strong></td><td>Satu panggilan trigger. Satu event menjalankan satu <em>eksekusi</em> workflow. 1 event = 1 hitungan kuota (bukan per channel).</td></tr>
      <tr><td><strong>Eksekusi</strong></td><td>Jalannya workflow untuk satu event: status per step dan satu log per pengiriman channel.</td></tr>
    </tbody>
  </table>
  <h3>Alur satu event</h3>
  <ol>
    <li>API memvalidasi key, rate limit, kuota, dan idempotency, menyimpan event, lalu membalas <code>202</code>.</li>
    <li>Worker menjalankan versi workflow yang <em>berlaku saat event diterima</em> (edit setelahnya tidak mengubah eksekusi yang sedang berjalan).</li>
    <li>Tiap step <code>CHANNEL</code>: cek preferensi subscriber → render template → kirim lewat provider → catat hasil.</li>
    <li>Status delivery lanjutan (Delivered/Read) masuk lewat webhook provider, dan callback ke server Anda dikirim bila Anda berlangganan.</li>
  </ol>
</x-docs.article>
