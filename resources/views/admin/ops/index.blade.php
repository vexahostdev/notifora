@extends('layouts.admin')
@section('title', 'Operasional')
@section('content')
<x-page-header title="Kesehatan sistem" subtitle="Pemeriksaan langsung setiap kali halaman dibuka.">
  <a href="{{ route('admin.ops.failed') }}" class="btn-secondary">Job gagal ({{ $failedCount }})</a>
  <a href="{{ route('admin.ops.providers') }}" class="btn-secondary">Provider</a>
  <a href="{{ route('admin.ops.webhooks') }}" class="btn-secondary">Webhook</a>
  <a href="{{ route('admin.ops.messages') }}" class="btn-secondary">Log pesan</a>
</x-page-header>

<div class="grid md:grid-cols-2 gap-3 mb-6">
  @foreach ($checks as $c)
    @php $tone = ['ok' => 'text-ok', 'warn' => 'text-warn', 'fail' => 'text-err'][$c['status']]; @endphp
    <div class="card card-pad flex items-start gap-3">
      <span class="mt-1 w-2.5 h-2.5 rounded-full shrink-0 {{ ['ok' => 'bg-ok', 'warn' => 'bg-warn', 'fail' => 'bg-err'][$c['status']] }}" aria-hidden="true"></span>
      <div class="min-w-0"><p class="font-medium text-white">{{ $c['name'] }} <span class="text-xs font-mono {{ $tone }}">{{ strtoupper($c['status']) }}</span></p><p class="text-xs text-zinc-400 break-words">{{ $c['detail'] }}</p></div>
    </div>
  @endforeach
</div>

<section class="card card-pad">
  <h2 class="font-semibold text-white mb-3">Antrean <span class="text-xs font-mono text-zinc-500">driver: {{ $driver }}</span></h2>
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
    @foreach ($queues as $q)<div class="rounded-md border border-line px-3 py-2"><p class="text-xs font-mono text-zinc-500">{{ $q['name'] }}</p><p class="text-xl font-mono text-white">{{ $q['size'] ?? '—' }}</p></div>@endforeach
  </div>
  <p class="hint mt-3">Denyut scheduler: {{ $heartbeat ? \Illuminate\Support\Carbon::createFromTimestamp((int) $heartbeat)->diffForHumans() : 'belum ada' }}</p>
</section>
@endsection
