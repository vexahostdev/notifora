import { api } from '../bootstrap';

/** Chart.js dimuat dinamis hanya di dashboard. */
export const dashboardCharts = ({ url, initial }) => ({
  range: initial.range,
  data: initial,
  loading: false,
  error: '',
  rangeRequest: 0,
  charts: {},

  async init() {
    const { Chart, registerables } = await import('chart.js');
    Chart.register(...registerables);
    this.Chart = Chart;
    this.draw();
    // Warna grafik mengikuti tema aktif
    window.addEventListener('themechange', () => this.draw());
  },

  themeColors() {
    const css = getComputedStyle(document.documentElement);
    const v = (name, fallback) => css.getPropertyValue(name).trim() || fallback;
    return { text: v('--color-zinc-400', '#a1a1aa'), grid: v('--color-line', '#27272a'), accent: v('--color-cobalt', '#0033ff') };
  },

  async setRange(range) {
    const request = ++this.rangeRequest;
    this.range = range;
    this.loading = true;
    this.error = '';
    try {
      const data = await api(`${url}?range=${range}`);
      if (request !== this.rangeRequest) return;
      this.data = data;
      this.draw();
    } catch {
      if (request === this.rangeRequest) {
        this.range = this.data.range;
        this.error = 'Data gagal dimuat. Silakan coba lagi.';
      }
    } finally {
      if (request === this.rangeRequest) this.loading = false;
    }
  },

  draw() {
    if (!this.Chart) return;
    const { labels, values } = this.data.series;
    const c = this.themeColors();
    this.Chart.defaults.color = c.text;
    this.Chart.defaults.borderColor = c.grid;
    this.charts.line?.destroy();
    this.charts.donut?.destroy();

    this.charts.line = new this.Chart(this.$refs.line, {
      type: 'line',
      data: { labels, datasets: [{ label: 'Events', data: values, borderColor: c.accent, backgroundColor: 'rgba(59,107,255,.15)', fill: true, tension: 0.3, pointRadius: 0 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { maxTicksLimit: 8 } } } },
    });

    const ch = this.data.channels;
    const keys = Object.keys(ch);
    this.charts.donut = new this.Chart(this.$refs.donut, {
      type: 'doughnut',
      data: { labels: keys, datasets: [{ data: keys.map((k) => ch[k]), backgroundColor: ['#0033ff', '#10b981', '#f59e0b', '#a78bfa'], borderWidth: 0 }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom' } } },
    });
  },
});
