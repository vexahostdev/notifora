@extends('layouts.admin')
@section('title', 'Plan')
@section('content')
@php $all = ['free' => $free + ['active' => true]] + $plans; @endphp
<x-page-header title="Plan & harga" subtitle="Perubahan berlaku langsung untuk semua workspace — tanpa deploy.">
  <a href="{{ route('admin.coupons.index') }}" class="btn-secondary">Kupon</a>
</x-page-header>

<div class="space-y-4">
@foreach ($all as $key => $p)
  <form method="POST" action="{{ route('admin.plans.update', $key) }}" class="card card-pad">
    @csrf @method('PUT')
    <div class="flex flex-wrap items-center gap-2 mb-3">
      <h2 class="font-semibold text-white">{{ $p['label'] }}</h2>
      <span class="font-mono text-xs text-zinc-500">{{ $key }}</span>
      <span class="text-xs text-zinc-500">· {{ $subs[$key] ?? 0 }} workspace</span>
      @if (in_array($key, $overridden)) <span class="text-[10px] font-mono text-info border border-info/40 rounded px-1">DIUBAH DI DB</span>@endif
      @if (! ($p['active'] ?? true)) <span class="text-[10px] font-mono text-warn border border-warn/40 rounded px-1">NONAKTIF</span>@endif
    </div>
    <div class="grid sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <div class="lg:col-span-2"><label class="label">Nama</label><input name="label" required maxlength="40" class="input" value="{{ $p['label'] }}"></div>
      <div><label class="label">Harga / bln (Rp)</label><input name="price" type="number" min="0" required class="input" value="{{ $p['price'] }}" @readonly($key === 'free')></div>
      <div><label class="label">Event / bln</label><input name="events" type="number" min="1" required class="input" value="{{ $p['events'] }}"></div>
      <div><label class="label">Rate (rpm)</label><input name="rpm" type="number" min="1" required class="input" value="{{ $p['rpm'] }}"></div>
      <div><label class="label">Retensi (hari)</label><input name="retention_days" type="number" min="1" required class="input" value="{{ $p['retention_days'] }}"></div>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3 mt-3">
      <div class="flex items-center gap-4">
        <div class="flex items-center gap-2"><label class="label !mb-0">Urutan</label><input name="sort" type="number" min="0" class="input !w-20" value="{{ $p['sort'] ?? 0 }}"></div>
        @if ($key !== 'free')<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" @checked($p['active'] ?? true) class="accent-[#0033FF]"> Dijual</label>@endif
      </div>
      <div class="flex gap-2">
        <button class="btn-primary btn-sm">Simpan</button>
      </div>
    </div>
  </form>
  @unless (in_array($key, $builtin) || $key === 'free')
    <form method="POST" action="{{ route('admin.plans.destroy', $key) }}" class="-mt-3 text-right" onsubmit="return confirm('Hapus plan {{ $key }}?')">@csrf @method('DELETE')<button class="btn-danger btn-sm">Hapus plan {{ $key }}</button></form>
  @endunless
@endforeach
</div>

<form method="POST" action="{{ route('admin.plans.store') }}" class="card card-pad mt-6">
  @csrf
  <h2 class="font-semibold text-white mb-3">Plan baru</h2>
  <div class="grid sm:grid-cols-3 lg:grid-cols-7 gap-3 items-end">
    <div><label class="label">Key</label><input name="key" required placeholder="enterprise" class="input input-mono" value="{{ old('key') }}">@error('key')<p class="error-text">{{ $message }}</p>@enderror</div>
    <div class="lg:col-span-2"><label class="label">Nama</label><input name="label" required maxlength="40" class="input"></div>
    <div><label class="label">Harga / bln</label><input name="price" type="number" min="0" required class="input"></div>
    <div><label class="label">Event / bln</label><input name="events" type="number" min="1" required class="input"></div>
    <div><label class="label">Rate (rpm)</label><input name="rpm" type="number" min="1" required class="input"></div>
    <div><label class="label">Retensi (hari)</label><input name="retention_days" type="number" min="1" required class="input" value="30"></div>
  </div>
  <input type="hidden" name="active" value="1">
  <button class="btn-primary mt-3">Buat plan</button>
</form>
@endsection
