@extends('layouts.app')
@section('title', 'Invoice '.$payment->number)
@section('content')
@php $pending = $payment->isPending(); @endphp
<div x-data="paymentStatus({ url: @js(route('console.billing.payments.status', $payment->id)), pending: @js($pending) })" class="print:text-black">
  <x-page-header :title="'Invoice '.$payment->number" :subtitle="$payment->workspace->name ?? $ws->name">
    <a href="{{ route('console.billing.index') }}" class="btn-secondary print:hidden">← Billing</a>
    @if ($payment->isPaid())<button class="btn-secondary print:hidden" onclick="window.print()">Cetak / simpan PDF</button>@endif
  </x-page-header>

  <section class="card card-pad mb-5 {{ $payment->isPaid() ? 'border-ok/50' : ($pending ? 'border-cobalt/50' : 'border-err/40') }}" aria-live="polite">
    @if ($payment->isPaid())
      <p class="text-lg font-semibold text-emerald-300">✓ Pembayaran diterima</p>
      <p class="text-sm text-zinc-300 mt-1">Plan <strong>{{ $payment->planLabel() }}</strong> aktif sampai <strong>{{ $ws->plan_expires_at?->setTimezone('Asia/Jakarta')->translatedFormat('d F Y, H:i') }} WIB</strong>.</p>
    @elseif ($pending)
      <p class="text-lg font-semibold text-white"><span class="inline-block w-2.5 h-2.5 rounded-full bg-cobalt animate-pulse mr-2"></span>Menunggu pembayaran</p>
      <p class="text-sm text-zinc-300 mt-1">Selesaikan pembayaran sebelum <strong>{{ $payment->expires_at?->setTimezone('Asia/Jakarta')->translatedFormat('d F Y, H:i') }} WIB</strong>. Halaman ini otomatis diperbarui setelah pembayaran terkonfirmasi.</p>
      <div class="flex flex-wrap gap-2 mt-4 print:hidden">
        @if ($payment->invoice_url)<a href="{{ $payment->invoice_url }}" class="btn-primary"><x-icon name="card" />Lanjutkan pembayaran</a>@endif
        @if ($canBuy)
          <form method="POST" action="{{ route('console.billing.payments.cancel', $payment->id) }}" onsubmit="return confirm('Batalkan invoice ini?')">@csrf<button class="btn-ghost">Batalkan invoice</button></form>
        @endif
        @if ($canSimulate && $canBuy)
          <form method="POST" action="{{ route('console.billing.payments.simulate', $payment->id) }}">@csrf<button class="btn-secondary">Simulasikan pembayaran (dev)</button></form>
        @endif
      </div>
    @else
      <p class="text-lg font-semibold text-red-300">{{ $payment->status === 'EXPIRED' ? 'Invoice kedaluwarsa' : ($payment->status === 'CANCELED' ? 'Invoice dibatalkan' : 'Pembayaran gagal') }}</p>
      <p class="text-sm text-zinc-300 mt-1">Tidak ada dana yang ditagihkan. Anda dapat membuat invoice baru kapan saja.</p>
      @if ($canBuy)<a href="{{ route('console.billing.checkout', $payment->plan) }}" class="btn-primary mt-4 print:hidden">Buat invoice baru</a>@endif
    @endif
  </section>

  <section class="card card-pad">
    <div class="flex items-center justify-between mb-3"><h2 class="font-semibold text-white">Rincian</h2><x-status-badge :status="$payment->status" /></div>
    <dl class="grid sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
      <div><dt class="label !mb-0">No. invoice</dt><dd class="font-mono text-xs">{{ $payment->number }}</dd></div>
      <div><dt class="label !mb-0">Dibuat</dt><dd>{{ $payment->created_at->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i') }} WIB</dd></div>
      <div><dt class="label !mb-0">Plan</dt><dd>{{ $payment->planLabel() }} · {{ $payment->months }} bulan</dd></div>
      <div><dt class="label !mb-0">Total</dt><dd class="font-mono text-lg text-white">Rp {{ number_format($payment->amount, 0, ',', '.') }}</dd></div>
      @if ($payment->payment_channel || $payment->payment_method)<div><dt class="label !mb-0">Metode</dt><dd>{{ $payment->payment_channel ?: $payment->payment_method }}</dd></div>@endif
      @if ($payment->paid_at)<div><dt class="label !mb-0">Dibayar</dt><dd>{{ $payment->paid_at->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i') }} WIB</dd></div>@endif
      <div><dt class="label !mb-0">Referensi Xendit</dt><dd class="font-mono text-xs break-all">{{ $payment->provider_invoice_id ?? '—' }}</dd></div>
      <div><dt class="label !mb-0">Penagih</dt><dd>notifora · support@notifora.my.id</dd></div>
    </dl>
  </section>
</div>
@endsection
