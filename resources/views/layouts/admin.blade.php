<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="robots" content="noindex,nofollow">
  <title>@yield('title', 'Admin') — {{ \App\Services\Brand::name() }} Admin</title>
  @include('partials.theme-init')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @include('partials.brand-head')
</head>
<body class="h-full"
      x-data="{
        nav: false,
        desktop: window.innerWidth >= 1024,
        openNav() { this.nav = true; this.$nextTick(() => this.$refs.navClose?.focus()); },
        closeNav() { this.nav = false; this.$nextTick(() => this.$refs.navTrigger?.focus()); }
      }"
      @resize.window="desktop = window.innerWidth >= 1024; if (desktop) nav = false"
      @keydown.escape.window="nav && closeNav()">
@php
  $groups = [
    'Ringkasan' => [['admin.dashboard', 'Dashboard', 'home'], ['admin.reports.index', 'Laporan', 'chart']],
    'Pelanggan' => [['admin.tenants.index', 'Tenants', 'team'], ['admin.users.index', 'Pengguna & operator', 'users']],
    'Billing' => [['admin.payments.index', 'Pembayaran', 'card'], ['admin.payments.events', 'Event pembayaran', 'webhook'], ['admin.plans.index', 'Plan & harga', 'list'], ['admin.coupons.index', 'Kupon', 'filter']],
    'Operasional' => [['admin.ops.index', 'Kesehatan sistem', 'activity'], ['admin.system.workers', 'Workers & scheduler', 'play'], ['admin.ops.failed', 'Job gagal', 'alert'], ['admin.ops.providers', 'Provider', 'plug'], ['admin.ops.webhooks', 'Webhook pelanggan', 'webhook'], ['admin.ops.messages', 'Log pesan global', 'list']],
    'Keamanan' => [['admin.abuse.index', 'Abuse & blokir', 'shield'], ['admin.audit', 'Audit log', 'clock']],
    'Komunikasi' => [['admin.comms.announcements', 'Pengumuman', 'bell'], ['admin.comms.broadcasts', 'Broadcast email', 'bell'], ['admin.emails.logs', 'Log email', 'list'], ['admin.emails.templates', 'Template email', 'list'], ['admin.emails.smtp', 'SMTP & uji kirim', 'key']],
    'Sistem' => [['admin.system.readiness', 'Kesiapan production', 'check'], ['admin.system.backups', 'Backup database', 'arrow-down'], ['admin.system.logs', 'Log aplikasi', 'list'], ['admin.system.branding', 'Branding & kontak', 'user'], ['admin.settings', 'Pengaturan platform', 'key']],
  ];
  // Sembunyikan menu di luar peran operator
  $groups = collect($groups)->map(fn ($links) => array_values(array_filter($links, fn ($l) => auth()->user()->canAdmin(\App\Http\Middleware\EnsurePlatformAdmin::section($l[0])))))->filter()->all();
  $hasWorkspace = (bool) auth()->user()->currentMembership();
@endphp

<div class="{{ app()->isProduction() ? 'bg-err text-white' : 'bg-surface-2 text-zinc-400 border-b border-line' }} text-center text-[11px] font-mono font-bold py-1 tracking-wider">PLATFORM ADMIN — {{ strtoupper(app()->environment()) }} — semua tindakan dicatat di audit log</div>

