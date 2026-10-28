import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';

Alpine.plugin(collapse);
Alpine.plugin(focus);

const esc = (s) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

Alpine.data('docsApp', ({ searchUrl }) => ({
  nav: false,
  desktop: window.innerWidth >= 1024,
  searchOpen: false,
  searchLoading: false,
  searchError: false,
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

  openNav() {
    this.nav = true;
    this.$nextTick(() => this.$refs.navClose?.focus());
  },

  closeNav() {
    this.nav = false;
    this.$nextTick(() => this.$refs.navTrigger?.focus());
  },

  async openSearch() {
    this.searchOpen = true;
    this.$nextTick(() => this.$refs.q?.focus());
    if (this.index === null && !this.searchLoading) await this.loadSearch();
    this.search();
  },

  async loadSearch() {
    this.searchLoading = true;
    this.searchError = false;
    try {
      const response = await fetch(searchUrl, { headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error('search-index-unavailable');
      const index = await response.json();
      if (!Array.isArray(index)) throw new Error('invalid-search-index');
      this.index = index;
      this.search();
    } catch {
      this.index = null;
      this.results = [];
      this.searchError = true;
    } finally {
      this.searchLoading = false;
    }
  },

  closeSearch() {
    this.searchOpen = false;
    this.query = '';
    this.results = [];
    this.active = 0;
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
    this.$nextTick(() => document.getElementById(`docs-result-${this.active}`)?.scrollIntoView({ block: 'nearest' }));
  },

  go() {
    const r = this.results[this.active];
    if (r) window.location.href = r.url;
  },

  scrollTop() {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
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
      else if (this.nav) this.closeNav();
    }
  },
}));

window.Alpine = Alpine;
Alpine.start();
