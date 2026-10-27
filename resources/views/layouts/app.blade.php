<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Console') — Notifora</title>
  <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
  <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
  <meta name="theme-color" content="#2244A8">
  @include('partials.theme-init')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full" x-data="{ nav: false }">
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

@if ($currentEnv->isProduction())
  <div class="bg-err text-white text-center text-xs font-mono font-bold py-1 tracking-wider">PRODUCTION — perubahan berdampak ke pengguna nyata</div>
@endif

<div class="flex min-h-[calc(100%-0px)]">
  {{-- Overlay mobile --}}
  <div x-show="nav" x-cloak @click="nav = false" class="fixed inset-0 bg-black/60 z-30 md:hidden"></div>

  <aside :class="nav ? 'translate-x-0' : '-translate-x-full md:translate-x-0'" class="fixed md:sticky top-0 z-40 h-screen w-60 shrink-0 bg-surface-2 border-r border-line flex flex-col transition-transform">
    <div class="px-4 h-14 flex items-center gap-2 border-b border-line">
      <span class="w-8 h-8 shrink-0 rounded-md bg-[#fff] border border-line flex items-center justify-center p-0.5"><img src="{{ asset('logo-sm.png') }}" alt="" width="28" height="28" class="w-7 h-7"></span>
      <span class="font-extrabold tracking-[.08em] text-white text-sm">NOTIFORA</span>
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
    </nav>
    <div class="p-3 border-t border-line text-xs text-zinc-500">
      <p class="truncate text-zinc-300 font-medium">{{ $currentWorkspace->name }}</p>
      <p class="font-mono">{{ $currentWorkspace->planConfig()['label'] }} · {{ $currentRole->value }}</p>
    </div>
  </aside>

  <div class="flex-1 min-w-0 flex flex-col">
    <header class="h-14 border-b border-line bg-surface/80 backdrop-blur sticky top-0 z-20 flex items-center gap-3 px-4">
      <button class="md:hidden btn-ghost btn-sm" @click="nav = true" aria-label="Buka menu"><x-icon name="menu" class="w-5 h-5" /></button>

      <form method="POST" action="{{ route('console.environment.switch') }}" class="flex items-center gap-2">
        @csrf
        <label for="env" class="sr-only">Environment</label>
        <select id="env" name="environment_id" onchange="this.form.submit()"
                class="bg-surface-2 border border-line rounded-md text-xs font-mono font-bold px-2.5 py-1.5 {{ $currentEnv->isProduction() ? 'text-red-300' : 'text-emerald-300' }}">
          @foreach ($allEnvs as $e)
            <option value="{{ $e->id }}" @selected($e->id === $currentEnv->id)>{{ $e->type->value }}</option>
          @endforeach
        </select>
      </form>

      <div class="ml-auto flex items-center gap-2">
        <a href="{{ route('docs.index') }}" target="_blank" rel="noopener" class="btn-ghost btn-sm">Docs ↗</a>
        <x-theme-toggle />
        <span class="hidden sm:block text-xs text-zinc-500 truncate max-w-48">{{ auth()->user()->email }}</span>
        <form method="POST" action="{{ route('logout') }}">@csrf
          <button class="btn-ghost btn-sm" title="Keluar"><x-icon name="logout" /><span class="hidden sm:inline">Keluar</span></button>
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
<div class="fixed bottom-4 right-4 z-50 space-y-2" aria-live="polite">
  <template x-for="t in $store.toast.items" :key="t.id">
    <div class="card px-4 py-2.5 text-sm shadow-xl" :class="t.type === 'error' ? 'border-err/50 text-red-200' : 'border-ok/50 text-emerald-200'" :role="t.type === 'error' ? 'alert' : 'status'" x-text="t.message"></div>
  </template>
</div>
@stack('scripts')
</body>
</html>
