import { api } from '../bootstrap';

/** Polling status invoice sampai final (lunas / kedaluwarsa / gagal), lalu muat ulang halaman. */
export const paymentStatus = ({ url, pending }) => ({
  pending,
  timer: null,
  tries: 0,

  init() {
    if (!this.pending) return;
    this.timer = setInterval(() => this.check(), 4000);
    document.addEventListener('visibilitychange', () => !document.hidden && this.check());
  },

  async check() {
    if (document.hidden || this.tries++ > 450) return; // ~30 menit
    try {
      const r = await api(url);
      if (r.final) {
        clearInterval(this.timer);
        window.location.reload();
      }
    } catch {
      /* jaringan putus: coba lagi pada interval berikutnya */
    }
  },
});
