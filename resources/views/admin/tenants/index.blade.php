@extends('layouts.admin')
@section('title', 'Tenants')
@section('content')
<x-page-header title="Tenants" subtitle="Semua workspace pelanggan.">
  <a href="{{ route('admin.reports.workspaces-csv') }}" class="btn-secondary">Ekspor CSV</a>
</x-page-header>

<form method="GET" class="flex flex-wrap gap-2 mb-4">
  <input name="q" value="{{ request('q') }}" placeholder="Nama, slug, ID, email owner / billing" class="input max-w-xs" aria-label="Cari">
  <select name="plan" class="input w-auto" aria-label="Plan" onchange="this.form.submit()"><option value="">Semua plan</option><option value="free" @selected(request('plan') === 'free')>Free</option>@foreach ($plans as $k => $p)<option value="{{ $k }}" @selected(request('plan') === $k)>{{ $p['label'] }}</option>@endforeach</select>
  <select name="status" class="input w-auto" aria-label="Status" onchange="this.form.submit()">
    <option value="">Semua status</option>
    @foreach (['active' => 'Aktif', 'suspended' => 'Ditangguhkan', 'grace' => 'Lewat masa berlaku', 'expiring' => 'Berakhir ≤ 7 hari'] as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach
  </select>
  <select name="sort" class="input w-auto" aria-label="Urutkan" onchange="this.form.submit()">@foreach (['created_at' => 'Terbaru', 'name' => 'Nama', 'plan_expires_at' => 'Masa berlaku'] as $v => $l)<option value="{{ $v }}" @selected(request('sort', 'created_at') === $v)>{{ $l }}</option>@endforeach</select>
  <button class="btn-secondary">Cari</button>
</form>

<div class="card overflow-hidden">
  @if ($tenants->isEmpty())<x-empty-state icon="team" title="Tidak ada tenant" text="Coba ubah filter." />@else
  <div class="overflow-x-auto"><table class="w-full">
    <thead><tr><th class="th">Workspace</th><th class="th">Owner</th><th class="th">Plan</th><th class="th">Berlaku sampai</th><th class="th text-right">Anggota</th><th class="th text-right">Event bln ini</th><th class="th">Dibuat</th></tr></thead>
    <tbody>
    @foreach ($tenants as $t)
      <tr class="tr">
        <td class="td"><a href="{{ route('admin.tenants.show', $t) }}" class="font-medium text-white hover:text-link">{{ $t->name }}</a>
          @if ($t->isSuspended())<span class="ml-1 text-[10px] font-mono text-err border border-err/40 rounded px-1">SUSPEND</span>@endif
          <span class="block text-xs font-mono text-zinc-500">{{ $t->slug }}</span></td>
        <td class="td"><span class="block">{{ $t->owner?->name }}</span><span class="block text-xs font-mono text-zinc-500">{{ $t->owner?->email }}</span></td>
        <td class="td">{{ $t->planConfig()['label'] }}@if ($t->effectivePlanKey() !== $t->plan)<span class="block text-[10px] text-warn font-mono">plan {{ $t->plan }} lewat tenggang</span>@elseif ($t->inGrace())<span class="block text-[10px] text-warn font-mono">MASA TENGGANG</span>@endif @if ($t->custom_limits)<span class="block text-[10px] text-info font-mono">batas kustom</span>@endif</td>
        <td class="td text-zinc-400 whitespace-nowrap">{{ $t->plan_expires_at?->setTimezone('Asia/Jakarta')->translatedFormat('d M Y') ?? '—' }}</td>
        <td class="td text-right font-mono">{{ $t->members_count }}</td>
        <td class="td text-right font-mono">{{ number_format($events[$t->id] ?? 0) }}</td>
        <td class="td text-zinc-500 whitespace-nowrap">{{ $t->created_at->format('d M Y') }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  <div class="px-4 py-3 border-t border-line">{{ $tenants->links() }}</div>
  @endif
</div>
@endsection
