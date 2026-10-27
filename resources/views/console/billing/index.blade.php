@extends('layouts.app')
@section('title', 'Billing')
@section('content')
<x-page-header title="Billing & plan" subtitle="Kelola plan berbayar workspace. Pembayaran diproses aman oleh Xendit." />

@if (! $configured && app()->environment(['local', 'development', 'staging']))
  <div class="mb-5 rounded-md border border-warn/40 bg-warn/10 px-4 py-3 text-sm text-amber-200" role="status">
    <strong>Xendit belum dikonfigurasi.</strong> Isi <code>XENDIT_SECRET_KEY</code>, <code>XENDIT_WEBHOOK_TOKEN</code>, dan <code>INTERNAL_WEBHOOK_SECRET</code> di file env.
    @if (app()->environment(['local', 'development']))Di development Anda tetap bisa menguji alur lewat tombol “Simulasikan pembayaran” setelah membuat invoice.@endif
  </div>
@endif

<div class="grid lg:grid-cols-3 gap-5 mb-6">
  <section class="card card-pad lg:col-span-2">
    <p class="label">Plan saat ini</p>
    <div class="flex flex-wrap items-center gap-3">
      <p class="text-2xl font-semibold text-white">{{ $ws->planConfig()['label'] }}</p>
      @if ($effective === 'free')<x-status-badge status="DRAFT" class="!hidden" /><span class="text-xs font-mono px-2 py-0.5 rounded border border-line text-zinc-400">GRATIS</span>
      @elseif ($ws->inGrace())<span class="text-xs font-mono px-2 py-0.5 rounded border border-warn/50 text-amber-300">MASA TENGGANG</span>
      @elseif ($active)<x-status-badge status="ACTIVE" />@endif
    </div>
    <dl class="grid sm:grid-cols-3 gap-4 mt-4 text-sm">
      <div><dt class="label !mb-0">Berlaku sampai</dt><dd class="font-mono text-xs">{{ $ws->plan_expires_at ? $ws->plan_expires_at->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i').' WIB' : ($effective === 'free' ? '—' : 'Tanpa batas waktu') }}</dd></div>
      <div><dt class="label !mb-0">Kuota event / bulan</dt><dd class="font-mono text-xs">{{ number_format($ws->planConfig()['events'], 0, ',', '.') }}</dd></div>
      <div><dt class="label !mb-0">Rate limit</dt><dd class="font-mono text-xs">{{ number_format($ws->planConfig()['rpm'], 0, ',', '.') }} req/menit</dd></div>
    </dl>
    @if ($ws->inGrace())
      <p class="hint mt-3 text-amber-300">Plan sudah berakhir. Batas plan sebelumnya masih berlaku sampai {{ $ws->graceEndsAt()->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i') }} WIB, lalu workspace turun ke Free.</p>
    @endif
    <div class="mt-4">
      <div class="flex justify-between text-xs text-zinc-400 mb-1"><span>Pemakaian production bulan ini</span><span class="font-mono">{{ number_format($usedProd, 0, ',', '.') }} / {{ number_format($limitProd, 0, ',', '.') }}</span></div>
      @php $pct = $limitProd ? min(100, round($usedProd / $limitProd * 100)) : 0; @endphp
      <div class="h-2 rounded-full bg-zinc-800 overflow-hidden"><div class="h-full {{ $pct >= 100 ? 'bg-err' : ($pct >= 80 ? 'bg-warn' : 'bg-cobalt') }}" style="width: {{ $pct }}%"></div></div>
    </div>
  </section>
  <section class="card card-pad">
    <p class="label">Bantuan</p>
    <p class="text-sm text-zinc-300">Butuh invoice khusus, kuota di atas plan Scale, atau pembayaran transfer manual?</p>
    <a href="mailto:{{ config('notifora.support_email') }}?subject=Billing%20Notifora%20-%20{{ urlencode($ws->name) }}" class="btn-secondary mt-3">Hubungi support</a>
  </section>
</div>

<h2 class="text-sm font-semibold text-white mb-3">Pilih plan</h2>
<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
  @foreach ($plans as $key => $p)
    @php $current = $effective === $key; @endphp
    <article class="card card-pad flex flex-col {{ $current ? 'border-cobalt' : '' }}">
      <div class="flex items-center justify-between"><h3 class="font-semibold text-white">{{ $p['label'] }}</h3>@if ($current)<span class="text-[10px] font-mono text-cobalt border border-cobalt/50 rounded px-1.5">AKTIF</span>@endif</div>
      <p class="mt-2 font-mono text-xl text-white">Rp {{ number_format($p['price'], 0, ',', '.') }}<span class="text-xs text-zinc-500">/bln</span></p>
      <ul class="mt-3 space-y-1 text-sm text-zinc-400 flex-1">
        <li>{{ number_format($p['events'], 0, ',', '.') }}{{ $key === 'scale' ? '+' : '' }} event / bulan</li>
        <li>Log {{ $p['retention_days'] }} hari</li>
        <li>{{ number_format($p['rpm'], 0, ',', '.') }} req/menit</li>
      </ul>
      @php $lower = $active && $p['price'] < ($plans[$ws->plan]['price'] ?? 0); @endphp
      @if ($canBuy && $lower)
        <button class="btn-secondary mt-4" disabled title="Tersedia setelah plan aktif berakhir">Plan lebih rendah</button>
      @elseif ($canBuy)
        <a href="{{ route('console.billing.checkout', $key) }}" class="{{ $current ? 'btn-secondary' : 'btn-primary' }} mt-4">{{ $current ? 'Perpanjang' : 'Pilih plan' }}</a>
      @else
        <p class="hint mt-4">Hanya Owner yang dapat membeli plan.</p>
      @endif
    </article>
  @endforeach
</div>

<div class="card overflow-hidden">
  <h2 class="font-semibold text-white px-5 pt-4 pb-3">Riwayat pembayaran</h2>
  @if ($payments->isEmpty())
    <x-empty-state icon="card" title="Belum ada pembayaran" text="Invoice dan bukti pembayaran Anda akan muncul di sini." />
  @else
  <div class="overflow-x-auto"><table class="w-full">
    <thead><tr><th class="th">Invoice</th><th class="th">Plan</th><th class="th text-right">Total</th><th class="th">Status</th><th class="th">Tanggal</th><th class="th"></th></tr></thead>
    <tbody>
    @foreach ($payments as $pay)
      <tr class="tr">
        <td class="td font-mono text-xs">{{ $pay->number }}</td>
        <td class="td">{{ $pay->planLabel() }} · {{ $pay->months }} bln</td>
        <td class="td text-right font-mono">Rp {{ number_format($pay->amount, 0, ',', '.') }}</td>
        <td class="td"><x-status-badge :status="$pay->status" /></td>
        <td class="td text-zinc-500 whitespace-nowrap">{{ $pay->created_at->diffForHumans() }}</td>
        <td class="td text-right"><a href="{{ route('console.billing.payments.show', $pay->id) }}" class="text-cobalt hover:underline text-xs">{{ $pay->isPending() ? 'Lanjutkan' : 'Detail' }}</a></td>
      </tr>
    @endforeach
    </tbody></table></div>
  @endif
</div>
@endsection