<div class="flex min-h-[calc(100%-1.5rem)]">
  <div x-show="nav" x-cloak @click="closeNav()" class="fixed inset-0 bg-black/60 z-30 lg:hidden"></div>

  <aside id="admin-nav"
         :class="nav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
         :inert="!desktop && !nav"
         x-trap.noscroll="!desktop && nav"
         class="fixed lg:sticky top-0 z-40 h-dvh w-60 max-w-[85vw] shrink-0 bg-surface-2 border-r border-line flex flex-col -translate-x-full lg:translate-x-0 transition-transform">
    <div class="px-4 h-14 flex items-center gap-2 border-b border-line shrink-0">
      <span class="w-8 h-8 shrink-0 rounded-md bg-[#fff] border border-line flex items-center justify-center p-0.5"><img src="{{ \App\Services\Brand::logoUrl() }}" alt="" width="28" height="28" class="w-7 h-7"></span>
      <div class="leading-tight min-w-0"><span class="font-extrabold tracking-[.08em] text-white text-sm block uppercase truncate">{{ \App\Services\Brand::name() }}</span><span class="text-[9px] font-mono font-bold tracking-widest text-link">ADMIN</span></div>
      <button type="button" x-ref="navClose" class="lg:hidden btn-ghost btn-sm ml-auto" @click="closeNav()" aria-label="Tutup menu"><x-icon name="x" /></button>
    </div>
    <nav class="flex-1 overflow-y-auto p-3 space-y-4" aria-label="Navigasi admin">
      @foreach ($groups as $label => $links)
        <div>
          <p class="px-3 pb-1 text-[10px] uppercase tracking-widest text-zinc-600 font-semibold">{{ $label }}</p>
          @foreach ($links as [$route, $text, $icon])
            <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($route) || (str_starts_with($route, 'admin.tenants') && request()->routeIs('admin.tenants.*')) || (str_starts_with($route, 'admin.users') && request()->routeIs('admin.users.*')) || ($route === 'admin.payments.index' && request()->routeIs('admin.payments.show')) ? 'nav-link-active' : '' }}"><x-icon :name="$icon" />{{ $text }}</a>
          @endforeach
        </div>
      @endforeach
    </nav>
    <div class="p-3 border-t border-line text-xs text-zinc-500 shrink-0">
      <p class="truncate text-zinc-300 font-medium">{{ $admin->name }}</p>
      <p class="truncate font-mono">{{ $admin->email }}</p>
      <div class="flex gap-3 mt-2">
        <a href="{{ route('console.dashboard') }}" class="text-link hover:underline">← Console (tampilan pengguna)</a>
        <a href="{{ route('security.2fa') }}" class="text-link hover:underline">2FA</a>
      </div>
    </div>
  </aside>

  <div class="flex-1 min-w-0 flex flex-col">
    <header class="h-14 border-b border-line bg-surface/80 backdrop-blur sticky top-0 z-20 flex items-center gap-1.5 sm:gap-3 px-2 sm:px-4">
      <button x-ref="navTrigger" class="lg:hidden btn-ghost btn-sm shrink-0" @click="openNav()" aria-label="Buka menu" aria-controls="admin-nav" :aria-expanded="nav"><x-icon name="menu" class="w-5 h-5" /></button>
      <form method="GET" action="{{ route('admin.search') }}" class="flex-1 min-w-0 max-w-xl" role="search">
        <label for="gs" class="sr-only">Cari</label>
        <input id="gs" name="q" value="{{ request('q') }}" placeholder="Cari email, workspace, invoice, ID pesan…" class="input !py-1.5 min-w-0" autocomplete="off">
      </form>
      <div class="ml-auto flex items-center gap-0.5 sm:gap-2 shrink-0">
        <x-theme-toggle />
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-ghost btn-sm" title="Keluar" aria-label="Keluar"><x-icon name="logout" /><span class="hidden sm:inline">Keluar</span></button></form>
      </div>
    </header>

    <main class="flex-1 p-4 sm:p-6 max-w-[1500px] w-full mx-auto">
      @if (! $admin->hasTwoFactor())
        <div class="mb-5 rounded-md border border-warn/40 bg-warn/10 px-4 py-3 text-sm text-amber-200" role="alert">2FA akun operator Anda belum aktif. <a class="underline" href="{{ route('security.2fa') }}">Aktifkan sekarang</a>.</div>
      @endif
      <x-flash />
      @yield('content')
    </main>
    <footer class="px-6 py-4 border-t border-line text-center text-[11px] text-zinc-500">@include('partials.credit')</footer>
  </div>
</div>

<div class="fixed bottom-4 inset-x-4 sm:left-auto sm:right-4 sm:max-w-sm z-50 space-y-2" aria-live="polite">
  <template x-for="t in $store.toast.items" :key="t.id">
    <div class="card px-4 py-2.5 text-sm shadow-xl break-words" :class="t.type === 'error' ? 'border-err/50 text-red-200' : 'border-ok/50 text-emerald-200'" :role="t.type === 'error' ? 'alert' : 'status'" x-text="t.message"></div>
  </template>
</div>
@stack('scripts')
</body>
</html>
