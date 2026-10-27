@extends('layouts.admin')
@section('title', 'Workers & scheduler')
@section('content')
<x-page-header title="Workers & scheduler" :subtitle="'Driver antrean: '.$driver.' · ditenagai supervisor bawaan (php artisan notifora:supervise)'" />

@if ($problems)
  <div class="card card-pad mb-4 border-err/50" role="alert">
    <h2 class="font-semibold text-red-300 mb-1">{{ count($problems) }} masalah terdeteksi</h2>
    <ul class="list-disc pl-5 text-sm text-zinc-300 space-y-0.5">@foreach ($problems as $p)<li>{{ $p }}</li>@endforeach</ul>
  </div>
@else
  <div class="card card-pad mb-4 border-ok/40"><p class="text-sm text-emerald-300">Semua worker dan scheduler sehat.</p></div>
@endif

<div class="grid md:grid-cols-3 gap-4 mb-4">
  <section class="card card-pad">
    <p class="text-xs uppercase tracking-wider text-zinc-500">Supervisor</p>
    @if ($supervisor['alive'])
      <p class="text-lg font-semibold text-emerald-300 mt-1">Aktif</p>
      <p class="hint">pid {{ $supervisor['data']['pid'] }} · {{ $supervisor['data']['host'] }} · sejak {{ \Carbon\Carbon::createFromTimestamp($supervisor['data']['started_at'])->diffForHumans() }}</p>
    @else
      <p class="text-lg font-semibold text-amber-300 mt-1">Tidak terdeteksi</p>
      <p class="hint">Jalankan <code>php artisan notifora:supervise</code>, atau gunakan Supervisor/systemd dari folder <code>deploy/</code>.</p>
    @endif
  </section>
  <section class="card card-pad">
    <p class="text-xs uppercase tracking-wider text-zinc-500">Scheduler</p>
    <p class="text-lg font-semibold mt-1 {{ $schedulerAlive ? 'text-emerald-300' : ($driver === 'sync' ? 'text-amber-300' : 'text-red-300') }}">{{ $schedulerAlive ? 'Berdenyut' : ($driver === 'sync' ? 'Tidak aktif (mode dev)' : 'Mati') }}</p>
    <p class="hint">{{ $schedulerBeat ? 'Denyut terakhir '.\Carbon\Carbon::createFromTimestamp($schedulerBeat)->diffForHumans() : 'Belum pernah berdenyut' }}. Mengurus billing sweep, masa tenggang, retensi, backup, dan watchdog.</p>
  </section>
  <section class="card card-pad">
    <p class="text-xs uppercase tracking-wider text-zinc-500">Job gagal / tertua menunggu</p>
    <p class="text-lg font-semibold text-white mt-1">{{ $failed }} gagal · {{ $oldest === null ? 'n/a' : ($oldest < 60 ? $oldest.' dtk' : intdiv($oldest, 60).' mnt') }}</p>
    <p class="hint"><a class="text-link hover:underline" href="{{ route('admin.ops.failed') }}">Kelola job gagal →</a>@if ($cronFallback) · mode cron-worker aktif @endif</p>
  </section>
</div>

<section class="card overflow-hidden mb-4">
  <div class="px-4 py-3 border-b border-line"><h2 class="font-semibold text-white">Antrean</h2></div>
  <div class="overflow-x-auto"><table class="w-full">
    <thead><tr><th class="th">Antrean</th><th class="th">Menunggu</th><th class="th">Proses target</th><th class="th">Denyut worker</th><th class="th">Status</th></tr></thead>
    <tbody>
    @foreach ($queues as $key => $q)
      <tr class="tr">
        <td class="td font-mono text-xs">{{ $q['queue'] }}</td>
        <td class="td">{{ $q['size'] ?? 'n/a' }}</td>
        <td class="td">{{ $procs[$key] ?? 1 }}</td>
        <td class="td text-zinc-500">{{ $q['beat'] ? \Carbon\Carbon::createFromTimestamp($q['beat'])->diffForHumans() : '—' }}</td>
        <td class="td">@if ($driver === 'sync')<span class="badge">sync</span>@elseif ($q['alive'])<span class="badge badge-ok">aktif</span>@else<span class="badge badge-err">tidak ada worker</span>@endif</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
</section>

@if ($supervisor['alive'] && !empty($supervisor['data']['children']))
<section class="card overflow-hidden mb-4">
  <div class="px-4 py-3 border-b border-line"><h2 class="font-semibold text-white">Proses yang diawasi</h2></div>
  <div class="overflow-x-auto"><table class="w-full">
    <thead><tr><th class="th">Nama</th><th class="th">PID</th><th class="th">Status</th><th class="th">Restart</th><th class="th">Mulai</th><th class="th">Kode keluar terakhir</th></tr></thead>
    <tbody>
    @foreach ($supervisor['data']['children'] as $c)
      <tr class="tr">
        <td class="td font-mono text-xs">{{ $c['name'] }}</td>
        <td class="td font-mono text-xs">{{ $c['pid'] ?? '—' }}</td>
        <td class="td">@if ($c['running'])<span class="badge badge-ok">berjalan</span>@else<span class="badge badge-warn">menunggu restart</span>@endif</td>
        <td class="td">{{ $c['restarts'] }}</td>
        <td class="td text-zinc-500">{{ $c['started'] ? \Carbon\Carbon::createFromTimestamp($c['started'])->diffForHumans() : '—' }}</td>
        <td class="td font-mono text-xs">{{ $c['last_exit'] ?? '—' }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
</section>
@endif

<section class="card card-pad">
  <h2 class="font-semibold text-white mb-1">Tindakan</h2>
  <p class="hint mb-3">Semua tindakan dicatat di audit log.</p>
  <div class="flex flex-wrap gap-2">
    <form method="POST" action="{{ route('admin.system.workers.restart') }}" onsubmit="return confirm('Restart semua worker? Job yang sedang berjalan diselesaikan dulu.')">@csrf<button class="btn-secondary">Restart worker</button></form>
    <form method="POST" action="{{ route('admin.system.workers.drain') }}" onsubmit="return confirm('Proses antrean sekarang (maks. 20 detik)?')">@csrf<button class="btn-secondary">Proses antrean sekarang</button></form>
    <form method="POST" action="{{ route('admin.system.workers.schedule') }}">@csrf<button class="btn-secondary">Jalankan scheduler sekali</button></form>
    <form method="POST" action="{{ route('admin.system.tool', 'cache-clear') }}" onsubmit="return confirm('Bersihkan cache aplikasi?')">@csrf<button class="btn-ghost">Bersihkan cache</button></form>
    <form method="POST" action="{{ route('admin.system.tool', 'optimize') }}">@csrf<button class="btn-ghost">Optimize (cache config/route/view)</button></form>
    <form method="POST" action="{{ route('admin.system.tool', 'optimize-clear') }}">@csrf<button class="btn-ghost">Optimize clear</button></form>
  </div>
</section>
@endsection
