import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import { api } from './bootstrap';
import { workflowEditor } from './components/workflow-editor';
import { workflowTester } from './components/workflow-tester';
import { dashboardCharts } from './components/dashboard';
import { notiforaInbox } from './components/inbox';
import { paymentStatus } from './components/billing';

Alpine.plugin(collapse);
Alpine.plugin(focus);

Alpine.store('toast', {
  items: [],
  push(type, message) {
    const id = crypto.randomUUID();
    this.items.push({ id, type, message });
    setTimeout(() => this.remove(id), 5000);
  },
  remove(id) {
    this.items = this.items.filter((i) => i.id !== id);
  },
});

Alpine.data('workflowEditor', workflowEditor);
Alpine.data('workflowTester', workflowTester);
Alpine.data('dashboardCharts', dashboardCharts);
Alpine.data('notiforaInbox', notiforaInbox);
Alpine.data('paymentStatus', paymentStatus);

Alpine.data('copy', (text) => ({
  done: false,
  async copy() {
    try {
      await navigator.clipboard.writeText(text);
      this.done = true;
      setTimeout(() => (this.done = false), 1800);
    } catch {
      Alpine.store('toast').push('error', 'Tidak dapat menyalin. Salin manual.');
    }
  },
}));

window.api = api;
window.Alpine = Alpine;
Alpine.start();
