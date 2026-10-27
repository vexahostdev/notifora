@extends('layouts.admin')
@section('title', 'Webhook pelanggan')
@section('content')
<x-page-header title="Webhook pelanggan" :subtitle="$failed24h.' pengiriman gagal dalam 24 jam terakhir'">
  <a href="{{ route('admin.ops.index') }}" class="btn-secondary">← Operasional</a>
</x-page-header>
<form method="GET" class="mb-4"><select name="status" class="input w-auto" aria-label="Status" onchange="this.form.submit()"><option value="">Semua status</option><option value="active" @selected(request('status') === 'active')>Aktif</option><option value="disabled" @selected(request('status') === 'disabled')>Dinonaktifkan</option></select></form>
<div class="card overflow-hidden">
  @if ($subs->isEmpty())<x-empty-state icon="webhook" title="Tidak ada webhook" text="Belum ada langganan webhook." />@else
  <div class="overflow-x-auto"><table class="w-full">
    <thead><tr><th class="th">Workspace</th><th class="th">URL</th><th class="th">Status</th><th class="th text-right">Gagal beruntun</th><th class="th">Dinonaktifkan</th></tr></thead>
    <tbody>
    @foreach ($subs as $s)
      <tr class="tr">
        <td class="td">@if ($s->environment?->workspace)<a href="{{ route('admin.tenants.show', $s->environment->workspace) }}" class="hover:text-link">{{ $s->environment->workspace->name }}</a>@endif</td>
        <td class="td font-mono text-xs break-all max-w-xs">{{ $s->url }}</td>
        <td class="td"><x-status-badge :status="$s->status" /></td>
        <td class="td text-right font-mono {{ $s->consecutive_failures >= 5 ? 'text-err' : '' }}">{{ $s->consecutive_failures }}</td>
        <td class="td text-zinc-500">{{ $s->disabled_at?->diffForHumans() ?? '—' }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  <div class="px-4 py-3 border-t border-line">{{ $subs->links() }}</div>
  @endif
</div>
@endsection
