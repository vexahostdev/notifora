/** Grafik dashboard admin: tiga deret harian. series = { events|revenue|signups: { labels, values } } */
export const adminCharts = (series) => ({
  charts: [],

  async init() {
    const { Chart, registerables } = await import('chart.js');
    Chart.register(...registerables);
    this.Chart = Chart;
    this.draw();
    window.addEventListener('themechange', () => this.draw());
  },

  draw() {
    if (!this.Chart) return;
    const css = getComputedStyle(document.documentElement);
    const v = (name, fallback) => css.getPropertyValue(name).trim() || fallback;
    this.Chart.defaults.color = v('--color-zinc-400', '#a1a1aa');
    this.Chart.defaults.borderColor = v('--color-line', '#27272a');
    const accent = v('--color-cobalt', '#0033ff');
    this.charts.forEach((c) => c.destroy());

    const make = (ref, set, type, color) => new this.Chart(this.$refs[ref], {
      type,
      data: { labels: set.labels, datasets: [{ data: set.values, borderColor: color, backgroundColor: type === 'bar' ? color : 'rgba(59,107,255,.15)', fill: type === 'line', tension: 0.3, pointRadius: 0, borderRadius: 3 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { maxTicksLimit: 6 } } } },
    });

    this.charts = [
      make('events', series.events, 'line', accent),
      make('revenue', series.revenue, 'bar', '#10b981'),
      make('signups', series.signups, 'bar', '#f59e0b'),
    ];
  },
});

/** QR code untuk enrolment 2FA (library qrcode dimuat dinamis). */
export const qrCode = ({ text }) => ({
  async init() {
    const QRCode = (await import('qrcode')).default;
    await QRCode.toCanvas(this.$refs.canvas, text, { width: 160, margin: 1 });
  },
});
