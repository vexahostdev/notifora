<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ strtoupper(\App\Services\Brand::name()) }} — {{ \App\Services\Brand::tagline() }}</title>
  <meta name="description" content="Notifora: gateway notifikasi untuk WhatsApp, Email, Telegram, dan In-App Inbox. Satu API, retry otomatis, fallback antar-channel.">
  @include('partials.theme-init')
  @vite(['resources/css/marketing.css', 'resources/js/marketing.js'])
  @include('partials.brand-head')
</head>
<body x-data="{ menu: false }">
{{-- Filter SVG dekoratif (nonaktif otomatis lewat reduced-motion tidak berlaku; filter statis) --}}
<svg style="position:absolute;width:0;height:0" aria-hidden="true">
  <defs>
    <filter id="graffiti" x="-25%" y="-25%" width="150%" height="150%">
      <feTurbulence type="fractalNoise" baseFrequency="0.75" numOctaves="3" seed="8" result="grain"/>
      <feTurbulence type="turbulence" baseFrequency="0.06" numOctaves="2" seed="3" result="wobble"/>
      <feDisplacementMap in="SourceGraphic" in2="wobble" scale="6" xChannelSelector="R" yChannelSelector="G" result="rough"/>
      <feDisplacementMap in="rough" in2="grain" scale="10" xChannelSelector="R" yChannelSelector="B"/>
    </filter>
    <filter id="aerosol" x="-20%" y="-20%" width="140%" height="140%">
      <feTurbulence type="fractalNoise" baseFrequency="0.65" numOctaves="3" seed="14" result="n1"/>
      <feTurbulence type="turbulence" baseFrequency="0.045" numOctaves="2" seed="5" result="n2"/>
      <feDisplacementMap in="SourceGraphic" in2="n2" scale="10" xChannelSelector="R" yChannelSelector="G" result="d1"/>
      <feDisplacementMap in="d1" in2="n1" scale="14" xChannelSelector="R" yChannelSelector="B"/>
    </filter>
  </defs>
</svg>

