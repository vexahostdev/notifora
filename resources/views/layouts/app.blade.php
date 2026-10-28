<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Console') — {{ \App\Services\Brand::name() }}</title>
  @include('partials.theme-init')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @include('partials.brand-head')
</head>
<body class="h-full"
      x-data="{
        nav: false,
        desktop: window.innerWidth >= 768,
        openNav() { this.nav = true; this.$nextTick(() => this.$refs.navClose?.focus()); },
        closeNav() { this.nav = false; this.$nextTick(() => this.$refs.navTrigger?.focus()); }
      }"
      @resize.window="desktop = window.innerWidth >= 768; if (desktop) nav = false"
      @keydown.escape.window="nav && closeNav()">
@php
  $links = [
    ['console.dashboard', 'Dashboard', 'home', 'console.dashboard'],
    ['console.workflows.index', 'Workflows', 'workflow', 'console.workflows.*'],
    ['console.subscribers.index', 'Subscribers', 'users', 'console.subscribers.*'],
    ['console.channels.index', 'Channels', 'plug', 'console.channels.*'],
    ['console.logs.index', 'Message Logs', 'list', 'console.logs.*'],
  ];
  $settings = [
    ['console.api-keys.index', 'API Keys', 'key', 'DEVELOPER'],
    ['console.webhooks.index', 'Webhooks', 'webhook', 'DEVELOPER'],
    ['console.team.index', 'Team', 'team', 'ADMIN'],
    ['console.usage', 'Usage', 'chart', 'ADMIN'],
    ['console.billing.index', 'Billing', 'card', 'ADMIN'],
    ['console.audit', 'Audit Log', 'shield', 'ADMIN'],
    ['console.account', 'Account', 'user', 'VIEWER'],
  ];
@endphp

@if (session('impersonator_id'))
  <div class="bg-amber-500 text-black text-center text-xs font-semibold py-1.5 px-3 flex flex-wrap items-center justify-center gap-3" role="alert">
    <span>Mode impersonasi: Anda masuk sebagai {{ auth()->user()->email }}. Semua aksi dicatat.</span>
    <form method="POST" action="{{ route('impersonation.stop') }}">@csrf<button class="underline font-bold">Akhiri &amp; kembali ke Admin</button></form>
  </div>
@endif
@foreach (($liveAnnouncements ?? collect()) as $ann)
  @php $annTone = ['info' => 'bg-info/15 text-blue-200 border-info/40', 'success' => 'bg-ok/15 text-emerald-200 border-ok/40', 'warn' => 'bg-warn/15 text-amber-200 border-warn/40', 'danger' => 'bg-err/15 text-red-200 border-err/40'][$ann->tone] ?? 'bg-info/15 text-blue-200 border-info/40'; @endphp
  <div class="border-b px-4 py-2 text-sm text-center {{ $annTone }}" role="status"><strong>{{ $ann->title }}</strong> — {{ $ann->body }}</div>
@endforeach

@if ($currentEnv->isProduction())
  <div class="bg-err text-white text-center text-xs font-mono font-bold py-1 tracking-wider">PRODUCTION — perubahan berdampak ke pengguna nyata</div>
@endif

