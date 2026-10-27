<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Dokumentasi') — Notifora Docs</title>
  <meta name="description" content="@yield('description', 'Dokumentasi teknis Notifora: API, workflow, channel, inbox real-time, webhook, dan billing.')">
  <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
  <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
  @include('partials.theme-init')
  @vite(['resources/css/app.css', 'resources/js/docs.js'])
</head>
<body x-data="docsApp({ searchUrl: @js(route('docs.search')), slug: @js($slug ?? null) })" @keydown.window="onKey($event)" class="min-h-screen">
@php $sections = config('docs.sections'); @endphp

{{-- Bar atas --}}
<header class="sticky top-0 z-40 border-b border-line bg-surface/90 backdrop-blur">
  <div class="mx-auto max-w-[1400px] px-4 sm:px-6 h-14 flex items-center gap-4">
    <button class="lg:hidden btn-ghost btn-sm" @click="nav = true" aria-label="Buka menu dokumentasi"><x-icon name="menu" class="w-5 h-5" /></button>
    <a href="{{ route('landing') }}" class="flex items-center gap-2 shrink-0">
      <img src="{{ asset('logo-sm.png') }}" alt="" width="26" height="26" class="w-[26px] h-[26px] bg-[#fff] rounded p-px">
      <span class="font-extrabold tracking-[.08em] text-white text-sm">NOTIFORA</span>
    </a>
    <nav class="hidden md:flex items-center gap-1 ml-4 text-sm" aria-label="Utama">
      <a href="{{ route('landing') }}" class="px-3 py-1.5 rounded-md text-zinc-400 hover:text-white hover:bg-white/5">Beranda</a>
      <a href="{{ route('docs.index') }}" class="px-3 py-1.5 rounded-md text-white bg-white/5">Docs</a>
      <a href="{{ route('landing') }}#pricing" class="px-3 py-1.5 rounded-md text-zinc-400 hover:text-white hover:bg-white/5">Harga</a>
    </nav>
    <div class="ml-auto flex items-center gap-2">
      <x-theme-toggle />
      @auth
        <a href="{{ route('console.dashboard') }}" class="btn-primary btn-sm">Console →</a>
      @else
        <a href="{{ route('login') }}" class="hidden sm:inline-flex btn-ghost btn-sm">Masuk</a>
        <a href="{{ route('register') }}" class="btn-primary btn-sm">Daftar gratis</a>
      @endauth
    </div>
  </div>
  {{-- Bar kedua: judul + pencarian --}}
  <div class="border-t border-line">
    <div class="mx-auto max-w-[1400px] px-4 sm:px-6 h-12 flex items-center gap-3">
      <p class="hidden sm:flex items-center gap-2 text-[11px] font-mono font-bold tracking-wider uppercase text-zinc-400 shrink-0">
        Dokumentasi Teknis <span class="text-zinc-600">/</span> <span class="text-white">{{ config('docs.version') }}</span>
      </p>
      <button type="button" @click="openSearch()" class="ml-auto sm:ml-6 flex-1 sm:flex-none sm:w-96 flex items-center gap-2 rounded-md border border-line bg-surface-2 px-3 py-1.5 text-left text-sm text-zinc-500 hover:border-zinc-600" aria-label="Cari dokumentasi">
        <span class="truncate flex-1">Cari dokumentasi atau endpoint API…</span>
        <kbd class="hidden sm:inline font-mono text-[10px] border border-line rounded px-1.5 py-0.5 text-zinc-400">Ctrl K</kbd>
      </button>
    </div>
  </div>
</header>

