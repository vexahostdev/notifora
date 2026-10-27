<x-docs.article id="alur-token" title="Alur Token Subscriber">
  <p>Browser pengguna <strong>tidak boleh</strong> memegang API key. Sebagai gantinya, server Anda meminta <em>token subscriber</em> berumur pendek, lalu meneruskannya ke browser.</p>
  <ol>
    <li>Pengguna membuka halaman Anda; server Anda memanggil <code>POST /v1/subscribers/{id}/inbox-token</code> memakai API key.</li>
    <li>Notifora membalas JWT (berlaku 1 jam) bersama <code>socket_url</code>.</li>
    <li>Browser membuka koneksi WebSocket dengan token tersebut. Server socket memverifikasi tanda tangan dan hanya mengizinkan akses ke inbox subscriber itu.</li>
  </ol>
  <x-docs.code lang="bash">
@verbatim
curl -X POST https://api.notifora.my.id/v1/subscribers/usr_102/inbox-token \
  -H "Authorization: Bearer $NOTIFORA_KEY"
# → { "data": { "token": "eyJ…", "expires_in": 3600, "socket_url": "wss://ws.notifora.my.id" } }
@endverbatim
  </x-docs.code>
  <x-docs.callout type="danger">Mengirim <code>subscriberId</code> mentah dari browser ke socket <strong>ditolak</strong>. Hanya token bertanda tangan yang diterima, sehingga pengguna tidak bisa menyadap inbox orang lain.</x-docs.callout>
  <p>Saat token hampir habis, server socket mengirim event <code>auth:expired</code> lalu memutus koneksi; minta token baru dan sambungkan ulang.</p>
</x-docs.article>

<x-docs.article id="widget" title="Widget Inbox">
  <p>Contoh integrasi minimal dengan <code>socket.io-client</code>:</p>
  <x-docs.code lang="javascript">
@verbatim
import { io } from 'socket.io-client';

async function connectInbox() {
  // endpoint milik aplikasi Anda; di dalamnya memanggil POST /v1/subscribers/{id}/inbox-token
  const { token, socket_url } = await (await fetch('/api/notifora/inbox-token')).json();

  const socket = io(socket_url, { auth: { token }, transports: ['websocket'] });

  socket.on('unread:count', ({ unreadCount }) => setBadge(unreadCount));
  socket.on('notification:received', (m) => prependToList(m));        // { id, title, body, action_url, data, created_at }
  socket.on('notification:read', ({ ids }) => markRead(ids));
  socket.on('auth:expired', () => { socket.close(); connectInbox(); }); // ambil token baru

  return {
    markRead: (ids) => socket.emit('notification:mark_read', { ids }),
    markAll: () => socket.emit('notification:mark_read', { all: true }),
  };
}
@endverbatim
  </x-docs.code>
  <ul>
    <li>Render teks pesan dengan <code>textContent</code> / <code>x-text</code>, <strong>jangan</strong> <code>innerHTML</code>, untuk mencegah XSS dari konten notifikasi.</li>
    <li>Jika WebSocket gagal tersambung, gunakan polling <code>GET /inbox</code> setiap ±30 detik sebagai cadangan (lewat server Anda).</li>
    <li>Pesan baru tampil di browser kurang dari ±1 detik setelah dikirim.</li>
  </ul>
</x-docs.article>

<x-docs.article id="event-websocket" title="Event WebSocket">
  <table>
    <thead><tr><th>Event</th><th>Arah</th><th>Payload</th></tr></thead>
    <tbody>
      <tr><td><code>unread:count</code></td><td>server → klien</td><td><code>{ "unreadCount": 3 }</code> (saat terhubung dan tiap perubahan)</td></tr>
      <tr><td><code>notification:received</code></td><td>server → klien</td><td><code>{ id, title, body, action_url, data, read_at, created_at }</code></td></tr>
      <tr><td><code>notification:read</code></td><td>server → klien</td><td><code>{ "ids": ["…"] }</code> (sinkron antar-tab)</td></tr>
      <tr><td><code>auth:expired</code></td><td>server → klien</td><td>Token kedaluwarsa; koneksi akan diputus</td></tr>
      <tr><td><code>notification:mark_read</code></td><td>klien → server</td><td><code>{ "ids": ["…"] }</code> atau <code>{ "all": true }</code></td></tr>
    </tbody>
  </table>
  <p>Database tetap menjadi sumber kebenaran: penandaan dibaca diteruskan server socket ke Notifora, lalu hasilnya disiarkan ke seluruh koneksi subscriber itu.</p>
</x-docs.article>
