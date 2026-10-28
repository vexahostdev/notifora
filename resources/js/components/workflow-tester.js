import { api } from '../bootstrap';

/** Simulator trigger workflow: kirim event uji lalu poll status. */
export const workflowTester = ({ sendUrl, statusUrl, defaults }) => ({
  to: JSON.stringify(defaults.to, null, 2),
  payload: JSON.stringify(defaults.payload, null, 2),
  real: false,
  sending: false,
  error: '',
  result: null,

  parse(text, label) {
    try {
      return JSON.parse(text || '{}');
    } catch {
      this.error = `${label}: JSON tidak valid`;
      return null;
    }
  },

  async send() {
    this.error = '';
    this.result = null;
    const to = this.parse(this.to, 'Penerima');
    const payload = this.parse(this.payload, 'Payload');
    if (to === null || payload === null) {
      if (!this.error) this.error = `${to === null ? 'Penerima' : 'Payload'}: harus berupa objek JSON`;
      return;
    }
    if (typeof to !== 'object' || Array.isArray(to)) {
      this.error = 'Penerima: harus berupa objek JSON';
      return;
    }
    if (typeof payload !== 'object' || Array.isArray(payload)) {
      this.error = 'Payload: harus berupa objek JSON';
      return;
    }
    if (this.real && !confirm('Pesan akan benar-benar dikirim lewat provider. Lanjutkan?')) return;

    this.sending = true;
    try {
      const { event_id } = await api(sendUrl, { method: 'POST', body: { to, payload, real: this.real } });
      await this.poll(event_id);
    } catch (e) {
      this.error = e.data?.error || Object.values(e.data?.errors ?? {}).flat().join(' ') || e.message || 'Simulasi gagal dijalankan.';
    } finally {
      this.sending = false;
    }
  },

  async poll(eventId) {
    for (let i = 0; i < 30; i++) {
      this.result = await api(statusUrl.replace('__ID__', eventId));
      if (this.result.final) return;
      await new Promise((r) => setTimeout(r, 1000));
    }
    this.error = 'Workflow masih menunggu atau diproses setelah 30 detik. Muat ulang halaman untuk memeriksa status terbaru.';
  },

  badge(status) {
    return (
      {
        COMPLETED: 'text-ok', SENT: 'text-ok', DELIVERED: 'text-ok', READ: 'text-ok',
        FAILED: 'text-err', SKIPPED: 'text-warn', PARTIAL: 'text-warn', WAITING: 'text-info', QUEUED: 'text-zinc-400', PENDING: 'text-zinc-400', PROCESSING: 'text-info',
      }[status] ?? 'text-zinc-400'
    );
  },
});
