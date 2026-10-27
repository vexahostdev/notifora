@extends('layouts.admin')
@section('title', 'Log pesan global')
@section('content')
<x-page-header title="Log pesan global" subtitle="Semua pesan lintas tenant. Tujuan sudah dimasking.">
  <a href="{{ route('admin.ops.index') }}" class="btn-secondary">← Operasional</a>
</x-page-header>
<form method="GET" class="flex flex-wrap gap-2 mb-4">
  <input name="q" value="{{ request('q') }}" placeholder="ID pesan, ID eksternal, tujuan" class="input max-w-xs" aria-label="Cari">
  <select name="status" class="input w-auto" aria-label="Status" onchange="this.form.submit()"><option value="">Semua status</option>@foreach ($statuses as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->value }}</option>@endforeach</select>
  <select name="channel" class="input w-auto" aria-label="Channel" onchange="this.form.submit()"><option value="">Semua channel</option>@foreach (['WHATSAPP', 'EMAIL', 'TELEGRAM', 'IN_APP'] as $c)<option value="{{ $c }}" @selected(request('channel') === $c)>{{ $c }}</option>@endforeach</select>
  <select name="error" class="input w-auto" aria-label="Kode error" onchange="this.form.submit()"><option value="">Semua error</option>@foreach ($errors as $e)<option value="{{ $e }}" @selected(request('error') === $e)>{{ $e }}</option>@endforeach</select>
  <select name="range" class="input w-auto" aria-label="Rentang" onchange="this.form.submit()">@foreach (['1h' => '1 jam', '24h' => '24 jam', '7d' => '7 hari', 'all' => 'Semua'] as $v => $l)<option value="{{ $v }}" @selected(request('range', '24h') === $v)>{{ $l }}</option>@endforeach</select>
  <button class="btn-secondary">Cari</button>
</form>
<div class="card overflow-hidden">
  @if ($logs->isEmpty())<x-empty-state icon="list" title="Tidak ada pesan" text="Ubah filter atau rentang waktu." />@else
  <div class="overflow-x-auto"><table class="w-full">
    <thead><tr><th class="th">Waktu</th><th class="th">Workspace</th><th class="th">Channel</th><th class="th">Tujuan</th><th class="th">Status</th><th class="th">Error</th></tr></thead>
    <tbody>
    @foreach ($logs as $l)
      <tr class="tr">
        <td class="td whitespace-nowrap text-zinc-500">{{ $l->created_at->setTimezone('Asia/Jakarta')->format('d M H:i:s') }}<span class="block text-[10px] font-mono">{{ $l->id }}</span></td>
        <td class="td">@if ($l->environment?->workspace)<a href="{{ route('admin.tenants.show', $l->environment->workspace) }}" class="hover:text-link">{{ $l->environment->workspace->name }}</a>@endif</td>
        <td class="td"><x-channel-icon :channel="$l->channel_type" /> <span class="font-mono text-xs">{{ $l->provider }}</span></td>
        <td class="td font-mono text-xs">{{ $l->destination_masked }}</td>
        <td class="td"><x-status-badge :status="$l->status" /></td>
        <td class="td text-xs text-red-300 max-w-xs break-words">{{ $l->error_code }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  <div class="px-4 py-3 border-t border-line">{{ $logs->links() }}</div>
  @endif
</div>
@endsection