<div class="mx-auto max-w-[1400px] px-4 sm:px-6 flex gap-8">
  {{-- Overlay mobile --}}
  <div x-show="nav" x-cloak @click="nav = false" class="fixed inset-0 z-40 bg-black/60 lg:hidden"></div>

  {{-- Sidebar --}}
  <aside :class="nav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
         class="fixed lg:sticky top-0 lg:top-[6.5rem] z-50 lg:z-0 left-0 h-screen lg:h-[calc(100vh-7.5rem)] w-72 lg:w-64 shrink-0 overflow-y-auto bg-surface lg:bg-transparent border-r lg:border-0 border-line p-4 lg:p-0 lg:py-6 transition-transform" aria-label="Navigasi dokumentasi">
    <div class="lg:hidden flex items-center justify-between mb-3"><span class="text-sm font-semibold text-white">Dokumentasi</span><button class="btn-ghost btn-sm" @click="nav = false" aria-label="Tutup menu"><x-icon name="x" /></button></div>
    <nav class="space-y-1">
      @foreach ($sections as $sSlug => $s)
        @php $isCurrent = ($slug ?? null) === $sSlug; @endphp
        <div x-data="{ open: {{ $isCurrent ? 'true' : 'false' }} }">
          <button type="button" @click="open = !open" class="w-full flex items-center gap-2 px-2 py-2 rounded-md text-[11px] font-mono font-bold uppercase tracking-wider {{ $isCurrent ? 'text-white' : 'text-zinc-500 hover:text-zinc-300' }}" :aria-expanded="open">
            <x-icon :name="$s['icon']" class="w-3.5 h-3.5" /><span class="flex-1 text-left">{{ $s['title'] }}</span>
            <x-icon name="arrow-down" class="w-3 h-3 transition-transform" ::class="open ? 'rotate-180' : ''" />
          </button>
          <ul x-show="open" x-collapse class="ml-3 mb-2 border-l border-line">
            @foreach ($s['articles'] as [$aid, $atitle])
              <li><a href="{{ route('docs.show', $sSlug) }}#{{ $aid }}" @click="nav = false"
                     :class="current === '{{ $aid }}' && '{{ $isCurrent ? 1 : 0 }}' === '1' ? 'text-white border-cobalt bg-cobalt/10' : 'text-zinc-400 border-transparent hover:text-white hover:border-zinc-600'"
                     class="block -ml-px border-l-2 pl-3 pr-2 py-1.5 text-[13px] leading-snug">{{ $atitle }}</a></li>
            @endforeach
          </ul>
        </div>
      @endforeach
    </nav>
    <div class="mt-6 rounded-md border border-line bg-surface-2 p-3">
      <p class="text-sm font-semibold text-white">Butuh bantuan teknis?</p>
      <p class="text-xs text-zinc-400 mt-1">Tim Notifora siap membantu integrasi Anda.</p>
      <a href="mailto:{{ config('notifora.support_email') }}" class="btn-secondary btn-sm mt-3 w-full">Hubungi support</a>
    </div>
  </aside>

  {{-- Konten --}}
  <main class="min-w-0 flex-1 py-8 lg:py-10 max-w-3xl xl:max-w-4xl">
    @yield('content')

    <footer class="mt-14 pt-6 border-t border-line text-xs text-zinc-500 flex flex-wrap items-center justify-between gap-3">
      <span>Notifora Engineering Knowledge Base · Terakhir diperbarui: {{ config('docs.updated') }} · Rilis {{ config('docs.version') }}</span>
      <a href="#" class="hover:text-white" @click.prevent="window.scrollTo({ top: 0, behavior: 'smooth' })">Kembali ke atas ↑</a>
    </footer>
    <p class="mt-4 text-center text-[11px] text-zinc-600">@include('partials.credit')</p>
  </main>
</div>

{{-- Modal pencarian (Ctrl+K) --}}
<div x-show="searchOpen" x-cloak class="fixed inset-0 z-[60] flex items-start justify-center p-4 pt-[10vh]" role="dialog" aria-modal="true" aria-label="Pencarian dokumentasi">
  <div class="absolute inset-0 bg-black/70" @click="closeSearch()"></div>
  <div class="relative w-full max-w-xl card shadow-2xl" x-trap.noscroll="searchOpen">
    <div class="flex items-center gap-2 border-b border-line px-3">
      <x-icon name="filter" class="w-4 h-4 text-zinc-500" />
      <input x-ref="q" x-model="query" @input="search()" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="go()" @keydown.escape="closeSearch()"
             type="search" placeholder="Cari: trigger, idempotency, webhook, checkout…" class="w-full bg-transparent py-3 text-sm text-zinc-100 placeholder:text-zinc-600 focus:outline-none" aria-label="Kata kunci">
      <kbd class="font-mono text-[10px] border border-line rounded px-1.5 py-0.5 text-zinc-500">ESC</kbd>
    </div>
    <ul class="max-h-[50vh] overflow-y-auto p-2" role="listbox">
      <template x-for="(r, i) in results" :key="r.url">
        <li role="option" :aria-selected="i === active">
          <a :href="r.url" @mouseenter="active = i" @click="closeSearch()" :class="i === active ? 'bg-cobalt/15' : ''" class="block rounded-md px-3 py-2">
            <span class="block text-[10px] font-mono uppercase tracking-wider text-zinc-500" x-text="r.section"></span>
            <span class="block text-sm font-medium text-white" x-text="r.title"></span>
            <span class="block text-xs text-zinc-400 mt-0.5 line-clamp-2" x-html="r.snippet"></span>
          </a>
        </li>
      </template>
      <li x-show="query && !results.length" class="px-3 py-6 text-center text-sm text-zinc-500">Tidak ada hasil untuk “<span x-text="query"></span>”.</li>
      <li x-show="!query" class="px-3 py-6 text-center text-sm text-zinc-500">Ketik untuk mencari di seluruh dokumentasi.</li>
    </ul>
  </div>
</div>
</body>
</html>
