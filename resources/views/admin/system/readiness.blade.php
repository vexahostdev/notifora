@extends('layouts.admin')
@section('title', 'Kesiapan production')
@section('content')
<x-page-header title="Kesiapan production" :subtitle="'Skor '.$sum['score'].'% · '.$sum['ok'].' lulus, '.$sum['warn'].' peringatan, '.$sum['fail'].' gagal dari '.$sum['total'].' pemeriksaan'">
  <a href="{{ route('admin.system.readiness') }}" class="btn-secondary">Periksa ulang</a>
</x-page-header>

<div class="card card-pad mb-4">
  <div class="h-2 rounded-full bg-line overflow-hidden"><div class="h-full {{ $sum['fail'] ? 'bg-err' : ($sum['warn'] ? 'bg-warn' : 'bg-ok') }}" style="width: {{ $sum['score'] }}%"></div></div>
  <p class="hint mt-2">Pemeriksaan hanya membaca konfigurasi dan status; nilai rahasia tidak pernah ditampilkan. Selesaikan semua yang <strong class="text-red-300">gagal</strong> sebelum go-live.</p>
</div>

@foreach ($groups as $group => $rows)
<section class="card overflow-hidden mb-4">
  <div class="px-4 py-3 border-b border-line"><h2 class="font-semibold text-white">{{ $group }}</h2></div>
  <ul class="divide-y divide-line">
    @foreach ($rows as $r)
      <li class="px-4 py-3 flex gap-3">
        <span class="mt-0.5 shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[11px] font-bold {{ ['ok' => 'bg-ok/20 text-emerald-300', 'warn' => 'bg-warn/20 text-amber-300', 'fail' => 'bg-err/20 text-red-300'][$r['status']] }}" aria-label="{{ $r['status'] }}">{{ ['ok' => '✓', 'warn' => '!', 'fail' => '✕'][$r['status']] }}</span>
        <div class="min-w-0">
          <p class="text-sm text-white font-medium">{{ $r['name'] }}</p>
          <p class="text-xs text-zinc-400 break-words">{{ $r['detail'] }}</p>
          @if ($r['status'] !== 'ok' && $r['fix'])<p class="text-xs text-amber-200/90 mt-1">→ {{ $r['fix'] }}</p>@endif
        </div>
      </li>
    @endforeach
  </ul>
</section>
@endforeach
@endsection
