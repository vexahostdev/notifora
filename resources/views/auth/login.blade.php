@extends('layouts.guest')
@section('title', 'Masuk')
@section('content')
<div class="flex items-center justify-between mb-3 px-1">
  <div class="flex items-center gap-2"><span class="inline-block w-2.5 h-2.5 bg-cobalt animate-pulse"></span><span class="font-mono text-[10.5px] font-black uppercase tracking-[.18em]">CORE PROTOCOL AUTH</span></div>
</div>

<div class="relative bg-paper border-[3px] border-ink shadow-brutal p-6 sm:p-9">
  <div class="absolute -top-3 -right-3 bg-ink text-white font-mono text-[9px] font-black tracking-widest uppercase px-2 py-0.5 border border-white">TLS ENCRYPTED</div>

  <div class="grid grid-cols-2 gap-2 mb-7 p-1 bg-concrete-2 border-2 border-ink" role="tablist">
    <a href="{{ route('login') }}" class="py-2.5 text-center font-display text-xs sm:text-sm uppercase tracking-wider bg-ink text-white shadow-[2px_2px_0_#0033ff]" aria-current="page">Sign in</a>
    @if (\App\Services\PlatformSettings::bool('signup_enabled'))
      <a href="{{ route('register') }}" class="py-2.5 text-center font-display text-xs sm:text-sm uppercase tracking-wider hover:bg-white/60">Register</a>
    @else
      <span class="py-2.5 text-center font-display text-xs sm:text-sm uppercase tracking-wider text-zinc-400 border border-dashed border-zinc-400 cursor-not-allowed" title="Pendaftaran akun baru ditutup sementara">Locked</span>
    @endif
  </div>

  @if (session('status'))<p class="mb-4 font-mono text-[11px] text-cobalt font-bold" role="status">{{ session('status') }}</p>@endif

  <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4" x-data="{ show: false }">
    @csrf
    <div>
      <label for="email" class="b-label">Work email *</label>
      <input id="email" name="email" type="email" required autofocus autocomplete="email" value="{{ old('email') }}" placeholder="dev@organization.io" class="b-input">
      @error('email')<p class="mt-1 font-mono text-[11px] text-red-600 font-bold" role="alert">{{ $message }}</p>@enderror
    </div>
    <div>
      <div class="flex items-center justify-between"><label for="password" class="b-label">Secret passphrase *</label>
        <a href="{{ route('password.request') }}" class="font-mono text-[9px] text-cobalt hover:underline">RECOVER?</a></div>
      <div class="relative">
        <input id="password" name="password" :type="show ? 'text' : 'password'" required autocomplete="current-password" class="b-input pr-14">
        <button type="button" @click="show = !show" class="absolute right-2 top-1/2 -translate-y-1/2 font-mono text-[9px] font-bold uppercase px-1.5 py-1 text-zinc-600 hover:text-ink" x-text="show ? 'HIDE' : 'SHOW'">SHOW</button>
      </div>
    </div>
    <label class="flex items-center gap-2 font-mono text-[10px]"><input type="checkbox" name="remember" class="accent-[#0033FF]"> Ingat saya</label>
    <button type="submit" class="b-btn w-full mt-2">Establish session <span aria-hidden="true">↗</span></button>
  </form>
</div>

<div class="mt-6 bg-[#0d0d0d] border-2 border-[#0d0d0d] p-4 text-[#e3e7ee] font-mono text-xs shadow-[6px_6px_0_rgba(0,51,255,.4)]">
  <div class="flex items-center justify-between text-[10px] text-zinc-500 pb-2 mb-2 border-b border-white/10"><span class="text-cobalt font-bold">CLI QUICK START:</span><span>BASH</span></div>
  <div class="text-[11px] leading-relaxed text-zinc-400 break-words">
    <span class="text-[#00FF66]">$</span> curl -X POST https://api.notifora.my.id/v1/events/trigger \<br>
    &nbsp;&nbsp;-H <span class="text-yellow-200">"Authorization: Bearer ntf_live_..."</span> \<br>
    &nbsp;&nbsp;-d <span class="text-sky-300">'{"workflow":"order-confirmed","to":{"subscriberId":"usr_102"}}'</span>
  </div>
</div>
@endsection
