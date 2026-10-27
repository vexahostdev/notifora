/**
 * Widget inbox. `token` = JWT subscriber dari server klien (POST /v1/subscribers/{id}/inbox-token).
 * Semua teks dirender dengan x-text (tidak pernah x-html).
 */
export const notiforaInbox = ({ token, socketUrl, apiUrl, subscriberId, apiKeyless = true }) => ({
  open: false,
  unread: 0,
  messages: [],
  socket: null,
  pollTimer: null,

  async init() {
    await this.connect(token);
  },

  async connect(tok) {
    const { io } = await import('socket.io-client');
    this.socket?.disconnect();
    this.socket = io(socketUrl, { auth: { token: tok }, transports: ['websocket'], reconnectionDelayMax: 10000 });

    this.socket.on('unread:count', (d) => (this.unread = d.unreadCount));
    this.socket.on('notification:received', (m) => {
      this.messages.unshift(m);
      this.messages = this.messages.slice(0, 50);
    });
    this.socket.on('notification:read', ({ ids }) => {
      this.messages = this.messages.map((m) => (ids.includes(m.id) ? { ...m, read_at: new Date().toISOString() } : m));
    });
    this.socket.on('auth:expired', () => window.dispatchEvent(new CustomEvent('notifora:token-expired')));
    this.socket.on('connect_error', () => this.startPolling());
    this.socket.on('connect', () => this.stopPolling());
  },

  reconnect(newToken) {
    return this.connect(newToken);
  },

  // Fallback bila WebSocket gagal: polling REST lewat endpoint proxy milik aplikasi klien.
  startPolling() {
    if (this.pollTimer || !apiUrl) return;
    const tick = async () => {
      try {
        const res = await fetch(apiUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const json = await res.json();
        this.messages = json.data ?? [];
        this.unread = json.meta?.unread_count ?? this.unread;
      } catch { /* abaikan */ }
    };
    tick();
    this.pollTimer = setInterval(tick, 30000);
  },

  stopPolling() {
    clearInterval(this.pollTimer);
    this.pollTimer = null;
  },

  markRead(m) {
    if (m.read_at) return;
    this.socket?.emit('notification:mark_read', { ids: [m.id] });
  },

  markAll() {
    this.socket?.emit('notification:mark_read', { all: true });
  },

  timeAgo(iso) {
    const s = Math.max(1, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
    if (s < 60) return `${s}d lalu`;
    if (s < 3600) return `${Math.round(s / 60)}m lalu`;
    if (s < 86400) return `${Math.round(s / 3600)}j lalu`;
    return `${Math.round(s / 86400)}h lalu`;
  },

  destroy() {
    this.stopPolling();
    this.socket?.disconnect();
  },
});
