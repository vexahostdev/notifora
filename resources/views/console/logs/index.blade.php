@extends('layouts.app')
@section('title', 'Message Logs')
@section('content')
<div x-data="{ auto: false, t: null }" x-init="$watch('auto', v => { clearInterval(t); if (v) t = setInterval(() => { if (!document.hidden) location.reload() }, 5000) })">
<x-page-header title="Message Logs" subtitle="Satu baris per pengiriman channel.">
  <label class="flex items-center gap-2 text-xs text-zinc-400"><input type="checkbox" x-model="auto" class="accent-[#0033FF]"> Auto-refresh 5 dtk</label>
</x-page-header>

<form method="GET" class="card card-pad grid sm:grid-cols-2 lg:grid-cols-6 gap-3 mb-4">
  <div class="lg:col-span-2"><label class="label" for="q">Cari</label><input id="q" name="q" value="{{ request('q') }}" class="input input-mono" placeholder="ID pesan / provider ID / tujuan"></div>
  <div><label class="label" for="status">Status</label><select id="status" name="status[]" class="input"><option value="">Semua</option>@foreach ($statuses as $s)<option value="{{ $s->value }}" @selected(in_array($s->value, (array) request('status')))>{{ $s->value }}</option>@endforeach</select></div>
  <div><label class="label" for="channel">Channel</label><select id="channel" name="channel" class="input"><option value="">Semua</option>@foreach ($channels as $c)<option value="{{ $c->value }}" @selected(request('channel') === $c->value)>{{ $c->label() }}</option>@endforeach</select></div>
  <div><label class="label" for="workflow">Workflow</label><select id="workflow" name="workflow" class="input"><option value="">Semua</option>@foreach ($workflows as $w)<option value="{{ $w->id }}" @selected(request('workflow') === $w->id)>{{ $w->name }}</option>@endforeach</select></div>
  <div><label class="label" for="range">Rentang</label><select id="range" name="range" class="input">@foreach (['1h' => '1 jam', '24h' => '24 jam', '7d' => '7 hari', '30d' => '30 hari', 'all' => 'Semua'] as $k => $l)<option value="{{ $k }}" @selected(request('range', '24h') === $k)>{{ $l }}</option>@endforeach</select></div>
  <div class="sm:col-span-2 lg:col-span-6 flex gap-2"><button class="btn-primary"><x-icon name="filter" />Terapkan</button><a href="{{ route('console.logs.index') }}" class="btn-ghost">Reset</a></div>
</form>

<div class="card overflow-hidden">
  @if ($logs->isEmpty())
    <x-empty-state icon="list" title="Tidak ada log" text="Belum ada pesan pada filter ini. Picu event lewat API atau simulator." />
  @else
  <div class="overflow-x-auto">
    <table class="w-full">
      <thead><tr><th class="th">Waktu</th><th class="th">Workflow</th><th class="th">Channel</th><th class="th">Tujuan</th><th class="th">Status</th><th class="th text-right">Percobaan</th><th class="th"></th></tr></thead>
      <tbody>
      @foreach ($logs as $l)
        <tr class="tr">
          <td class="td text-zinc-400 whitespace-nowrap" title="{{ $l->created_at }}">{{ $l->created_at->diffForHumans() }}</td>
          <td class="td truncate max-w-40" title="{{ $l->workflow?->name }}">{{ $l->workflow?->name ?? '—' }}</td>
          <td class="td"><x-channel-icon :channel="$l->channel_type" /></td>
          <td class="td font-mono text-xs truncate max-w-40">{{ $l->destination_masked }}</td>
          <td class="td"><x-status-badge :status="$l->status" />@if ($l->error_code)<span class="block text-[11px] text-red-300 font-mono mt-0.5">{{ $l->error_code }}</span>@endif</td>
          <td class="td text-right font-mono">{{ $l->attempts }}</td>
          <td class="td text-right"><a href="{{ route('console.logs.show', $l->id) }}" class="text-cobalt hover:underline text-xs">Detail</a></td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
  <div class="px-4 py-3 border-t border-line">{{ $logs->links() }}</div>
  @endif
</div>
</div>
@endsection
