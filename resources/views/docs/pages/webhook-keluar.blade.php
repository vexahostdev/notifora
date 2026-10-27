<x-docs.article id="event-payload" title="Event & Payload">
  <p>Berlangganan di Console → <em>Settings → Webhooks</em>: isi URL <strong>https</strong>, pilih event, lalu salin <em>signing secret</em> (hanya tampil sekali).</p>
  <table>
    <thead><tr><th>Event</th><th>Dikirim saat</th></tr></thead>
    <tbody>
      <tr><td><code>message.sent</code></td><td>Pesan diterima provider channel</td></tr>
      <tr><td><code>message.delivered</code></td><td>Pesan sampai ke penerima</td></tr>
      <tr><td><code>message.read</code></td><td>Pesan dibaca</td></tr>
      <tr><td><code>message.failed</code></td><td>Pengiriman gagal permanen</td></tr>
      <tr><td><code>execution.completed</code></td><td>Workflow selesai (<code>COMPLETED</code> atau <code>PARTIAL</code>)</td></tr>
      <tr><td><code>execution.failed</code></td><td>Semua pengiriman gagal</td></tr>
    </tbody>
  </table>
  <x-docs.code lang="json" title="Body">
@verbatim
{
  "id": "evt_k3p1q9z8m2x7c4v6b0n5",
  "type": "message.failed",
  "created_at": "2026-10-27T10:31:31+00:00",
  "data": { "message_id": "01m5…", "execution_id": "01m5…", "channel": "WHATSAPP", "status": "FAILED", "error_code": "http_400" }
}
@endverbatim
  </x-docs.code>
  <p>Header: <code>X-Notifora-Event</code>, <code>X-Notifora-Delivery</code> (ID pengiriman, berguna sebagai kunci dedup), <code>X-Notifora-Signature</code>. Respons dengan status <strong>2xx</strong> dalam 10 detik dianggap sukses. Redirect tidak diikuti.</p>
</x-docs.article>

<x-docs.article id="verifikasi-signature" title="Verifikasi Signature">
  <p>Header <code>X-Notifora-Signature: t=&lt;unix&gt;,v1=&lt;hex&gt;</code> berisi HMAC-SHA256 dari string <code>"{t}.{body mentah}"</code> memakai signing secret Anda. Verifikasi <strong>body mentah</strong> (sebelum di-parse) dan tolak timestamp lebih dari 5 menit.</p>
  <x-docs.tabs>
    <x-docs.code tab="PHP" lang="php">
@verbatim
function verifyNotifora(string $rawBody, string $header, string $secret, int $tolerance = 300): bool
{
    parse_str(str_replace(',', '&', $header), $p);          // t=...&v1=...
    if (empty($p['t']) || empty($p['v1']) || abs(time() - (int) $p['t']) > $tolerance) {
        return false;
    }
    $expected = hash_hmac('sha256', $p['t'].'.'.$rawBody, $secret);
    return hash_equals($expected, $p['v1']);
}

// Laravel: $request->getContent() adalah body mentah
abort_unless(verifyNotifora($request->getContent(), $request->header('X-Notifora-Signature', ''), config('services.notifora.webhook_secret')), 401);
@endverbatim
    </x-docs.code>
    <x-docs.code tab="Node.js" lang="javascript">
@verbatim
import crypto from 'node:crypto';

export function verifyNotifora(rawBody, header, secret, tolerance = 300) {
  const p = Object.fromEntries(header.split(',').map((kv) => kv.split('=')));
  if (!p.t || !p.v1 || Math.abs(Date.now() / 1000 - Number(p.t)) > tolerance) return false;
  const expected = crypto.createHmac('sha256', secret).update(`${p.t}.${rawBody}`).digest('hex');
  const a = Buffer.from(expected), b = Buffer.from(p.v1);
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

// Express: gunakan express.raw({ type: 'application/json' }) agar req.body adalah Buffer mentah
@endverbatim
    </x-docs.code>
    <x-docs.code tab="Python" lang="python">
@verbatim
import hmac, hashlib, time

def verify_notifora(raw_body: bytes, header: str, secret: str, tolerance: int = 300) -> bool:
    p = dict(kv.split("=", 1) for kv in header.split(","))
    if "t" not in p or "v1" not in p or abs(time.time() - int(p["t"])) > tolerance:
        return False
    expected = hmac.new(secret.encode(), f"{p['t']}.".encode() + raw_body, hashlib.sha256).hexdigest()
    return hmac.compare_digest(expected, p["v1"])
@endverbatim
    </x-docs.code>
  </x-docs.tabs>
  <x-docs.callout type="warn">Selalu bandingkan dengan fungsi waktu-konstan (<code>hash_equals</code>, <code>timingSafeEqual</code>, <code>compare_digest</code>) dan buat endpoint Anda idempoten: callback yang sama bisa terkirim lebih dari sekali (gunakan <code>X-Notifora-Delivery</code>).</x-docs.callout>
</x-docs.article>

<x-docs.article id="retry-disable" title="Retry & Penonaktifan Otomatis">
  <p>Jika endpoint Anda tidak membalas 2xx, pengiriman diulang dengan jadwal: <strong>1 menit, 5 menit, 30 menit, 2 jam, 6 jam, 24 jam</strong>. Setelah itu status pengiriman menjadi <code>failed</code>.</p>
  <ul>
    <li>Bila gagal beruntun mencapai <strong>50</strong>, webhook <em>dinonaktifkan otomatis</em> dan admin workspace menerima email.</li>
    <li>Aktifkan kembali dan kirim ulang pengiriman tertentu dari Console → <em>Settings → Webhooks</em> (tombol “Kirim ulang”).</li>
    <li>Keamanan: URL harus <strong>https</strong>. Host yang mengarah ke alamat privat/loopback/metadata ditolak (proteksi SSRF) dan DNS di-pin saat pengiriman.</li>
  </ul>
</x-docs.article>
