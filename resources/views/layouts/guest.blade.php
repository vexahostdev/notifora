<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Masuk') — Notifora</title>
  <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
  <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
  <meta name="theme-color" content="#2244A8">
  @include('partials.theme-init')
  @vite(['resources/css/marketing.css', 'resources/js/marketing.js'])
</head>
<body class="concrete-hero min-h-screen flex flex-col">
  <header class="px-6 sm:px-10 py-5 flex items-center justify-between border-b border-ink/10 bg-concrete/60 backdrop-blur">
    <a href="{{ route('landing') }}" class="group flex items-center gap-2.5">
      <img src="{{ asset('logo-sm.png') }}" alt="" width="34" height="34" class="brand-logo w-[34px] h-[34px] transition-transform duration-300 group-hover:-rotate-6">
      <span class="flex flex-col"><span class="font-extrabold text-sm tracking-[.08em]">NOTIFORA</span><span class="font-mono text-[8px] font-bold tracking-widest text-cobalt uppercase -mt-0.5">DEV CONSOLE</span></span>
    </a>
    <div class="flex items-center gap-4"><x-theme-toggle class="p-1.5 border-2 border-ink/80 hover:bg-ink hover:text-white transition-colors" /><a href="{{ route('landing') }}" class="font-mono text-[11px] font-bold tracking-wider hover:text-cobalt">← KEMBALI KE SITUS</a></div>
  </header>

  <main class="flex-1 flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-[540px]">
      @yield('content')
    </div>
  </main>

  <footer class="px-6 py-4 border-t border-ink/10 bg-concrete/60 font-mono text-[10px] text-zinc-600">@include('partials.credit')</footer>
</body>
</html>