<div x-data="sprayCanvas()" class="relative">
  <canvas x-ref="canvas" @pointerdown="drawing = on; on && spray($event)" @pointermove="drawing && on && spray($event)" @pointerup="drawing = false"
          :class="on ? 'pointer-events-auto cursor-crosshair' : 'pointer-events-none'" class="absolute inset-0 z-30" aria-hidden="true"></canvas>

  {{-- HEADER --}}
  <header class="fixed top-0 inset-x-0 z-40 bg-concrete/80 backdrop-blur-md border-b border-ink/5 px-5 sm:px-9 md:px-12 py-3.5 flex items-center justify-between">
    <a href="#overview" class="group flex items-center gap-2">
      <img src="{{ \App\Services\Brand::logoUrl() }}" alt="" width="30" height="30" class="brand-logo w-[30px] h-[30px] transition-transform duration-300 group-hover:-rotate-6">
      <span class="font-extrabold text-sm tracking-[.08em] uppercase">{{ \App\Services\Brand::name() }}</span>
    </a>
    <nav class="hidden md:flex items-center gap-8" aria-label="Utama">
      @foreach (['overview' => 'OVERVIEW', 'channels' => 'CHANNELS', 'workflow' => 'WORKFLOW', 'docs' => 'DOCS', 'pricing' => 'PRICING'] as $id => $label)
        <a href="{{ $id === 'docs' ? url('/docs') : '#'.$id }}" class="text-[10.5px] font-extrabold tracking-[.14em] hover:text-cobalt transition-colors">{{ $label }}</a>
      @endforeach
    </nav>
    <div class="flex items-center gap-4">
      <x-theme-toggle class="p-1.5 border-2 border-ink/80 hover:bg-ink hover:text-white transition-colors" />
      @auth
        <a href="{{ route('console.dashboard') }}" class="text-[11px] font-extrabold tracking-[.12em] hover:text-cobalt">CONSOLE ↗</a>
      @else
        <a href="{{ route('login') }}" class="hidden sm:block text-[11px] font-extrabold tracking-[.12em] hover:text-cobalt">SIGN IN</a>
        <a href="{{ route('register') }}" class="text-[11px] font-extrabold tracking-[.12em] hover:text-cobalt">GET API KEY ↗</a>
      @endauth
    </div>
  </header>

  {{-- HERO --}}
  <section id="overview" class="concrete-hero relative min-h-screen overflow-hidden flex items-center border-b border-ink/10 pt-20">
    <svg class="absolute inset-0 w-full h-full pointer-events-none" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
      <g stroke="#1E2024" fill="none" stroke-linecap="round" stroke-linejoin="round">
        <path d="M-10,188 L18,210 L42,238 L49,255 L78,278 L72,306 L44,344 L36,368 L8,395 L-5,412" stroke-width="2.6" opacity=".7"/>
        <path d="M42,238 L66,232 L89,242 M78,278 L104,289 L124,285 M44,344 L62,362 L75,388" stroke-width="1.1" opacity=".45"/>
      </g>
    </svg>
    <div class="relative w-full max-w-[1360px] mx-auto px-5 sm:px-9 md:px-12 py-12">
      <h1 class="sr-only">Notifora — satu API untuk semua notifikasi</h1>
      <div class="relative z-10 flex flex-col" aria-hidden="true">
        @foreach (['DROPPED QUEUES', 'RATE LIMITED', 'FAILED RETRIES', 'SLACK SPAM', 'SILENT FAILS', 'SPAGHETTI SDKS'] as $i => $line)
          <div class="overflow-hidden">
            <div class="display hero-ghost text-[10.8vw] sm:text-[9.6vw] lg:text-[124px] xl:text-[138px] rise" style="animation-delay: {{ $i * 80 }}ms">{{ $line }}</div>
          </div>
        @endforeach
      </div>
      <div class="relative z-20 mt-12 max-w-xl reveal">
        <p class="font-mono text-[10px] font-black tracking-[.18em] text-cobalt uppercase mb-2">SATU API · SEMUA NOTIFIKASI</p>
        <p class="text-lg sm:text-xl font-semibold leading-snug">Kirim ke WhatsApp, Email, Telegram, dan In-App Inbox dari satu endpoint, dengan retry otomatis dan fallback antar-channel.</p>
        <div class="mt-6 flex flex-wrap gap-3">
          <a href="{{ route('register') }}" class="b-btn">Get API key <span aria-hidden="true">↗</span></a>
          <a href="{{ url('/docs') }}" class="b-btn-alt">Baca dokumentasi</a>
        </div>
      </div>
    </div>
  </section>

  {{-- SLOGAN --}}
  <section id="channels" class="concrete-light relative min-h-screen overflow-hidden flex items-center border-b border-ink/10">
    <svg class="absolute inset-0 w-full h-full pointer-events-none" viewBox="0 0 1440 820" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
      <path d="M 555 445 C 395 605, 195 695, 72 605 C -45 515, -15 335, 145 315 C 315 295, 455 385, 575 455 C 735 548, 955 645, 1175 568 C 1375 498, 1395 245, 1185 112 C 965 -25, 685 115, 535 325 C 465 422, 468 492, 555 445" fill="none" stroke="#0033FF" stroke-width="62" stroke-linecap="round" filter="url(#aerosol)" class="draw" opacity=".95"/>
    </svg>
    <div class="relative z-10 w-full max-w-[1380px] mx-auto px-5 sm:px-9 md:px-12 py-20 grid lg:grid-cols-12 gap-8 items-center">
      <div class="lg:col-span-8 reveal">
        <p class="display text-[14.5vw] sm:text-[13vw] lg:text-[148px] xl:text-[168px]">ENDLESS</p>
        <p class="display text-[14.5vw] sm:text-[13vw] lg:text-[148px] xl:text-[168px] mt-1">NO LIMIT</p>
      </div>
      <div class="lg:col-span-4 lg:pl-4 max-w-[340px] reveal bg-concrete/70 backdrop-blur-sm p-4 border-2 border-ink shadow-brutal-ink">
        <p class="text-[10px] font-bold tracking-[.14em] text-zinc-600 uppercase mb-2">UNIFIED PROTOCOL</p>
        <h2 lang="ja" class="font-black text-[20px] sm:text-[23px] tracking-[.16em] mb-3 leading-tight">全チャンネルを1つのAPIへ</h2>
        <p class="text-sm leading-relaxed">WhatsApp, Email, Telegram, dan In-App Inbox lewat satu endpoint. Template, preferensi subscriber, retry, dan fallback sudah ditangani.</p>
      </div>
    </div>
  </section>

  {{-- WORKFLOW --}}
  <section id="workflow" class="bg-white relative overflow-hidden border-b border-ink/10">
    <div class="max-w-[1400px] mx-auto px-5 sm:px-9 md:px-12 py-20">
      <div class="reveal">
        <p class="display text-[12vw] sm:text-[9.5vw] lg:text-[98px] xl:text-[112px]">DISPATCH</p>
        <p class="display text-[9.2vw] sm:text-[8.1vw] lg:text-[96px] xl:text-[111px]">EVERY SINGLE EVENT</p>
        <p class="display text-[9.2vw] sm:text-[8.1vw] lg:text-[96px] xl:text-[111px] text-cobalt">ACROSS ALL CHANNELS</p>
      </div>

      <div class="grid md:grid-cols-3 gap-6 mt-14">
        @foreach ([
          ['01', 'Satu request', 'POST /v1/events/trigger. Event tersimpan durable sebelum diproses; respons ack ditargetkan < 50 ms (p95).'],
          ['02', 'Workflow & fallback', 'Susun step: filter, delay, kirim. Jika WhatsApp gagal, otomatis pindah ke Email atau Telegram.'],
          ['03', 'Inbox real-time', 'Bell icon siap pakai lewat WebSocket. Unread badge, mark read, sinkron antar tab.'],
        ] as [$n, $t, $d])
          <article class="reveal border-[3px] border-ink bg-concrete p-6 shadow-brutal-ink">
            <p class="font-mono text-xs font-bold text-cobalt">{{ $n }}</p>
            <h3 class="font-display uppercase text-xl mt-2 mb-2">{{ $t }}</h3>
            <p class="text-sm leading-relaxed text-zinc-700">{{ $d }}</p>
          </article>
        @endforeach
      </div>
    </div>
  </section>

  {{-- DOCS / CODE --}}
  <section id="docs" class="concrete-hero border-b border-ink/10">
    <div class="max-w-[1100px] mx-auto px-5 sm:px-9 py-20 grid lg:grid-cols-2 gap-10 items-center">
      <div class="reveal">
        <p class="font-mono text-[10px] font-black tracking-[.18em] text-cobalt uppercase mb-2">DEVELOPER EXPERIENCE</p>
        <h2 class="display text-4xl sm:text-5xl mb-4">Integrate in minutes</h2>
        <p class="text-sm leading-relaxed text-zinc-700 mb-5">REST API sederhana dengan idempotency key, webhook bertanda tangan HMAC, dan log pengiriman lengkap. SDK resmi menyusul setelah rilis 1.0.</p>
        <a href="{{ route('register') }}" class="b-btn">Get API key <span aria-hidden="true">↗</span></a>
      </div>
      <div class="reveal bg-[#0d0d0d] text-[#e3e7ee] font-mono text-xs p-5 border-2 border-ink shadow-brutal overflow-x-auto">
        <div class="flex justify-between text-[10px] text-zinc-500 pb-2 mb-3 border-b border-white/10"><span class="text-cobalt font-bold">TRIGGER</span><span>BASH</span></div>
