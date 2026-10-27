@extends('layouts.admin')
@section('title', $p->number)
@section('content')
@php $rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'); $wib = fn ($d) => $d?->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i') ?? '—'; @endphp
<x-page-header :title="$p->number" :subtitle="$p->external_id">
  <a href="{{ route('admin.payments.index') }}" class="btn-secondary">← Pembayaran</a>
</x-page-header>

<div class="grid lg:grid-cols-3 gap-5">
  <div class="lg:col-span-2 space-y-5">
    <section class="card card-pad">
      <div class="grid sm:grid-cols-3 gap-4 text-sm">
        <div><p class="label">Status</p><x-status-badge :status="$p->status" /></div>
        <div><p class="label">Workspace</p>@if ($p->workspace)<a href="{{ route('admin.tenants.show', $p->workspace) }}" class="text-white hover:text-link">{{ $p->workspace->name }}</a>@else — @endif</div>
        <div><p class="label">Pembeli</p>{{ $p->user?->email ?? '—' }}</div>
        <div><p class="label">Plan</p>{{ $p->planLabel() }} · {{ $p->months }} bulan</div>
        <div><p class="label">Subtotal</p><span class="font-mono">{{ $rp($p->subtotal ?: $p->amount) }}</span></div>
        <div><p class="label">Diskon{{ $p->coupon_code ? ' ('.$p->coupon_code.')' : '' }}</p><span class="font-mono">{{ $rp($p->discount ?? 0) }}</span></div>
        <div><p class="label">Total</p><span class="font-mono font-semibold text-white">{{ $rp($p->amount) }}</span></div>
        <div><p class="label">Metode</p>{{ $p->payment_method ?? '—' }} {{ $p->payment_channel }}</div>
        <div><p class="label">ID invoice Xendit</p><span class="font-mono text-xs break-all">{{ $p->provider_invoice_id ?? '—' }}</span></div>
        <div><p class="label">Dibuat</p>{{ $wib($p->created_at) }}</div>
        <div><p class="label">Kedaluwarsa</p>{{ $wib($p->expires_at) }}</div>
        <div><p class="label">Dibayar</p>{{ $wib($p->paid_at) }}</div>
        <div><p class="label">Plan diterapkan</p>{{ $wib($p->applied_at) }}</div>
        <div><p class="label">Dikembalikan</p>{{ $wib($p->refunded_at) }}</div>
        <div><p class="label">Alasan gagal</p>{{ $p->failure_reason ?? '—' }}</div>
      </div>
      @if ($p->manual_note || $p->refund_note)
        <div class="mt-4 border-t border-line pt-3 text-sm space-y-1">@if ($p->manual_note)<p><span class="text-zinc-500">Catatan manual:</span> {{ $p->manual_note }}</p>@endif @if ($p->refund_note)<p><span class="text-zinc-500">Catatan refund:</span> {{ $p->refund_note }}</p>@endif</div>
      @endif
      @if ($p->invoice_url)<p class="mt-3 text-xs"><a href="{{ $p->invoice_url }}" target="_blank" rel="noopener" class="text-link">Buka halaman invoice Xendit ↗</a></p>@endif
    </section>

    <section class="card overflow-hidden">
      <h2 class="font-semibold text-white px-5 pt-4 pb-3">Event webhook ({{ $events->count() }})</h2>
      <div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="th">Waktu</th><th class="th">Tipe</th><th class="th">Hasil</th><th class="th">Keterangan</th></tr></thead><tbody>
        @forelse ($events as $e)<tr class="tr"><td class="td whitespace-nowrap text-zinc-500">{{ $wib($e->created_at) }}</td><td class="td font-mono text-xs">{{ $e->event_type }}</td><td class="td font-mono text-xs">{{ $e->processing_status }}</td><td class="td text-xs text-zinc-400">{{ $e->processing_error }}</td></tr>
        @empty<tr><td class="td text-zinc-500" colspan="4">Belum ada event.</td></tr>@endforelse
      </tbody></table></div>
    </section>
  </div>

  <aside class="space-y-5">
    @if ($p->isPending())
    <section class="card card-pad space-y-2">
      <h2 class="font-semibold text-white">Rekonsiliasi</h2>
      <form method="POST" action="{{ route('admin.payments.sync', $p->id) }}">@csrf<button class="btn-secondary w-full">Sinkron status dari Xendit</button></form>
      <form method="POST" action="{{ route('admin.payments.resend', $p->id) }}">@csrf<button class="btn-secondary w-full">Kirim ulang tautan invoice</button></form>
    </section>
    @endif

    @if (! $p->isPaid() && $p->status !== \App\Models\Payment::REFUNDED)
    <section class="card card-pad">
      <h2 class="font-semibold text-white mb-1">Tandai lunas manual</h2>
      <p class="hint mb-3">Mis. transfer manual. Plan langsung diterapkan.</p>
      <form method="POST" action="{{ route('admin.payments.mark-paid', $p->id) }}" class="space-y-2" onsubmit="return confirm('Tandai lunas dan terapkan plan?')">@csrf
        <input name="note" required maxlength="255" class="input" placeholder="Bukti / alasan (wajib)" aria-label="Catatan">@error('note')<p class="error-text">{{ $message }}</p>@enderror
        <button class="btn-primary w-full">Tandai lunas</button></form>
    </section>
    @endif

    @if ($p->isPending())
    <section class="card card-pad">
      <h2 class="font-semibold text-white mb-2">Batalkan invoice</h2>
      <form method="POST" action="{{ route('admin.payments.void', $p->id) }}" class="space-y-2" onsubmit="return confirm('Batalkan invoice ini?')">@csrf
        <input name="note" maxlength="255" class="input" placeholder="Alasan (opsional)" aria-label="Alasan">
        <button class="btn-danger w-full">Void invoice</button></form>
    </section>
    @endif

    @if ($p->isPaid())
    <section class="card card-pad">
      <h2 class="font-semibold text-white mb-1">Refund</h2>
      <p class="hint mb-3">Mencatat pengembalian dana. Transfer uangnya dilakukan manual lewat dashboard Xendit.</p>
      <form method="POST" action="{{ route('admin.payments.refund', $p->id) }}" class="space-y-2" onsubmit="return confirm('Catat refund untuk pembayaran ini?')">@csrf
        <input name="note" required maxlength="255" class="input" placeholder="Alasan (wajib)" aria-label="Alasan">@error('note')<p class="error-text">{{ $message }}</p>@enderror
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="revoke_plan" value="1" class="accent-[#0033FF]"> Turunkan workspace ke Free</label>
        <button class="btn-danger w-full">Catat refund</button></form>
    </section>
    @endif
  </aside>
</div>
@endsection
