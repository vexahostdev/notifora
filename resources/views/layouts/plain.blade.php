<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title') — {{ \App\Services\Brand::name() }}</title>
  @include('partials.theme-init')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @include('partials.brand-head')
</head>
<body class="min-h-full flex flex-col">
  <header class="h-14 border-b border-line flex items-center gap-3 px-4 sm:px-6">
    <a href="{{ route('landing') }}" class="flex items-center gap-2">
      <img src="{{ \App\Services\Brand::logoUrl() }}" alt="" width="26" height="26" class="w-[26px] h-[26px] bg-[#fff] rounded p-px">
      <span class="font-extrabold tracking-[.08em] text-white text-sm uppercase">{{ \App\Services\Brand::name() }}</span>
    </a>
    <div class="ml-auto flex items-center gap-2">
      <x-theme-toggle />
      @auth
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-ghost btn-sm"><x-icon name="logout" /> Keluar</button></form>
      @endauth
    </div>
  </header>
  <main class="flex-1 w-full max-w-2xl mx-auto p-4 sm:p-6">
    <x-flash />
    @yield('content')
  </main>
  <footer class="px-6 py-4 border-t border-line text-center text-[11px] text-zinc-500">@include('partials.credit')</footer>
</body>
</html>