<pre class="leading-relaxed text-zinc-300 whitespace-pre-wrap break-words"><span class="text-[#00FF66]">$</span> curl -X POST https://api.notifora.my.id/v1/events/trigger \
  -H "Authorization: Bearer ntf_live_..." \
  -H "X-Idempotency-Key: order-9901" \
  -d '{
    "workflow": "order-confirmed",
    "to": { "subscriberId": "usr_102",
            "phone": "0812-3456-7890" },
    "payload": { "invoiceId": "INV-9901",
                 "amount": 450000 }
  }'

<span class="text-zinc-500"># 202 Accepted</span>
{ "data": { "event_id": "01J...", "status": "QUEUED" } }</pre>
      </div>
    </div>
  </section>

  {{-- PRICING --}}
  <section id="pricing" class="concrete-light border-b border-ink/10">
    <div class="max-w-[1300px] mx-auto px-5 sm:px-9 py-20">
      <p class="font-mono text-[10px] font-black tracking-[.18em] text-cobalt uppercase mb-2">TRANSPARENT USAGE PRICING</p>
      <h2 class="display text-4xl sm:text-6xl mb-3">Pay for what you dispatch</h2>
      <p class="text-sm text-zinc-700 mb-10">1 event = 1 trigger yang diterima API (bukan per channel). Environment Development gratis hingga {{ number_format(config('notifora.dev_events_monthly'), 0, ',', '.') }} event/bulan.</p>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
        @foreach ($plans as $key => $p)
          <article class="reveal border-[3px] border-ink bg-white p-6 {{ $key === 'pro' ? 'shadow-brutal' : 'shadow-brutal-ink' }}">
            @if ($key === 'pro')<p class="font-mono text-[10px] font-black tracking-widest text-cobalt mb-1">POPULER</p>@endif
            <h3 class="font-display uppercase text-xl">{{ $p['label'] }}</h3>
            <p class="mt-3 font-mono text-2xl font-bold">Rp {{ number_format($p['price'], 0, ',', '.') }}<span class="text-xs text-zinc-500">/bln</span></p>
            <ul class="mt-4 space-y-1.5 text-sm text-zinc-700">
              <li>{{ number_format($p['events'], 0, ',', '.') }}{{ $key === 'scale' ? '+' : '' }} event / bulan</li>
              <li>Log {{ $p['retention_days'] }} hari</li>
              <li>Rate limit {{ number_format($p['rpm'], 0, ',', '.') }} req/menit</li>
              @if ($key === 'pro')<li>Custom branding inbox</li>@endif
              @if ($key === 'business')<li>Queue prioritas</li>@endif
              @if ($key === 'scale')<li>Infrastruktur dedicated · SLA 99,99%</li>@endif
            </ul>
            <a href="{{ auth()->check() ? route('console.billing.checkout', $key) : route('register') }}" class="b-btn-alt w-full mt-5">{{ auth()->check() ? 'Pilih plan' : 'Mulai' }}</a>
          </article>
        @endforeach
      </div>
    </div>
  </section>

  <footer class="bg-[#0d0d0d] text-[#e3e7ee] px-5 sm:px-9 md:px-12 py-10">
    <div class="max-w-[1300px] mx-auto flex flex-wrap items-center justify-between gap-4">
      <p class="font-mono text-xs flex items-center gap-2"><img src="{{ asset('logo-sm.png') }}" alt="" width="20" height="20" class="w-5 h-5 rounded-sm bg-white p-px">@include('partials.credit')</p>
      <p class="font-mono text-xs text-zinc-500"><a href="mailto:{{ \App\Services\Brand::supportEmail() }}" class="hover:text-cobalt">{{ \App\Services\Brand::supportEmail() }}</a> · <a href="{{ url('/docs') }}" class="hover:text-cobalt">Docs</a></p>
    </div>
  </footer>

  {{-- Spray toggle --}}
  <div class="fixed bottom-4 left-4 z-40 flex items-center gap-2">
    <button @click="on = !on" :aria-pressed="on" :class="on ? 'bg-cobalt text-white border-cobalt' : 'bg-white/90 border-ink/15 hover:border-cobalt'" class="px-3.5 py-2 text-[10px] font-extrabold tracking-[.14em] uppercase border backdrop-blur-md">
      <span x-text="on ? 'SPRAY ON — DRAG TO TAG' : 'TRY SPRAY CAN'">TRY SPRAY CAN</span>
    </button>
    <button x-show="on" x-cloak @click="clear()" class="px-3 py-2 text-[10px] font-extrabold tracking-[.12em] uppercase bg-ink text-white hover:bg-cobalt">CLEAR</button>
  </div>
</div>
</body>
</html>
