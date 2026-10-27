@extends('layouts.admin')
@section('title', 'Kupon')
@section('content')
<x-page-header title="Kupon diskon" subtitle="Dipakai pelanggan saat checkout. Penukaran dihitung dari invoice pending/lunas.">
  <a href="{{ route('admin.plans.index') }}" class="btn-secondary">← Plan</a>
</x-page-header>

<form method="POST" action="{{ route('admin.coupons.store') }}" class="card card-pad mb-6">
  @csrf
  <h2 class="font-semibold text-white mb-3">Kupon baru</h2>
  <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
    <div><label class="label">Kode</label><input name="code" required maxlength="40" class="input input-mono uppercase" value="{{ old('code') }}" placeholder="LAUNCH50">@error('code')<p class="error-text">{{ $message }}</p>@enderror</div>
    <div><label class="label">Jenis</label><select name="type" class="input"><option value="percent">Persen (%)</option><option value="fixed">Nominal (Rp)</option></select></div>
    <div><label class="label">Nilai</label><input name="value" type="number" min="1" required class="input" value="{{ old('value') }}">@error('value')<p class="error-text">{{ $message }}</p>@enderror</div>
    <div><label class="label">Kedaluwarsa</label><input name="expires_at" type="datetime-local" class="input"></div>
    <div><label class="label">Maks. penukaran total</label><input name="max_redemptions" type="number" min="1" class="input" placeholder="tanpa batas"></div>
    <div><label class="label">Maks. per workspace</label><input name="per_workspace_limit" type="number" min="1" max="100" required class="input" value="1"></div>
    <div><label class="label">Minimal durasi (bulan)</label><input name="min_months" type="number" min="1" max="12" required class="input" value="1"></div>
    <div><label class="label">Deskripsi</label><input name="description" maxlength="255" class="input"></div>
  </div>
  <fieldset class="mt-3"><legend class="label">Berlaku untuk plan (kosong = semua)</legend>
    <div class="flex flex-wrap gap-4">@foreach ($plans as $k => $pl)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="plans[]" value="{{ $k }}" class="accent-[#0033FF]"> {{ $pl['label'] }}</label>@endforeach</div></fieldset>
  <button class="btn-primary mt-4">Buat kupon</button>
</form>

<div class="card overflow-hidden">
  @if ($coupons->isEmpty())<x-empty-state icon="card" title="Belum ada kupon" text="Buat kupon pertama di atas." />@else
  <div class="overflow-x-auto"><table class="w-full">
    <thead><tr><th class="th">Kode</th><th class="th">Diskon</th><th class="th">Aturan</th><th class="th text-right">Dipakai</th><th class="th">Status</th><th class="th"></th></tr></thead>
    <tbody>
    @foreach ($coupons as $c)
      <tr class="tr">
        <td class="td font-mono text-white">{{ $c->code }}<span class="block text-xs text-zinc-500 font-sans">{{ $c->description }}</span></td>
        <td class="td font-mono">{{ $c->label() }}</td>
        <td class="td text-xs text-zinc-400">min {{ $c->min_months }} bln · {{ $c->per_workspace_limit }}×/workspace{{ $c->plans ? ' · '.implode(', ', $c->plans) : '' }}{{ $c->expires_at ? ' · s/d '.$c->expires_at->format('d M Y') : '' }}</td>
        <td class="td text-right font-mono">{{ $c->usedCount() }}{{ $c->max_redemptions ? ' / '.$c->max_redemptions : '' }}</td>
        <td class="td"><x-status-badge :status="$c->active ? 'active' : 'disabled'" /></td>
        <td class="td text-right whitespace-nowrap">
          <form method="POST" action="{{ route('admin.coupons.update', $c) }}" class="inline">@csrf @method('PUT')<input type="hidden" name="toggle" value="1"><button class="btn-secondary btn-sm">{{ $c->active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
          <form method="POST" action="{{ route('admin.coupons.destroy', $c) }}" class="inline" onsubmit="return confirm('Hapus kupon {{ $c->code }}?')">@csrf @method('DELETE')<button class="btn-danger btn-sm">Hapus</button></form>
        </td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  @endif
</div>
@endsection
