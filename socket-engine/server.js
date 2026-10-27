'use strict';

/**
 * Notifora Socket Engine — relay real-time inbox.
 * - Auth: JWT HS256 subscriber (dikeluarkan Laravel: POST /v1/subscribers/{id}/inbox-token)
 * - Room: env:{env}:sub:{subscriberId}
 * - Sumber event: Redis pub/sub channel `notifora:inapp-stream` (dipublish worker Laravel)
 */
const http = require('http');
const { Server } = require('socket.io');
const Redis = require('ioredis');
const jwt = require('jsonwebtoken');

const PORT = Number(process.env.PORT || 8042);
const SECRET = process.env.SOCKET_JWT_SECRET;
const ORIGINS = (process.env.SOCKET_ALLOWED_ORIGINS || '').split(',').map((s) => s.trim()).filter(Boolean);
const LARAVEL = (process.env.LARAVEL_INTERNAL_URL || '').replace(/\/$/, '');
const INTERNAL = process.env.INTERNAL_API_SECRET;
const REDIS_URL = process.env.REDIS_URL || 'redis://127.0.0.1:6379';
const CHANNEL = process.env.INAPP_CHANNEL || 'notifora:inapp-stream';
const MAX_CONN_PER_IP = Number(process.env.MAX_CONN_PER_IP || 50);

if (!SECRET || SECRET.length < 32) fail('SOCKET_JWT_SECRET wajib diisi (>= 32 karakter)');
if (!ORIGINS.length) fail('SOCKET_ALLOWED_ORIGINS wajib diisi (daftar origin dipisah koma)');
if (!LARAVEL || !INTERNAL) fail('LARAVEL_INTERNAL_URL dan INTERNAL_API_SECRET wajib diisi');

function fail(msg) {
  console.error(`[socket-engine] ${msg}`);
  process.exit(1);
}

const httpServer = http.createServer((req, res) => {
  if (req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    return res.end(JSON.stringify({ ok: true }));
  }
  res.writeHead(404);
  res.end();
});

const io = new Server(httpServer, {
  cors: { origin: ORIGINS, credentials: false },
  pingInterval: 20000,
  pingTimeout: 20000,
  maxHttpBufferSize: 1e5,
});

const connectionsPerIp = new Map();

io.use((socket, next) => {
  const ip = socket.handshake.headers['x-forwarded-for']?.split(',')[0].trim() || socket.handshake.address;
  const count = connectionsPerIp.get(ip) || 0;
  if (count >= MAX_CONN_PER_IP) return next(new Error('too_many_connections'));

  try {
    const claims = jwt.verify(socket.handshake.auth?.token, SECRET, { algorithms: ['HS256'] });
    if (!claims.sub || !claims.env) throw new Error('bad_claims');
    socket.data = { sub: claims.sub, env: claims.env, exp: claims.exp, ip };
    connectionsPerIp.set(ip, count + 1);
    next();
  } catch {
    next(new Error('unauthorized'));
  }
});

async function internal(path, { method = 'GET', body, query } = {}) {
  const url = new URL(`${LARAVEL}/v1/internal/inbox/${path}`);
  if (query) Object.entries(query).forEach(([k, v]) => url.searchParams.set(k, v));
  const res = await fetch(url, {
    method,
    headers: { 'X-Internal-Secret': INTERNAL, Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}) },
    body: body ? JSON.stringify(body) : undefined,
    signal: AbortSignal.timeout(5000),
  });
  if (!res.ok) throw new Error(`laravel ${res.status}`);
  return (await res.json()).data;
}

io.on('connection', async (socket) => {
  const { sub, env, exp, ip } = socket.data;
  socket.join(`env:${env}:sub:${sub}`);

  // Token kedaluwarsa -> beri tahu klien lalu putuskan
  const ttl = Math.max(exp * 1000 - Date.now(), 0);
  const timer = setTimeout(() => {
    socket.emit('auth:expired');
    socket.disconnect(true);
  }, ttl);

  socket.on('disconnect', () => {
    clearTimeout(timer);
    const n = (connectionsPerIp.get(ip) || 1) - 1;
    n <= 0 ? connectionsPerIp.delete(ip) : connectionsPerIp.set(ip, n);
  });

  // Unread awal
  try {
    socket.emit('unread:count', await internal('unread', { query: { env, subscriber_id: sub } }));
  } catch (e) {
    console.error('[socket-engine] unread gagal:', e.message);
  }

  // Tandai dibaca: DB (Laravel) tetap sumber kebenaran; Laravel yang mem-publish hasilnya.
  socket.on('notification:mark_read', async (payload = {}) => {
    try {
      const ids = Array.isArray(payload.ids) ? payload.ids.filter((i) => typeof i === 'string').slice(0, 200) : [];
      await internal('read', { method: 'POST', body: { env, subscriber_id: sub, ids, all: payload.all === true } });
    } catch (e) {
      console.error('[socket-engine] mark_read gagal:', e.message);
    }
  });
});

// Relay dari Laravel
const subscriber = new Redis(REDIS_URL, { maxRetriesPerRequest: null });
subscriber.on('error', (e) => console.error('[socket-engine] redis:', e.message));
subscriber.subscribe(CHANNEL, (err) => err && fail(`subscribe gagal: ${err.message}`));
subscriber.on('message', (_channel, raw) => {
  try {
    const m = JSON.parse(raw);
    if (m.env && m.subscriber_id && m.type) io.to(`env:${m.env}:sub:${m.subscriber_id}`).emit(m.type, m.data);
  } catch (e) {
    console.error('[socket-engine] pesan tidak valid:', e.message);
  }
});

httpServer.listen(PORT, () => console.log(`[socket-engine] listening on :${PORT}`));

for (const sig of ['SIGTERM', 'SIGINT']) {
  process.on(sig, () => io.close(() => process.exit(0)));
}
