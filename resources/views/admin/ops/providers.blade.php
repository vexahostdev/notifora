@extends('layouts.admin')
@section('title', 'Provider')
@section('content')
<x-page-header title="Provider channel (semua tenant)" subtitle="Status koneksi dan circuit breaker.">
  <a href="{{ route('admin.ops.index') }}" class="btn-secondary">← Operasional</a>
</x-page-header>
<div class="card overflow-hidden">
  @if ($providers->isEmpty())<x-empty-state icon="plug" title="Belum ada provider" text="Tenant belum menghubungkan provider." />@else
  <div class="overflow-x-auto"><table class="w-full">
    <thead><tr><th class="th">Workspace</th><th class="th">Channel</th><th class="th">Provider</th><th class="th">Nama</th><th class="th">Status</th><th class="th">Circuit</th><th class="th">Uji terakhir</th></tr></thead>
    <tbody>
    @foreach ($providers as $p)
      <tr class="tr">
        <td class="td">@if ($p->environment?->workspace)<a href="{{ route('admin.tenants.show', $p->environment->workspace) }}" class="hover:text-link">{{ $p->environment->workspace->name }}</a>@endif<span class="block text-[10px] font-mono text-zinc-500">{{ $p->environment?->type?->value }}</span></td>
        <td class="td"><x-channel-icon :channel="$p->channel" /></td>
        <td class="td font-mono text-xs">{{ $p->provider }}</td><td class="td">{{ $p->name }}</td>
        <td class="td"><x-status-badge :status="$p->status" /></td>
        <td class="td font-mono text-xs {{ $p->circuit_open ? 'text-err' : 'text-zinc-500' }}">{{ $p->circuit_open ? 'TERBUKA' : 'tertutup' }}@if ($p->recent_failures) · {{ $p->recent_failures }} gagal @endif</td>
        <td class="td text-zinc-500 whitespace-nowrap">{{ $p->last_tested_at?->diffForHumans() ?? '—' }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  @endif
</div>
@endsection
