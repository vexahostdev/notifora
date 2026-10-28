import { api } from '../bootstrap';

const MAX_STEPS = 20;

/**
 * Editor workflow linear. State dari server (Blade @js), simpan via PUT, publish via POST.
 */
export const workflowEditor = ({ workflow, steps, providers, channels, saveUrl, publishUrl, previewUrl, samplePayload }) => ({
  workflow,
  steps,
  providers,
  channels,
  selected: steps[0]?.uid ?? null,
  dirty: false,
  saving: false,
  publishing: false,
  errors: [],
  preview: { subject: '', output: '', errors: [], warnings: [] },
  samplePayload: JSON.stringify(samplePayload, null, 2),
  samplePayloadError: '',
  _timer: null,
  _previewRequest: 0,

  init() {
    window.addEventListener('beforeunload', (e) => {
      if (this.dirty) {
        e.preventDefault();
        e.returnValue = '';
      }
    });
    this.$watch('steps', () => (this.dirty = true), { deep: true });
    this.$watch('workflow', () => (this.dirty = true), { deep: true });
    this.$nextTick(() => {
      this.dirty = false;
      this.refreshPreview();
    });
    this.$watch('selected', () => this.refreshPreview());
  },

  get current() {
    return this.steps.find((s) => s.uid === this.selected) ?? null;
  },

  get fallbackOptions() {
    return this.steps.filter((s) => s.type === 'CHANNEL' && s.uid !== this.selected);
  },

  providersFor(channel) {
    return this.providers.filter((p) => p.channel === channel);
  },

  add(type, channel = null) {
    if (this.steps.length >= MAX_STEPS) {
      return this.$store.toast.push('error', `Maksimal ${MAX_STEPS} step`);
    }
    const label = this.channels.find((c) => c.value === channel)?.label ?? channel;
    const step = {
      uid: crypto.randomUUID(),
      type,
      name: type === 'CHANNEL' ? `Kirim ${label}` : type === 'DELAY' ? 'Tunggu' : 'Filter',
      channel,
      provider_id: null,
      config: type === 'DELAY' ? { seconds: 3600 } : type === 'FILTER' ? { rules: [{ path: 'payload.', op: 'eq', value: '' }] } : {},
      template: { subject: '', body: type === 'CHANNEL' ? 'Halo {{ subscriber.first_name|default:"" }}' : '', body_html: '' },
      fallback_uid: null,
    };
    this.steps.push(step);
    this.selected = step.uid;
  },

  remove(uid) {
    if (!confirm('Hapus step ini?')) return;
    this.steps = this.steps.filter((s) => s.uid !== uid).map((s) => (s.fallback_uid === uid ? { ...s, fallback_uid: null } : s));
    this.selected = this.steps[0]?.uid ?? null;
  },

  move(index, delta) {
    const to = index + delta;
    if (to < 0 || to >= this.steps.length) return;
    const [s] = this.steps.splice(index, 1);
    this.steps.splice(to, 0, s);
  },

  addRule() {
    this.current.config.rules.push({ path: 'payload.', op: 'eq', value: '' });
  },

  removeRule(i) {
    this.current.config.rules.splice(i, 1);
  },

  delayUnit: 'm',
  get delayValue() {
    const s = this.current?.config?.seconds ?? 0;
    const value = s / { s: 1, m: 60, h: 3600, d: 86400 }[this.delayUnit];
    return Number.isInteger(value) ? value : Number(value.toFixed(4));
  },
  set delayValue(v) {
    const factor = { s: 1, m: 60, h: 3600, d: 86400 }[this.delayUnit];
    this.current.config.seconds = Math.max(1, Math.round((Number(v) || 1) * factor));
  },

  summary(step) {
    if (step.type === 'DELAY') return `Tunggu ${this.humanDuration(step.config.seconds)}`;
    if (step.type === 'FILTER') return `${step.config.rules?.length ?? 0} rule`;
    return (step.template.body || '').slice(0, 60) || '(isi pesan kosong)';
  },

  humanDuration(sec) {
    if (sec % 86400 === 0) return `${sec / 86400} hari`;
    if (sec % 3600 === 0) return `${sec / 3600} jam`;
    if (sec % 60 === 0) return `${sec / 60} menit`;
    return `${sec} detik`;
  },

  payloadObject() {
    try {
      this.samplePayloadError = '';
      return JSON.parse(this.samplePayload || '{}');
    } catch {
      this.samplePayloadError = 'JSON tidak valid';
      return null;
    }
  },

  refreshPreview() {
    clearTimeout(this._timer);
    const request = ++this._previewRequest;
    this._timer = setTimeout(async () => {
      const step = this.current;
      const payload = this.payloadObject();
      if (!step || step.type !== 'CHANNEL' || payload === null) return;
      try {
        const isEmail = step.channel === 'EMAIL';
        const [body, subject] = await Promise.all([
          api(previewUrl, { method: 'POST', body: { template: step.template.body, payload, html: false } }),
          step.template.subject ? api(previewUrl, { method: 'POST', body: { template: step.template.subject, payload } }) : Promise.resolve({ output: '', errors: [] }),
        ]);
        if (request !== this._previewRequest) return;
        this.preview = {
          subject: subject.output,
          output: body.output,
          errors: [...body.errors, ...subject.errors],
          warnings: body.warnings,
          isEmail,
        };
      } catch {
        /* preview bersifat best-effort */
      }
    }, 400);
  },

  insertVar(text) {
    if (!this.current || this.current.type !== 'CHANNEL') return;
    this.current.template.body += text;
    this.refreshPreview();
  },

  async save({ silent = false } = {}) {
    this.saving = true;
    this.errors = [];
    try {
      const data = await api(saveUrl, {
        method: 'PUT',
        body: { name: this.workflow.name, priority: this.workflow.priority, is_critical: this.workflow.is_critical, steps: this.steps },
      });
      const keep = this.selected;
      this.steps = data.steps;
      this.selected = this.steps.find((s) => s.uid === keep)?.uid ?? this.steps[0]?.uid ?? null;
      this.$nextTick(() => (this.dirty = false));
      if (!silent) this.$store.toast.push('success', 'Draft disimpan');
      return true;
    } catch (e) {
      this.errors = Object.values(e.data?.errors ?? {}).flat().length ? Object.values(e.data.errors).flat() : [e.message];
      this.$store.toast.push('error', 'Gagal menyimpan draft');
      return false;
    } finally {
      this.saving = false;
    }
  },

  async publish() {
    if (!(await this.save({ silent: true }))) return;
    this.publishing = true;
    try {
      const data = await api(publishUrl, { method: 'POST', body: {} });
      this.workflow.version = data.version;
      this.workflow.status = 'ACTIVE';
      this.errors = [];
      this.$nextTick(() => (this.dirty = false)); // perubahan version/status bukan edit pengguna
      this.$store.toast.push('success', `Dipublish sebagai v${data.version}`);
    } catch (e) {
      this.errors = e.data?.errors ?? [e.message];
      this.$store.toast.push('error', 'Publish gagal — periksa daftar masalah');
    } finally {
      this.publishing = false;
    }
  },
});
