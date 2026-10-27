import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';

Alpine.plugin(collapse);
Alpine.plugin(focus);

const esc = (s) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

Alpine.data('docsApp', ({ searchUrl }) => ({
  nav: false,
  searchOpen: false,
  query: '',
  results: [],
  active: 0,
  index: null,
  current: null,

  init() {
    // Scroll-spy: tandai artikel yang sedang dibaca di sidebar
    const articles = [...document.querySelectorAll('.doc-article')];
    if (articles.length && 'IntersectionObserver' in window) {
      const io = new IntersectionObserver(
        (entries) => {
          const visible = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
          if (visible[0]) this.current = visible[0].target.id;
        },
        { rootMargin: '-120px 0px -65% 0px' },
      );
      articles.forEach((a) => io.observe(a));
    }
    if (location.hash) this.current = location.hash.slice(1);
    window.addEventListener('hashchange', () => (this.current = location.hash.slice(1)));
  },

  async openSearch() {
    this.searchOpen = true;
    this.$nextTick(() => this.$refs.q?.focus());
    if (!this.index) {
      try {
        this.index = await (await fetch(searchUrl, { headers: { Accept: 'application/json' } })).json();
      } catch {
        this.index = [];
      }
    }
    this.search();
  },

  closeSearch() {
    this.searchOpen = false;
    this.query = '';
    this.results = [];
  },

  search() {
    const tokens = this.query.toLowerCase().split(/\s+/).filter(Boolean);
    if (!tokens.length || !this.index) {
      this.results = [];
      return;
    }
    const scored = [];
    for (const e of this.index) {
      const title = e.title.toLowerCase();
      const text = e.text.toLowerCase();
      const hay = `${title} ${e.section.toLowerCase()} ${text}`;
      if (!tokens.every((t) => hay.includes(t))) continue;
      let score = 0;
      for (const t of tokens) score += (title.includes(t) ? 6 : 0) + (text.includes(t) ? 1 : 0) + (e.section.toLowerCase().includes(t) ? 2 : 0);
      scored.push({ e, score });
    }
    scored.sort((a, b) => b.score - a.score);
    this.results = scored.slice(0, 12).map(({ e }) => ({ ...e, snippet: this.snippet(e.text, tokens) }));
    this.active = 0;
  },

  snippet(text, tokens) {
    const lower = text.toLowerCase();
    const hits = tokens.map((t) => lower.indexOf(t)).filter((i) => i >= 0);
    const pos = hits.length ? Math.max(0, Math.min(...hits) - 40) : 0;
    let out = esc(text.slice(pos, pos + 160));
    for (const t of tokens) out = out.replace(new RegExp(`(${t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'ig'), '<mark class="bg-cobalt/30 text-inherit rounded px-0.5">$1</mark>');
    return (pos > 0 ? '…' : '') + out + '…';
  },

  move(d) {
    if (!this.results.length) return;
    this.active = (this.active + d + this.results.length) % this.results.length;
  },

  go() {
    const r = this.results[this.active];
    if (r) window.location.href = r.url;
  },

  onKey(e) {
    const inField = ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.target.isContentEditable;
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      this.searchOpen ? this.closeSearch() : this.openSearch();
    } else if (e.key === '/' && !inField && !this.searchOpen) {
      e.preventDefault();
      this.openSearch();
    } else if (e.key === 'Escape') {
      if (this.searchOpen) this.closeSearch();
      this.nav = false;
    }
  },
}));

window.Alpine = Alpine;
Alpine.start();
