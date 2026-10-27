@extends('layouts.app')
@section('title', 'Test ' . $workflow->name)
@section('content')
<x-page-header :title="'Simulator: ' . $workflow->name" :subtitle="'Trigger: ' . $workflow->trigger_key">
  <a href="{{ route('console.workflows.edit', $workflow) }}" class="btn-secondary">← Editor</a>
</x-page-header>

@unless ($workflow->isActive())
  <div class="mb-5 rounded-md border border-warn/40 bg-warn/10 px-4 py-3 text-sm text-amber-200">Workflow belum aktif. Publish dulu dari editor agar bisa diuji.</div>
@endunless

<div x-data="workflowTester({
  sendUrl: @js(route('console.workflows.test.send', $workflow)),
  statusUrl: @js(route('console.events.status', '__ID__')),
  defaults: { to: { subscriberId: 'test_user_1', firstName: 'Budi', email: 'budi@example.com', phone: '0812-3456-7890' }, payload: { invoiceId: 'INV-001', amount: 450000 } },
})" class="grid lg:grid-cols-2 gap-5">
  <section class="card card-pad space-y-4">
    <div><label class="label" for="t-to">Penerima (JSON)</label><textarea id="t-to" rows="8" class="input input-mono" x-model="to"></textarea></div>
    <div><label class="label" for="t-pl">Payload (JSON)</label><textarea id="t-pl" rows="8" class="input input-mono" x-model="payload"></textarea></div>
    <label class="flex items-start gap-2 text-sm"><input type="checkbox" x-model="real" class="mt-1 accent-[#0033FF]">
      <span>Kirim ke provider sungguhan<span class="hint block">Default off: hanya render template (dry-run), tidak ada pesan terkirim.</span></span></label>
    <p class="error-text" x-text="error" role="alert"></p>
    <button class="btn-primary" @click="send()" :disabled="sending || {{ $workflow->isActive() ? 'false' : 'true' }}"><x-icon name="play" /><span x-text="sending ? 'Memproses…' : 'Kirim event uji'">Kirim event uji</span></button>
  </section>

  <section class="card card-pad" aria-live="polite">
    <h2 class="font-semibold text-white mb-3">Hasil</h2>
    <p class="text-sm text-zinc-500" x-show="!result">Belum ada hasil. Kirim event uji untuk melihat eksekusi tiap step.</p>
    <template x-if="result">
      <div>
        <p class="mb-3 text-sm">Status eksekusi: <span class="font-mono font-bold" :class="badge(result.status)" x-text="result.status"></span></p>
        <ol class="space-y-3">
          <template x-for="s in result.steps" :key="s.order">
            <li class="rounded-md border border-line p-3">
              <div class="flex items-center gap-2 text-sm"><span class="font-mono text-zinc-500" x-text="'#' + s.order"></span><span class="font-medium text-white" x-text="s.name"></span>
                <span class="ml-auto font-mono text-xs font-bold" :class="badge(s.status)" x-text="s.status + (s.skip_reason ? ' · ' + s.skip_reason : '')"></span></div>
              <template x-if="s.content"><div class="mt-2 text-sm"><p class="font-semibold text-zinc-200" x-show="s.subject" x-text="s.subject"></p><pre class="whitespace-pre-wrap break-words font-sans text-zinc-300" x-text="s.content"></pre></div></template>
              <p class="text-xs text-err mt-1" x-show="s.error" x-text="s.error"></p>
            </li>
          </template>
        </ol>
      </div>
    </template>
  </section>
</div>
@endsection