<div class="flex min-h-[calc(100%-0px)]">
  {{-- Overlay mobile --}}
  <div x-show="nav" x-cloak @click="closeNav()" class="fixed inset-0 bg-black/60 z-30 md:hidden"></div>

  <aside id="console-nav"
         :class="nav ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
         :inert="!desktop && !nav"
         x-trap.noscroll="!desktop && nav"
         class="fixed md:sticky top-0 z-40 h-dvh w-60 max-w-[85vw] shrink-0 bg-surface-2 border-r border-line flex flex-col -translate-x-full md:translate-x-0 transition-transform">
    <div class="px-4 h-14 flex items-center gap-2 border-b border-line shrink-0">
      <span class="w-8 h-8 shrink-0 rounded-md bg-[#fff] border border-line flex items-center justify-center p-0.5"><img src="{{ \App\Services\Brand::logoUrl() }}" alt="" width="28" height="28" class="w-7 h-7"></span>
      <span class="font-extrabold tracking-[.08em] text-white text-sm uppercase truncate">{{ \App\Services\Brand::name() }}</span>
      <button type="button" x-ref="navClose" class="md:hidden btn-ghost btn-sm ml-auto" @click="closeNav()" aria-label="Tutup menu"><x-icon name="x" /></button>
    </div>
    <nav class="flex-1 overflow-y-auto p-3 space-y-0.5" aria-label="Navigasi utama">
      @foreach ($links as [$route, $label, $icon, $match])
        <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($match) ? 'nav-link-active' : '' }}" @if (request()->routeIs($match)) aria-current="page" @endif>
          <x-icon :name="$icon" />{{ $label }}
        </a>
      @endforeach
      <p class="px-3 pt-5 pb-1 text-[10px] uppercase tracking-widest text-zinc-600 font-semibold">Settings</p>
      @foreach ($settings as [$route, $label, $icon, $min])
        @if ($currentRole->atLeast(\App\Enums\MemberRole::from($min)))
          <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($route === 'console.billing.index' ? 'console.billing.*' : $route) ? 'nav-link-active' : '' }}"><x-icon :name="$icon" />{{ $label }}</a>
        @endif
      @endforeach
      @if (auth()->user()->isPlatformAdmin() && ! session('impersonator_id'))
        <a href="{{ route('admin.dashboard') }}" class="nav-link mt-3"><x-icon name="shield" />Admin panel</a>
      @endif
    </nav>
    <div class="p-3 border-t border-line text-xs text-zinc-500">
      <p class="truncate text-zinc-300 font-medium">{{ $currentWorkspace->name }}</p>
      <p class="font-mono">{{ $currentWorkspace->planConfig()['label'] }} · {{ $currentRole->value }}</p>
    </div>
  </aside>

  <div class="flex-1 min-w-0 flex flex-col">
    <header class="h-14 border-b border-line bg-surface/80 backdrop-blur sticky top-0 z-20 flex items-center gap-1.5 sm:gap-3 px-2 sm:px-4">
      <button x-ref="navTrigger" class="md:hidden btn-ghost btn-sm shrink-0" @click="openNav()" aria-label="Buka menu" aria-controls="console-nav" :aria-expanded="nav"><x-icon name="menu" class="w-5 h-5" /></button>

      <form method="POST" action="{{ route('console.environment.switch') }}" class="flex items-center gap-2 min-w-0">
        @csrf
        <label for="env" class="sr-only">Environment</label>
        <select id="env" name="environment_id" onchange="this.form.submit()"
                class="bg-surface-2 border border-line rounded-md text-xs font-mono font-bold px-2.5 py-1.5 max-w-28 sm:max-w-none {{ $currentEnv->isProduction() ? 'text-red-300' : 'text-emerald-300' }}">
          @foreach ($allEnvs as $e)
            <option value="{{ $e->id }}" @selected($e->id === $currentEnv->id)>{{ $e->type->value }}</option>
          @endforeach
        </select>
      </form>

      <div class="ml-auto flex items-center gap-0.5 sm:gap-2 shrink-0">
        <a href="{{ route('docs.index') }}" target="_blank" rel="noopener" class="hidden sm:inline-flex btn-ghost btn-sm">Docs ↗</a>
        <x-theme-toggle />
        <span class="hidden sm:block text-xs text-zinc-500 truncate max-w-48">{{ auth()->user()->email }}</span>
        <form method="POST" action="{{ route('logout') }}">@csrf
          <button class="btn-ghost btn-sm" title="Keluar" aria-label="Keluar"><x-icon name="logout" /><span class="hidden sm:inline">Keluar</span></button>
        </form>
      </div>
    </header>

    <main class="flex-1 p-4 sm:p-6 max-w-[1400px] w-full mx-auto">
      <x-flash />
      @yield('content')
    </main>
    <footer class="px-6 py-4 border-t border-line text-center text-[11px] text-zinc-500">@include('partials.credit')</footer>
  </div>
</div>

{{-- Toast --}}
<div class="fixed bottom-4 inset-x-4 sm:left-auto sm:right-4 sm:max-w-sm z-50 space-y-2" aria-live="polite">
  <template x-for="t in $store.toast.items" :key="t.id">
    <div class="card px-4 py-2.5 text-sm shadow-xl break-words" :class="t.type === 'error' ? 'border-err/50 text-red-200' : 'border-ok/50 text-emerald-200'" :role="t.type === 'error' ? 'alert' : 'status'" x-text="t.message"></div>
  </template>
</div>
@stack('scripts')
</body>
</html>
