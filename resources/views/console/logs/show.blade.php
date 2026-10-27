@extends('layouts.app')
@section('title', 'Log ' . $log->id)
@section('content')
<x-page-header title="Detail pesan" :subtitle="$log->id">
  <a href="{{ route('console.logs.index') }}" class="btn-secondary">← Logs</a>
</x-page-header>

<div class="grid lg:grid-cols-3 gap-5">
  <section class="card card-pad space-y-3">
    <div class="flex items-center justify-between"><x-channel-icon :channel="$log->channel_type" /><x-status-badge :status="$log->status" /></div>
    <dl class="text-sm space-y-2">
      <div><dt class="label !mb-0">Tujuan</dt><dd class="font-mono text-xs">{{ $log->destination_masked }}</dd></div>
      <div><dt class="label !mb-0">Workflow</dt><dd>{{ $event?->workflow?->name ?? '—' }}</dd></div>
      <div><dt class="label !mb-0">Provider / ID eksternal</dt><dd class="font-mono text-xs break-all">{{ $log->provider ?? '—' }} · {{ $log->external_message_id ?? '—' }}</dd></div>
      @if ($log->error_code)<div><dt class="label !mb-0">Error</dt><dd class="font-mono text-xs text-red-300 break-words">{{ $log->error_code }}<br>{{ $log->error_message }}</dd></div>@endif
    </dl>
    <h3 class="text-sm font-semibold text-white pt-2">Timeline</h3>
    <ol class="text-xs font-mono space-y-1.5 text-zinc-400">
      <li>● Dibuat — {{ $log->created_at->format('d M H:i:s') }}</li>
      @foreach (['sent_at' => 'Sent', 'delivered_at' => 'Delivered', 'read_at' => 'Read', 'failed_at' => 'Failed'] as $col => $lbl)
        @if ($log->$col)<li class="{{ $col === 'failed_at' ? 'text-red-300' : 'text-emerald-300' }}">● {{ $lbl }} — {{ $log->$col->format('d M H:i:s') }}</li>@endif
      @endforeach
    </ol>
  </section>

  <div class="lg:col-span-2 space-y-5">
    <section class="card card-pad">
      <h2 class="font-semibold text-white mb-2">Pesan ter-render</h2>
      @if ($step?->rendered_subject)<p class="font-semibold text-zinc-100">{{ $step->rendered_subject }}</p>@endif
      <pre class="code whitespace-pre-wrap break-words mt-1">{{ $step?->rendered_content ?? '(tidak tersedia)' }}</pre>
    </section>

    <section class="card overflow-hidden">
      <h2 class="font-semibold text-white px-5 pt-4 pb-3">Percobaan pengiriman</h2>
      @if ($log->attemptsLog->isEmpty())<p class="px-5 pb-5 text-sm text-zinc-500">Belum ada percobaan.</p>@else
      <div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="th">#</th><th class="th">Hasil</th><th class="th">HTTP</th><th class="th">Durasi</th><th class="th">Waktu</th></tr></thead><tbody>
        @foreach ($log->attemptsLog as $a)<tr class="tr"><td class="td font-mono">{{ $a->attempt }}</td><td class="td">{!! $a->success ? '<span class="text-ok">sukses</span>' : '<span class="text-err">gagal'.($a->retryable ? ' (retry)' : '').'</span>' !!}</td><td class="td font-mono">{{ $a->http_status ?? '—' }}</td><td class="td font-mono">{{ $a->duration_ms }} ms</td><td class="td text-zinc-500">{{ $a->created_at->format('H:i:s') }}</td></tr>@endforeach
      </tbody></table></div>
      <details class="px-5 pb-4 pt-2"><summary class="cursor-pointer text-xs text-zinc-400">Raw trace (disamarkan)</summary>
        <pre class="code mt-2">{{ json_encode($log->attemptsLog->pluck('raw_trace', 'attempt'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>
      @endif
    </section>

    @if ($event)
    <section class="card card-pad">
      <h2 class="font-semibold text-white mb-2">Event masukan</h2>
      <pre class="code">{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
      <p class="hint">Event ID: <span class="font-mono">{{ $event->id }}</span></p>
    </section>
    @endif
  </div>
</div>
@endsection
