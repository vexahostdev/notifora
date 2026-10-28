@extends('layouts.app')
@section('title', 'Workflows')
@section('content')
<x-page-header title="Workflows" subtitle="Susun alur notifikasi: filter, delay, kirim, fallback.">
  @if ($currentRole->atLeast(\App\Enums\MemberRole::DEVELOPER))
    <button class="btn-primary" x-data @click="$dispatch('open-create')"><x-icon name="plus" />Workflow baru</button>
  @endif
</x-page-header>

<form method="GET" class="flex flex-wrap gap-2 mb-4">
  <input name="q" value="{{ request('q') }}" placeholder="Cari nama / trigger key" class="input max-w-xs" aria-label="Cari workflow">
  <select name="status" class="input w-auto" aria-label="Status" onchange="this.form.submit()">
    <option value="">Semua status</option>
    @foreach (['ACTIVE', 'DRAFT', 'ARCHIVED'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>@endforeach
  </select>
  <button class="btn-secondary">Cari</button>
</form>

<div class="card overflow-hidden">
  @if ($workflows->isEmpty())
    <x-empty-state icon="workflow" title="Belum ada workflow" text="Buat workflow pertama untuk mulai mengirim notifikasi lewat API." />
  @else
  <div class="overflow-x-auto">
    <table class="w-full">
      <thead><tr><th class="th">Nama</th><th class="th">Trigger key</th><th class="th">Status</th><th class="th">Channel</th><th class="th text-right">Eksekusi 7h</th><th class="th"></th></tr></thead>
      <tbody>
      @foreach ($workflows as $w)
        <tr class="tr">
          <td class="td"><a href="{{ route('console.workflows.edit', $w) }}" class="font-medium text-white hover:text-cobalt">{{ $w->name }}</a>
            @if ($w->is_critical)<span class="ml-1 text-[10px] font-mono text-warn border border-warn/40 rounded px-1">KRITIS</span>@endif
            @if ($w->priority === 'HIGH')<span class="ml-1 text-[10px] font-mono text-info border border-info/40 rounded px-1">HIGH</span>@endif</td>
          <td class="td"><div class="flex items-center gap-2"><code class="font-mono text-xs text-zinc-300">{{ $w->trigger_key }}</code><x-copy-button :text="$w->trigger_key" label="" class="btn-ghost btn-sm" aria-label="Salin trigger key" /></div></td>
          <td class="td"><x-status-badge :status="$w->status" />@if ($w->publishedVersion)<span class="ml-1 text-xs text-zinc-500 font-mono">v{{ $w->publishedVersion->version }}</span>@endif</td>
          <td class="td"><div class="flex gap-1.5 flex-wrap">@foreach (collect($w->publishedVersion?->snapshot['steps'] ?? [])->pluck('channel')->filter()->unique() as $c)<x-channel-icon :channel="$c" class="[&>span:last-child]:hidden" />@endforeach</div></td>
          <td class="td text-right font-mono">{{ number_format($runs[$w->id] ?? 0, 0, ',', '.') }}</td>
          <td class="td text-right whitespace-nowrap">
            @if ($currentRole->atLeast(\App\Enums\MemberRole::DEVELOPER))
              @if ($w->published_version_id && $w->status !== 'ARCHIVED')
                <form method="POST" action="{{ route('console.workflows.toggle', $w) }}" class="inline">@csrf<button class="btn-ghost btn-sm">{{ $w->status === 'ACTIVE' ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
              @endif
              <a href="{{ route('console.workflows.edit', $w) }}" class="btn-secondary btn-sm">Edit</a>
            @endif
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
  @endif
</div>

{{-- Modal buat workflow --}}
<div x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }" @open-create.window="open = true" x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start sm:items-center justify-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-label="Workflow baru">
  <div class="absolute inset-0 bg-black/70" @click="open = false"></div>
  <form method="POST" action="{{ route('console.workflows.store') }}" class="card card-pad relative w-full max-w-md max-h-[calc(100dvh-2rem)] overflow-y-auto space-y-4" x-trap.noscroll="open" @keydown.escape.window="open = false">
    @csrf
    <h2 class="font-semibold text-white">Workflow baru</h2>
    <div><label class="label" for="wn">Nama</label><input id="wn" name="name" required value="{{ old('name') }}" class="input" placeholder="Order Confirmed">@error('name')<p class="error-text">{{ $message }}</p>@enderror</div>
    <div><label class="label" for="wk">Trigger key</label><input id="wk" name="trigger_key" required value="{{ old('trigger_key') }}" class="input input-mono" placeholder="order-confirmed" pattern="[a-z0-9][a-z0-9_\-]*">
      <p class="hint">Dipakai di API sebagai <code>workflow</code>. Huruf kecil, angka, - dan _.</p>@error('trigger_key')<p class="error-text">{{ $message }}</p>@enderror</div>
    <div><label class="label" for="wp">Prioritas</label>
      <select id="wp" name="priority" class="input"><option value="NORMAL" @selected(old('priority', 'NORMAL') === 'NORMAL')>Normal</option><option value="HIGH" @selected(old('priority') === 'HIGH')>High (OTP / instan)</option></select></div>
    <div class="flex justify-end gap-2"><button type="button" class="btn-ghost" @click="open = false">Batal</button><button class="btn-primary">Buat</button></div>
  </form>
</div>
@endsection
