@extends('layouts.app')
@section('title', 'Usage')
@section('content')
<x-page-header title="Usage & plan" :subtitle="'Environment ' . $currentEnv->type->value . ' · bulan ' . now()->translatedFormat('F Y')" />

<div class="grid lg:grid-cols-3 gap-5 mb-6">
  <section class="card card-pad lg:col-span-2">
    <h2 class="font-semibold text-white mb-1">Event bulan ini</h2>
    <p class="font-mono text-2xl text-white">{{ number_format($used, 0, ',', '.') }} <span class="text-sm text-zinc-500">/ {{ number_format($limit, 0, ',', '.') }}</span></p>
    <div class="mt-3 h-2 rounded-full bg-zinc-800 overflow-hidden" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full {{ $pct >= 100 ? 'bg-err' : ($pct >= 80 ? 'bg-warn' : 'bg-cobalt') }}" style="width: {{ $pct }}%"></div></div>
    <p class="hint">{{ $currentEnv->isProduction() ? 'Kuota plan '.$plan['label'] : 'Kuota gratis environment Development' }}. Lewat 100% masih ada toleransi 10% sebelum event ditolak (402).</p>
  </section>
  <section class="card card-pad">
    <h2 class="font-semibold text-white mb-1">Plan saat ini</h2>
    <p class="text-xl font-semibold text-cobalt">{{ $plan['label'] }}</p>
    <p class="hint">Log {{ $plan['retention_days'] }} hari · {{ number_format($plan['rpm'], 0, ',', '.') }} req/menit</p>
    <p class="hint mt-3"><a class="text-cobalt hover:underline" href="{{ route('console.billing.index') }}">Upgrade atau perpanjang plan →</a></p>
  </section>
</div>

<div class="card overflow-hidden mb-6">
  <h2 class="font-semibold text-white px-5 pt-4 pb-3">Harian</h2>
  @if (empty($daily))<p class="px-5 pb-6 text-sm text-zinc-500">Belum ada pemakaian bulan ini.</p>@else
  <div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="th">Tanggal</th><th class="th text-right">Events</th><th class="th text-right">Terkirim</th><th class="th text-right">Gagal</th></tr></thead><tbody>
    @foreach ($daily as $d)<tr class="tr"><td class="td">{{ $d['date'] }}</td><td class="td text-right font-mono">{{ number_format($d['events'], 0, ',', '.') }}</td><td class="td text-right font-mono">{{ number_format($d['sent'], 0, ',', '.') }}</td><td class="td text-right font-mono {{ $d['failed'] ? 'text-err' : '' }}">{{ number_format($d['failed'], 0, ',', '.') }}</td></tr>@endforeach
  </tbody></table></div>@endif
</div>

<div class="card overflow-hidden">
  <h2 class="font-semibold text-white px-5 pt-4 pb-3">Daftar plan</h2>
  <div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="th">Plan</th><th class="th text-right">Event / bln</th><th class="th text-right">Harga / bln</th><th class="th text-right">Log</th></tr></thead><tbody>
    @foreach ($plans as $k => $p)<tr class="tr {{ $k === $planKey ? 'bg-cobalt/10' : '' }}"><td class="td text-white">{{ $p['label'] }}</td><td class="td text-right font-mono">{{ number_format($p['events'], 0, ',', '.') }}</td><td class="td text-right font-mono">Rp {{ number_format($p['price'], 0, ',', '.') }}</td><td class="td text-right font-mono">{{ $p['retention_days'] }} hari</td></tr>@endforeach
  </tbody></table></div>
</div>
@endsection
