@extends('layouts.docs', ['slug' => null])
@section('title', 'Dokumentasi Teknis')
@section('content')
<div class="grid xl:grid-cols-[1fr_16rem] gap-8 xl:max-w-none">
  <div>
    <span class="inline-flex items-center gap-2 rounded border border-line bg-surface-2 px-2 py-0.5 text-[10px] font-mono font-bold tracking-wider uppercase text-zinc-400">Knowledge Base <span class="text-white">Notifora</span></span>
    <h1 class="mt-3 text-3xl sm:text-4xl font-bold text-white tracking-tight">Dokumentasi Teknis Notifora</h1>
    <p class="mt-3 text-zinc-400 leading-7 max-w-2xl">Panduan mengintegrasikan, mengonfigurasi, dan mengoperasikan Notifora: satu API untuk WhatsApp, Email, Telegram, dan In-App Inbox. Pilih topik di bawah, atau tekan <kbd class="font-mono text-xs border border-line rounded px-1.5 py-0.5">Ctrl K</kbd> untuk mencari.</p>

    <div class="mt-6 flex flex-wrap gap-3">
      <a href="{{ route('docs.show', 'mulai-cepat') }}#quickstart" class="btn-primary">Mulai dalam 5 menit →</a>
      <a href="{{ route('docs.show', 'referensi-api') }}#trigger" class="btn-secondary">Referensi API</a>
    </div>

    <div class="mt-10 grid sm:grid-cols-2 gap-4">
      @foreach ($sections as $slug => $s)
        <section class="card card-pad hover:border-zinc-600 transition-colors">
          <a href="{{ route('docs.show', $slug) }}" class="flex items-center gap-2 font-semibold text-white hover:text-link"><x-icon :name="$s['icon']" class="w-4 h-4 text-cobalt" />{{ $s['title'] }}</a>
          <p class="mt-1.5 text-[13px] leading-6 text-zinc-400">{{ $s['summary'] }}</p>
          <ul class="mt-3 space-y-1">
            @foreach ($s['articles'] as [$aid, $atitle])
              <li class="flex gap-2 text-[13px]"><span class="text-zinc-600">•</span><a href="{{ route('docs.show', $slug) }}#{{ $aid }}" class="text-zinc-300 hover:text-link">{{ $atitle }}</a></li>
            @endforeach
          </ul>
        </section>
      @endforeach
    </div>
  </div>

  <aside class="hidden xl:block pt-1">
    <div class="card card-pad sticky top-32">
      <p class="text-[11px] font-mono font-bold uppercase tracking-wider text-zinc-500 mb-3">Shortcut keyboard</p>
      <dl class="space-y-2 text-sm">
        <div class="flex items-center justify-between"><dt class="text-zinc-400">Pencarian cepat</dt><dd><kbd class="font-mono text-[10px] border border-line rounded px-1.5 py-0.5">Ctrl K</kbd></dd></div>
        <div class="flex items-center justify-between"><dt class="text-zinc-400">Buka pencarian</dt><dd><kbd class="font-mono text-[10px] border border-line rounded px-1.5 py-0.5">/</kbd></dd></div>
        <div class="flex items-center justify-between"><dt class="text-zinc-400">Tutup modal</dt><dd><kbd class="font-mono text-[10px] border border-line rounded px-1.5 py-0.5">ESC</kbd></dd></div>
      </dl>
      <hr class="my-4 border-line">
      <p class="text-[11px] font-mono font-bold uppercase tracking-wider text-zinc-500 mb-2">Base URL</p>
      <code class="block font-mono text-xs break-all text-zinc-300">{{ config('notifora.api_url') }}/v1</code>
    </div>
  </aside>
</div>
@endsection
