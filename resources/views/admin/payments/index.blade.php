@extends('layouts.admin')
@section('title', 'Pembayaran')
@section('content')
@php $rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'); @endphp
<x-page-header title="Pembayaran" subtitle="Semua invoice Xendit seluruh tenant.">
  <a href="{{ route('admin.payments.events') }}" class="btn-secondary">Event webhook</a>
  <a href="{{ route('admin.payments.export', request()->query()) }}" class="btn-secondary">Ekspor CSV</a>
</x-page-header>

<form method="GET" class="flex flex-wrap gap-2 mb-4">
  <input name="q" value="{{ request('q') }}" placeholder="No. invoice, external ID, workspace, email" class="input max-w-xs" aria-label="Cari">
  <select name="status" class="input w-auto" aria-label="Status" onchange="this.form.submit()"><option value="">Semua status</option>@foreach ($statuses as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select>
  <select name="plan" class="input w-auto" aria-label="Plan" onchange="this.form.submit()"><option value="">Semua plan</option>@foreach (\App\Services\PlanCatalog::all() as $k => $p)<option value="{{ $k }}" @selected(request('plan') === $k)>{{ $p['label'] }}</option>@endforeach</select>
  <input type="date" name="from" value="{{ request('from') }}" class="input w-auto" aria-label="Dari tanggal">
  <input type="date" name="to" value="{{ request('to') }}" class="input w-auto" aria-label="Sampai tanggal">
  <button class="btn-secondary">Filter</button>
</form>
<p class="text-sm text-zinc-400 mb-3">Total lunas pada filter ini: <strong class="text-white font-mono">{{ $rp($paidTotal) }}</strong></p>

<div class="card overflow-hidden">
  @if ($payments->isEmpty())<x-empty-state icon="card" title="Belum ada pembayaran" text="Invoice akan muncul di sini." />@else
  <div class="overflow-x-auto"><table class="w-full">
    <thead><tr><th class="th">Invoice</th><th class="th">Workspace</th><th class="th">Plan</th><th class="th text-right">Jumlah</th><th class="th">Status</th><th class="th">Dibuat</th></tr></thead>
    <tbody>
    @foreach ($payments as $p)
      <tr class="tr">
        <td class="td"><a href="{{ route('admin.payments.show', $p->id) }}" class="font-mono text-xs text-white hover:text-link">{{ $p->number }}</a>@if ($p->coupon_code)<span class="block text-[10px] font-mono text-info">kupon {{ $p->coupon_code }}</span>@endif</td>
        <td class="td">@if ($p->workspace)<a href="{{ route('admin.tenants.show', $p->workspace) }}" class="hover:text-link">{{ $p->workspace->name }}</a>@endif<span class="block text-xs font-mono text-zinc-500">{{ $p->user?->email }}</span></td>
        <td class="td">{{ $p->planLabel() }} · {{ $p->months }} bln</td>
        <td class="td text-right font-mono">{{ $rp($p->amount) }}</td>
        <td class="td"><x-status-badge :status="$p->status" /></td>
        <td class="td text-zinc-500 whitespace-nowrap">{{ $p->created_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  <div class="px-4 py-3 border-t border-line">{{ $payments->links() }}</div>
  @endif
</div>
@endsection
