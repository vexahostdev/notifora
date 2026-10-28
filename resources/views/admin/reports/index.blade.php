@extends('layouts.admin')
@section('title', 'Laporan')
@section('content')
@php $rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'); $maxRev = max(1, collect($revenue['months'])->max('revenue')); @endphp
<x-page-header title="Laporan & MRR" subtitle="Pendapatan 12 bulan terakhir dan distribusi pelanggan.">
  <a href="{{ route('admin.reports.workspaces-csv') }}" class="btn-secondary">Ekspor workspace (CSV)</a>
  <a href="{{ route('admin.payments.export') }}" class="btn-secondary">Ekspor pembayaran (CSV)</a>
</x-page-header>

<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="card card-pad"><p class="label">MRR</p><p class="text-2xl font-mono font-semibold text-white">{{ $rp($mrr) }}</p></div>
  <div class="card card-pad"><p class="label">ARR (perkiraan)</p><p class="text-2xl font-mono font-semibold text-white">{{ $rp($arr) }}</p></div>
  <div class="card card-pad"><p class="label">Pendapatan 12 bln</p><p class="text-2xl font-mono font-semibold text-white">{{ $rp($revenue['total']) }}</p></div>
  <div class="card card-pad"><p class="label">Konversi berbayar</p><p class="text-2xl font-mono font-semibold text-white">{{ $total ? round($paidWorkspaces / $total * 100, 1) : 0 }}%</p><p class="hint">{{ $paidWorkspaces }} dari {{ $total }} workspace</p></div>
</div>

<div class="grid lg:grid-cols-3 gap-5 mb-6">
  <section class="card overflow-hidden lg:col-span-2">
    <h2 class="font-semibold text-white px-5 pt-4 pb-3">Per bulan</h2>
    <div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="th">Bulan</th><th class="th">Pendapatan</th><th class="th text-right">Transaksi</th><th class="th text-right">Diskon</th><th class="th text-right">Pendaftar</th></tr></thead><tbody>
      @foreach (array_reverse($revenue['months'], true) as $m => $r)
        <tr class="tr"><td class="td font-mono text-xs">{{ $m }}</td><td class="td"><div class="flex items-center gap-2"><div class="h-2 rounded bg-cobalt" style="width: {{ max(2, round($r['revenue'] / $maxRev * 140)) }}px" aria-hidden="true"></div><span class="font-mono text-xs">{{ $rp($r['revenue']) }}</span></div></td><td class="td text-right font-mono">{{ $r['count'] }}</td><td class="td text-right font-mono text-zinc-400">{{ $rp($r['discount']) }}</td><td class="td text-right font-mono">{{ $r['signups'] }}</td></tr>
      @endforeach
    </tbody></table></div>
  </section>
  <div class="space-y-5">
    <section class="card card-pad"><h2 class="font-semibold text-white mb-2">Distribusi plan</h2>
      <ul class="space-y-1.5 text-sm">@foreach ($distribution as $plan => $c)<li class="flex justify-between"><span class="font-mono text-xs">{{ $plan }}</span><span class="font-mono">{{ $c }}</span></li>@endforeach</ul></section>
    <section class="card card-pad"><h2 class="font-semibold text-white mb-2">Pendapatan per plan</h2>
      <ul class="space-y-1.5 text-sm">@forelse ($revenue['by_plan'] as $plan => $v)<li class="flex justify-between"><span class="font-mono text-xs">{{ $plan }}</span><span class="font-mono">{{ $rp($v) }}</span></li>@empty<li class="text-zinc-500">Belum ada.</li>@endforelse</ul></section>
  </div>
</div>

<div class="grid lg:grid-cols-2 gap-5">
  <section class="card overflow-hidden"><h2 class="font-semibold text-white px-5 pt-4 pb-3">Tenant teratas bulan ini</h2>
    <div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="th">Workspace</th><th class="th">Plan</th><th class="th text-right">Event</th><th class="th text-right">Gagal</th></tr></thead><tbody>
      @forelse ($top as $t)<tr class="tr"><td class="td"><a href="{{ route('admin.tenants.show', $t->id) }}" class="text-white hover:text-link">{{ $t->name }}</a></td><td class="td font-mono text-xs">{{ $t->plan }}</td><td class="td text-right font-mono">{{ number_format($t->events) }}</td><td class="td text-right font-mono">{{ number_format($t->failed) }}</td></tr>
      @empty<tr><td class="td text-zinc-500" colspan="4">Belum ada penggunaan.</td></tr>@endforelse
    </tbody></table></div></section>
  <section class="card overflow-hidden"><h2 class="font-semibold text-white px-5 pt-4 pb-3">Segera berakhir (14 hari)</h2>
    <div class="overflow-x-auto"><table class="w-full"><tbody>
      @forelse ($expiring as $w)<tr class="tr"><td class="td"><a href="{{ route('admin.tenants.show', $w) }}" class="text-white hover:text-link">{{ $w->name }}</a></td><td class="td font-mono text-xs">{{ $w->plan }}</td><td class="td text-zinc-400 whitespace-nowrap">{{ $w->plan_expires_at->format('d M Y') }}</td></tr>
      @empty<tr><td class="td text-zinc-500">Tidak ada.</td></tr>@endforelse
    </tbody></table></div></section>
</div>
@endsection
