@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
@php $rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'); @endphp
<x-page-header title="Dashboard platform" subtitle="Ringkasan seluruh tenant Notifora." />

@if ($alerts)
  <section class="mb-6 grid gap-2" aria-label="Perlu perhatian">
    @foreach ($alerts as $a)
      <a href="{{ $a['url'] }}" class="flex items-center gap-3 rounded-md border px-4 py-2.5 text-sm {{ ['danger' => 'border-err/40 bg-err/10 text-red-200', 'warn' => 'border-warn/40 bg-warn/10 text-amber-200', 'info' => 'border-info/40 bg-info/10 text-blue-200'][$a['tone']] }}">
        <x-icon name="alert" class="w-4 h-4 shrink-0" /><span class="flex-1">{{ $a['text'] }}</span><span class="text-xs opacity-70">Tinjau →</span>
      </a>
    @endforeach
  </section>
@endif

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <a href="{{ route('admin.tenants.index') }}" class="card card-pad hover:border-zinc-600"><p class="label">Workspace</p><p class="text-2xl font-semibold text-white font-mono">{{ number_format($kpi['workspaces']) }}</p><p class="hint">{{ $kpi['paid'] }} berbayar · <span class="{{ $kpi['suspended'] ? 'text-err' : '' }}">{{ $kpi['suspended'] }} ditangguhkan</span></p></a>
  <a href="{{ route('admin.users.index') }}" class="card card-pad hover:border-zinc-600"><p class="label">Pengguna</p><p class="text-2xl font-semibold text-white font-mono">{{ number_format($kpi['users']) }}</p><p class="hint">+{{ $kpi['new_users_7d'] }} 7 hari · {{ $kpi['admins'] }} operator</p></a>
  <div class="card card-pad"><p class="label">Event hari ini</p><p class="text-2xl font-semibold text-white font-mono">{{ number_format($kpi['events_today']) }}</p><p class="hint">{{ number_format($kpi['events_month']) }} bulan ini</p></div>
  <div class="card card-pad"><p class="label">Sukses kirim (7 hari)</p><p class="text-2xl font-semibold text-white font-mono">{{ $kpi['success_rate_7d'] === null ? '—' : $kpi['success_rate_7d'].'%' }}</p></div>
  <a href="{{ route('admin.reports.index') }}" class="card card-pad hover:border-zinc-600"><p class="label">Perkiraan MRR</p><p class="text-2xl font-semibold text-white font-mono">{{ $rp($kpi['mrr']) }}</p><p class="hint">dari plan berbayar aktif</p></a>
  <a href="{{ route('admin.payments.index', ['status' => 'PAID']) }}" class="card card-pad hover:border-zinc-600"><p class="label">Pendapatan bulan ini</p><p class="text-2xl font-semibold text-white font-mono">{{ $rp($kpi['revenue_month']) }}</p><p class="hint">{{ $rp($kpi['revenue_30d']) }} 30 hari</p></a>
  <a href="{{ route('admin.payments.index', ['status' => 'PENDING']) }}" class="card card-pad hover:border-zinc-600"><p class="label">Invoice menunggu</p><p class="text-2xl font-semibold text-white font-mono">{{ $kpi['pending_payments'] }}</p></a>
  <a href="{{ route('admin.ops.index') }}" class="card card-pad hover:border-zinc-600"><p class="label">Kesehatan sistem</p><p class="text-2xl font-semibold {{ $alerts ? 'text-warn' : 'text-ok' }}">{{ $alerts ? count($alerts).' perhatian' : 'Normal' }}</p></a>
</div>

<div class="grid lg:grid-cols-3 gap-4 mb-6" x-data="adminCharts(@js($series))">
  <div class="card card-pad"><h2 class="text-sm font-semibold text-white mb-3">Event / hari (30 hari)</h2><div class="h-52"><canvas x-ref="events" aria-label="Grafik event harian"></canvas></div></div>
  <div class="card card-pad"><h2 class="text-sm font-semibold text-white mb-3">Pendapatan / hari (Rp)</h2><div class="h-52"><canvas x-ref="revenue" aria-label="Grafik pendapatan harian"></canvas></div></div>
  <div class="card card-pad"><h2 class="text-sm font-semibold text-white mb-3">Pendaftaran / hari</h2><div class="h-52"><canvas x-ref="signups" aria-label="Grafik pendaftaran harian"></canvas></div></div>
</div>

<div class="grid lg:grid-cols-2 gap-4">
  <section class="card overflow-hidden">
    <h2 class="font-semibold text-white px-5 pt-4 pb-3">Pendaftar terbaru</h2>
    <div class="overflow-x-auto"><table class="w-full"><tbody>
      @forelse ($recent_signups as $u)
        <tr class="tr"><td class="td"><a href="{{ route('admin.users.show', $u) }}" class="text-white hover:text-link">{{ $u->name }}</a><span class="block text-xs font-mono text-zinc-500">{{ $u->email }}</span></td><td class="td text-right text-zinc-500 whitespace-nowrap">{{ $u->created_at->diffForHumans() }}</td></tr>
      @empty<tr><td class="td text-zinc-500">Belum ada.</td></tr>@endforelse
    </tbody></table></div>
  </section>
  <section class="card overflow-hidden">
    <h2 class="font-semibold text-white px-5 pt-4 pb-3">Pembayaran terbaru</h2>
    <div class="overflow-x-auto"><table class="w-full"><tbody>
      @forelse ($recent_payments as $p)
        <tr class="tr"><td class="td"><a href="{{ route('admin.payments.show', $p->id) }}" class="font-mono text-xs text-white hover:text-link">{{ $p->number }}</a><span class="block text-xs text-zinc-500">{{ $p->workspace?->name }} · {{ $p->planLabel() }}</span></td><td class="td text-right font-mono">{{ $rp($p->amount) }}</td><td class="td"><x-status-badge :status="$p->status" /></td></tr>
      @empty<tr><td class="td text-zinc-500">Belum ada.</td></tr>@endforelse
    </tbody></table></div>
  </section>
</div>
@endsection
