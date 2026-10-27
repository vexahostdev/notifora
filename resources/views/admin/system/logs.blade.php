@extends('layouts.admin')
@section('title', 'Log aplikasi')
@section('content')
<x-page-header title="Log aplikasi" :subtitle="$file ? $file.' · '.number_format($size / 1024, 0, ',', '.').' KB · menampilkan 120 entri terbaru dari 600 KB terakhir' : 'Belum ada berkas log'">
  @if ($file)<a href="{{ route('admin.system.logs.download') }}" class="btn-secondary">Unduh berkas</a>@endif
</x-page-header>

<form method="GET" class="flex flex-wrap gap-2 mb-4">
  <input name="q" value="{{ $q }}" placeholder="Cari teks (pesan, class, request id)" class="input max-w-sm" aria-label="Cari">
  <select name="level" class="input w-auto" aria-label="Level" onchange="this.form.submit()"><option value="">Semua level</option>@foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] as $l)<option value="{{ $l }}" @selected($level === $l)>{{ $l }}</option>@endforeach</select>
  <button class="btn-secondary">Cari</button>
</form>

<div class="space-y-2">
@forelse ($entries as $e)
  @php $tone = in_array($e['level'], ['error', 'critical', 'alert', 'emergency']) ? 'text-red-300' : ($e['level'] === 'warning' ? 'text-amber-300' : 'text-zinc-400'); @endphp
  <details class="card px-4 py-2.5">
    <summary class="cursor-pointer flex flex-wrap items-baseline gap-x-3 gap-y-1 text-sm">
      <span class="font-mono text-xs text-zinc-500">{{ $e['at'] }}</span>
      <span class="font-mono text-xs font-bold uppercase {{ $tone }}">{{ $e['level'] }}</span>
      <span class="text-zinc-200 break-words min-w-0">{{ $e['message'] }}</span>
    </summary>
    @if ($e['detail'])<pre class="mt-2 text-[11px] leading-relaxed text-zinc-400 whitespace-pre-wrap break-words overflow-x-auto">{{ $e['detail'] }}</pre>@endif
  </details>
@empty
  <div class="card"><x-empty-state icon="list" title="Tidak ada entri" text="Tidak ada log yang cocok dengan filter." /></div>
@endforelse
</div>
@endsection
