import Alpine from 'alpinejs';

// Reveal saat scroll (nonaktif otomatis lewat CSS bila prefers-reduced-motion)
document.addEventListener('DOMContentLoaded', () => {
  const els = document.querySelectorAll('.reveal');
  if (!('IntersectionObserver' in window)) {
    els.forEach((el) => el.classList.add('is-in'));
    return;
  }
  const io = new IntersectionObserver(
    (entries) => entries.forEach((e) => e.isIntersecting && (e.target.classList.add('is-in'), io.unobserve(e.target))),
    { threshold: 0.15 },
  );
  els.forEach((el) => io.observe(el));
});

// Easter egg: spray can (lazy, hanya aktif saat diminta)
Alpine.data('sprayCanvas', () => ({
  on: false,
  drawing: false,
  init() {
    const c = this.$refs.canvas;
    const fit = () => {
      c.width = window.innerWidth;
      c.height = document.documentElement.scrollHeight;
    };
    fit();
    window.addEventListener('resize', fit);
  },
  spray(e) {
    const c = this.$refs.canvas;
    const ctx = c.getContext('2d');
    const { pageX: x, pageY: y } = e;
    const g = ctx.createRadialGradient(x, y, 2, x, y, 26);
    g.addColorStop(0, 'rgba(0,56,255,.38)');
    g.addColorStop(1, 'rgba(0,56,255,0)');
    ctx.fillStyle = g;
    ctx.beginPath();
    ctx.arc(x, y, 26, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = '#0038ff';
    for (let i = 0; i < 26; i++) {
      const a = Math.random() * Math.PI * 2;
      const r = Math.random() * 28;
      ctx.fillRect(x + Math.cos(a) * r, y + Math.sin(a) * r, Math.random() * 2.2, Math.random() * 2.2);
    }
  },
  clear() {
    const c = this.$refs.canvas;
    c.getContext('2d').clearRect(0, 0, c.width, c.height);
  },
}));

window.Alpine = Alpine;
Alpine.start();
