@extends('layouts.app')
@section('title', $workflow->name)
@section('content')
@php $canEdit = $currentRole->atLeast(\App\Enums\MemberRole::DEVELOPER); @endphp
<div x-data="workflowEditor({
  workflow: { name: @js($workflow->name), priority: @js($workflow->priority), is_critical: @js((bool) $workflow->is_critical), status: @js($workflow->status), version: @js($workflow->publishedVersion?->version) },
  steps: @js($steps),
  providers: @js($providers),
  channels: @js($channels),
  saveUrl: @js(route('console.workflows.update', $workflow)),
  publishUrl: @js(route('console.workflows.publish', $workflow)),
  previewUrl: @js(route('console.templates.preview')),
  samplePayload: @js($samplePayload),
})">

  <x-page-header :title="$workflow->name" :subtitle="'Trigger: ' . $workflow->trigger_key">
    <span class="text-xs font-mono text-zinc-500" x-show="dirty" x-cloak>● perubahan belum disimpan</span>
    <x-status-badge :status="$workflow->status" />
    <span class="text-xs font-mono text-zinc-500" x-show="workflow.version" x-cloak x-text="'v' + workflow.version"></span>
    <a href="{{ route('console.workflows.test', $workflow) }}" class="btn-secondary"><x-icon name="play" />Test</a>
    @if ($canEdit)
      <button class="btn-secondary" @click="save()" :disabled="saving || !dirty"><span x-text="saving ? 'Menyimpan…' : 'Simpan draft'">Simpan draft</span></button>
      <button class="btn-primary" @click="publish()" :disabled="publishing || saving"><span x-text="publishing ? 'Publishing…' : 'Publish'">Publish</span></button>
    @endif
  </x-page-header>

  <div x-show="errors.length" x-cloak class="mb-5 rounded-md border border-err/40 bg-err/10 px-4 py-3 text-sm text-red-200" role="alert">
    <p class="font-medium mb-1">Perbaiki masalah berikut:</p>
    <ul class="list-disc pl-5"><template x-for="e in errors"><li x-text="e"></li></template></ul>
  </div>

  <div class="grid lg:grid-cols-12 gap-5">
    {{-- Kolom kiri: pengaturan + daftar step --}}
    <div class="lg:col-span-5 space-y-4">
      <section class="card card-pad grid sm:grid-cols-2 gap-3">
        <div class="sm:col-span-2"><label class="label" for="wf-name">Nama workflow</label><input id="wf-name" class="input" x-model="workflow.name" @disabled(! $canEdit)></div>
        <div><label class="label" for="wf-prio">Prioritas queue</label>
          <select id="wf-prio" class="input" x-model="workflow.priority" @disabled(! $canEdit)><option value="NORMAL">Normal</option><option value="HIGH">High</option></select></div>
        <label class="flex items-end gap-2 pb-2 text-sm"><input type="checkbox" x-model="workflow.is_critical" class="accent-[#0033FF]" @disabled(! $canEdit)> <span>Kritis (OTP) <span class="hint block">Abaikan opt-out per-workflow</span></span></label>
      </section>

      @if ($canEdit)
      <section class="card card-pad">
        <p class="label">Tambah step</p>
        <div class="flex flex-wrap gap-2">
          <button class="btn-secondary btn-sm text-emerald-300" @click="add('CHANNEL','WHATSAPP')">+ WhatsApp</button>
          <button class="btn-secondary btn-sm text-blue-300" @click="add('CHANNEL','EMAIL')">+ Email</button>
          <button class="btn-secondary btn-sm text-sky-300" @click="add('CHANNEL','TELEGRAM')">+ Telegram</button>
          <button class="btn-secondary btn-sm text-amber-300" @click="add('CHANNEL','IN_APP')">+ In-App</button>
          <button class="btn-secondary btn-sm text-purple-300" @click="add('DELAY')">+ Delay</button>
          <button class="btn-secondary btn-sm text-pink-300" @click="add('FILTER')">+ Filter</button>
        </div>
      </section>
      @endif

      <ol class="space-y-0" aria-label="Urutan step">
        <li class="flex items-center gap-2 px-3 py-2 text-xs font-mono"><span class="px-2 py-1 bg-cobalt text-[#fff] rounded font-bold">TRIGGER</span><span class="text-zinc-400">{{ $workflow->trigger_key }}</span></li>
        <template x-for="(step, i) in steps" :key="step.uid">
          <li class="relative pl-6">
            <span class="absolute left-2.5 top-0 bottom-0 w-px bg-line"></span>
            <div @click="selected = step.uid" :class="selected === step.uid ? 'border-cobalt' : 'border-line hover:border-zinc-600'" class="card my-2 p-3 cursor-pointer border">
              <div class="flex items-center gap-2">
                <span class="text-xs font-mono text-zinc-500" x-text="'#' + (i + 1)"></span>
                <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded border border-line" x-text="step.type === 'CHANNEL' ? step.channel : step.type"></span>
                <span class="text-sm text-white font-medium truncate" x-text="step.name"></span>
                @if ($canEdit)
                <span class="ml-auto flex items-center">
                  <button class="btn-ghost btn-sm" @click.stop="move(i,-1)" :disabled="i === 0" aria-label="Naik"><x-icon name="arrow-up" class="w-3.5 h-3.5" /></button>
                  <button class="btn-ghost btn-sm" @click.stop="move(i,1)" :disabled="i === steps.length - 1" aria-label="Turun"><x-icon name="arrow-down" class="w-3.5 h-3.5" /></button>
                  <button class="btn-ghost btn-sm text-err" @click.stop="remove(step.uid)" aria-label="Hapus step"><x-icon name="trash" class="w-3.5 h-3.5" /></button>
                </span>
                @endif
              </div>
              <p class="text-xs text-zinc-500 mt-1 truncate" x-text="summary(step)"></p>
              <p class="text-[11px] text-amber-300 mt-1" x-show="step.fallback_uid" x-cloak>↳ fallback: <span x-text="steps.find(s => s.uid === step.fallback_uid)?.name"></span></p>
            </div>
          </li>
        </template>
        <li x-show="steps.length === 0" x-cloak class="card"><x-empty-state icon="workflow" title="Belum ada step" text="Tambahkan step WhatsApp, Email, Telegram, atau In-App di atas." /></li>
      </ol>
    </div>

    {{-- Kolom kanan: pengaturan step --}}
    <div class="lg:col-span-7">
      <template x-if="current">
        <section class="card card-pad space-y-4" :key="current.uid">
          <div class="grid sm:grid-cols-2 gap-3">
            <div><label class="label" for="st-name">Nama step</label><input id="st-name" class="input" x-model="current.name" @disabled(! $canEdit)></div>
            <template x-if="current.type === 'CHANNEL'">
              <div><label class="label" for="st-prov">Provider</label>
                <select id="st-prov" class="input" x-model="current.provider_id" @disabled(! $canEdit)>
                  <option :value="null">Default channel</option>
                  <template x-for="p in providersFor(current.channel)" :key="p.id"><option :value="p.id" x-text="p.name"></option></template>
                </select></div>
            </template>
          </div>

          {{-- DELAY --}}
          <template x-if="current.type === 'DELAY'">
            <div>
              <label class="label" for="dl">Tunggu</label>
              <div class="flex gap-2 max-w-xs">
                <input id="dl" type="number" min="1" class="input" x-model.number="delayValue" @disabled(! $canEdit)>
                <select class="input w-auto" x-model="delayUnit" aria-label="Satuan"><option value="s">detik</option><option value="m">menit</option><option value="h">jam</option><option value="d">hari</option></select>
              </div>
              <p class="hint">Maksimal 30 hari. Workflow berhenti sementara lalu dilanjutkan otomatis.</p>
            </div>
          </template>

          {{-- FILTER --}}
          <template x-if="current.type === 'FILTER'">
            <div class="space-y-2">
              <p class="hint">Jika semua rule (AND) tidak terpenuhi, sisa workflow dihentikan.</p>
              <template x-for="(r, ri) in current.config.rules" :key="ri">
                <div class="flex flex-wrap gap-2 items-center">
                  <input class="input input-mono flex-1 min-w-32" x-model="r.path" placeholder="payload.amount" aria-label="Path">
                  <select class="input w-auto" x-model="r.op" aria-label="Operator">
                    <option value="eq">=</option><option value="neq">≠</option><option value="gt">&gt;</option><option value="gte">≥</option><option value="lt">&lt;</option><option value="lte">≤</option>
                    <option value="in">in (a,b)</option><option value="contains">contains</option><option value="exists">exists</option>
                  </select>
                  <input class="input input-mono flex-1 min-w-24" x-model="r.value" placeholder="nilai" aria-label="Nilai" x-show="r.op !== 'exists'">
                  <button class="btn-ghost btn-sm text-err" @click="removeRule(ri)" aria-label="Hapus rule"><x-icon name="x" /></button>
                </div>
              </template>
              <button class="btn-secondary btn-sm" @click="addRule()">+ Rule</button>
            </div>
          </template>

          {{-- CHANNEL --}}
          <template x-if="current.type === 'CHANNEL'">
            <div class="space-y-4">
              <div x-show="current.channel === 'EMAIL' || current.channel === 'IN_APP'">
                <label class="label" for="st-sub" x-text="current.channel === 'EMAIL' ? 'Subject' : 'Judul'"></label>
                <input id="st-sub" class="input" x-model="current.template.subject" @input="refreshPreview()" @disabled(! $canEdit)>
              </div>
              <div>
                <div class="flex items-center justify-between"><label class="label" for="st-body">Isi pesan</label>
                  <span class="text-[11px] text-zinc-500 font-mono" x-text="current.template.body.length + ' karakter'"></span></div>
                <textarea id="st-body" rows="7" class="input input-mono" x-model="current.template.body" @input="refreshPreview()" @disabled(! $canEdit)></textarea>
                <div class="flex flex-wrap gap-1.5 mt-2">
                  <template x-for="v in ['@{{ subscriber.first_name }}', '@{{ subscriber.email }}', '@{{ payload.invoiceId }}', '@{{ event.id }}']" :key="v">
                    <button type="button" class="btn-secondary btn-sm font-mono" @click="insertVar(v)" x-text="v"></button>
                  </template>
                </div>
                <p class="hint">Filter: <code>upper lower title trim default:"x" truncate:N currency:"IDR" number:N date:"d M Y" e json</code></p>
              </div>

              <div x-show="current.channel === 'EMAIL'">
                <label class="label" for="st-html">Isi HTML (opsional)</label>
                <textarea id="st-html" rows="4" class="input input-mono" x-model="current.template.body_html" @disabled(! $canEdit)></textarea>
                <p class="hint">Nilai variabel di-escape otomatis. Pratinjau HTML tidak menjalankan script.</p>
              </div>

              <div>
                <label class="label" for="st-fb">Fallback jika gagal</label>
                <select id="st-fb" class="input" x-model="current.fallback_uid" @disabled(! $canEdit)>
                  <option :value="null">Tidak ada</option>
                  <template x-for="s in fallbackOptions" :key="s.uid"><option :value="s.uid" x-text="s.name + ' (' + s.channel + ')'"></option></template>
                </select>
                <p class="hint">Step fallback hanya berjalan bila step ini gagal permanen; tidak berjalan dalam alur normal.</p>
              </div>

              <div class="rounded-md border border-line bg-surface p-3">
                <div class="flex items-center justify-between mb-2"><p class="label !mb-0">Preview live</p>
                  <details class="text-xs"><summary class="cursor-pointer text-zinc-400">Sample payload</summary>
                    <textarea rows="5" class="input input-mono mt-2 w-72" x-model="samplePayload" @input="refreshPreview()" aria-label="Sample payload JSON"></textarea>
                    <p class="error-text" x-text="samplePayloadError"></p></details></div>
                <p class="text-sm font-semibold text-white" x-show="preview.subject" x-text="preview.subject"></p>
                <pre class="whitespace-pre-wrap break-words text-sm text-zinc-200 font-sans" x-text="preview.output || '(kosong)'"></pre>
                <template x-for="e in preview.errors"><p class="error-text" x-text="'⚠ ' + e"></p></template>
                <template x-for="w in preview.warnings"><p class="text-xs text-warn" x-text="'• ' + w"></p></template>
              </div>
            </div>
          </template>
        </section>
      </template>
      <div x-show="!current" x-cloak class="card"><x-empty-state icon="workflow" title="Pilih step" text="Klik sebuah step di kiri untuk mengatur isinya." /></div>
    </div>
  </div>

  @if ($canEdit)
  <div class="mt-8 flex flex-wrap gap-2">
    <form method="POST" action="{{ route('console.workflows.archive', $workflow) }}">@csrf<button class="btn-secondary">{{ $workflow->status === 'ARCHIVED' ? 'Pulihkan' : 'Arsipkan' }}</button></form>
    <form method="POST" action="{{ route('console.workflows.destroy', $workflow) }}" onsubmit="return confirm('Hapus workflow beserta riwayat event-nya?')">@csrf @method('DELETE')<button class="btn-danger">Hapus workflow</button></form>
  </div>
  @endif
</div>
@endsection
