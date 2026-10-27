@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<x-page-header title="Dashboard" :subtitle="'Environment ' . $currentEnv->type->value">
  <a href="{{ route('console.workflows.index') }}" class="btn-primary"><x-icon name="plus" />Workflow baru</a>
</x-page-header>

@if (collect($onboarding)->contains('done', false))
  <section class="card card-pad mb-6" aria-label="Langkah awal">
    <h2 class="font-semibold text-white mb-3">Mulai dalam 4 langkah</h2>
    <ol class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
      @foreach ($onboarding as $i => $step)
        <li>
          <a href="{{ $step['url'] }}" class="flex items-center gap-3 rounded-md border px-3 py-2.5 text-sm {{ $step['done'] ? 'border-ok/40 bg-ok/10 text-emerald-200' : 'border-line hover:border-cobalt text-zinc-300' }}">
            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-mono font-bold {{ $step['done'] ? 'bg-ok text-black' : 'bg-zinc-800' }}">
              @if ($step['done'])<x-icon name="check" class="w-3.5 h-3.5" />@else {{ $i + 1 }} @endif
            </span>{{ $step['label'] }}
          </a>
        </li>
      @endforeach
    </ol>
  </section>
@endif

<div x-data="dashboardCharts({ url: @js(route('console.dashboard.data')), initial: @js($data) })">
  <div class="flex items-center justify-between mb-3">
    <div class="inline-flex rounded-md border border-line overflow-hidden" role="group" aria-label="Rentang waktu">
      @foreach (['24h' => '24 jam', '7d' => '7 hari', '30d' => '30 hari'] as $r => $l)
        <button type="button" @click="setRange('{{ $r }}')" :class="range === '{{ $r }}' ? 'bg-cobalt text-[#fff]' : 'text-zinc-400 hover:bg-white/5'" class="px-3 py-1.5 text-xs font-medium">{{ $l }}</button>
      @endforeach
    </div>
    <span x-show="loading" x-cloak class="text-xs text-zinc-500">Memuat…</span>
  </div>

  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="card card-pad"><p class="label">Total events</p><p class="text-2xl font-semibold text-white font-mono" x-text="data.kpi.events.toLocaleString('id-ID')">{{ number_format($data['kpi']['events'], 0, ',', '.') }}</p></div>
    <div class="card card-pad"><p class="label">Delivery success</p><p class="text-2xl font-semibold text-white font-mono" x-text="data.kpi.success_rate === null ? '—' : data.kpi.success_rate + '%'">{{ $data['kpi']['success_rate'] === null ? '—' : $data['kpi']['success_rate'].'%' }}</p></div>
    <div class="card card-pad"><p class="label">Avg latency (enqueue→sent)</p><p class="text-2xl font-semibold text-white font-mono" x-text="data.kpi.avg_latency_ms === null ? '—' : data.kpi.avg_latency_ms + ' ms'">{{ $data['kpi']['avg_latency_ms'] === null ? '—' : $data['kpi']['avg_latency_ms'].' ms' }}</p></div>
    <div class="card card-pad"><p class="label">Failed</p><p class="text-2xl font-semibold font-mono" :class="data.kpi.failed ? 'text-err' : 'text-white'" x-text="data.kpi.failed.toLocaleString('id-ID')">{{ $data['kpi']['failed'] }}</p></div>
  </div>

  <div class="grid lg:grid-cols-3 gap-4 mb-6">
    <div class="card card-pad lg:col-span-2"><h2 class="text-sm font-semibold text-white mb-3">Throughput</h2><div class="h-64"><canvas x-ref="line" aria-label="Grafik throughput event"></canvas></div></div>
    <div class="card card-pad"><h2 class="text-sm font-semibold text-white mb-3">Channel share</h2><div class="h-64"><canvas x-ref="donut" aria-label="Distribusi channel"></canvas></div></div>
  </div>

  <div class="grid lg:grid-cols-3 gap-4">
    <div class="card lg:col-span-2 overflow-hidden">
      <h2 class="text-sm font-semibold text-white px-5 pt-4 pb-3">Kegagalan terbaru</h2>
      <template x-if="data.recent_failures.length === 0"><p class="px-5 pb-6 text-sm text-zinc-500">Tidak ada kegagalan pada rentang ini. 🎉</p></template>
      <div class="overflow-x-auto" x-show="data.recent_failures.length">
        <table class="w-full">
          <thead><tr><th class="th">Waktu</th><th class="th">Channel</th><th class="th">Tujuan</th><th class="th">Error</th></tr></thead>
          <tbody>
            <template x-for="f in data.recent_failures" :key="f.id">
              <tr class="tr"><td class="td text-zinc-400" x-text="f.at"></td><td class="td font-mono text-xs" x-text="f.channel"></td><td class="td font-mono text-xs truncate max-w-40" x-text="f.destination"></td>
                <td class="td"><a :href="'{{ url('/console/logs') }}/' + f.id" class="text-red-300 hover:underline font-mono text-xs" x-text="f.error ?? 'error'"></a></td></tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card card-pad">
      <h2 class="text-sm font-semibold text-white mb-3">Kuota bulan ini</h2>
      <p class="font-mono text-lg text-white">{{ number_format($usage['used'], 0, ',', '.') }} <span class="text-zinc-500 text-sm">/ {{ number_format($usage['limit'], 0, ',', '.') }}</span></p>
      <div class="mt-3 h-2 rounded-full bg-zinc-800 overflow-hidden" role="progressbar" aria-valuenow="{{ $usage['pct'] }}" aria-valuemin="0" aria-valuemax="100">
        <div class="h-full {{ $usage['pct'] >= 100 ? 'bg-err' : ($usage['pct'] >= 80 ? 'bg-warn' : 'bg-cobalt') }}" style="width: {{ $usage['pct'] }}%"></div>
      </div>
      <p class="hint">{{ $usage['pct'] }}% terpakai · plan {{ $currentWorkspace->planConfig()['label'] }}</p>
    </div>
  </div>
</div>
@endsection
