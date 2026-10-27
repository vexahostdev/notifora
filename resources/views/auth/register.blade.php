@extends('layouts.guest')
@section('title', 'Daftar')
@section('content')
<div class="flex items-center gap-2 mb-3 px-1"><span class="inline-block w-2.5 h-2.5 bg-cobalt animate-pulse"></span><span class="font-mono text-[10.5px] font-black uppercase tracking-[.18em]">NEW DEVELOPER</span></div>

<div class="relative bg-paper border-[3px] border-ink shadow-brutal p-6 sm:p-9">
  <div class="grid grid-cols-2 gap-2 mb-7 p-1 bg-concrete-2 border-2 border-ink">
    <a href="{{ route('login') }}" class="py-2.5 text-center font-display text-xs sm:text-sm uppercase tracking-wider hover:bg-white/60">Sign in</a>
    <a href="{{ route('register') }}" class="py-2.5 text-center font-display text-xs sm:text-sm uppercase tracking-wider bg-ink text-white shadow-[2px_2px_0_#0033ff]" aria-current="page">Register</a>
  </div>

  @if (session('status'))<p class="mb-4 font-mono text-[11px] text-cobalt font-bold" role="status">{{ session('status') }}</p>@endif

  <form method="POST" action="{{ route('register.attempt') }}" class="space-y-4">
    @csrf
    <div>
      <label for="name" class="b-label">Developer name *</label>
      <input id="name" name="name" required value="{{ old('name') }}" autocomplete="name" placeholder="Alex Vance" class="b-input">
      @error('name')<p class="mt-1 font-mono text-[11px] text-red-600 font-bold">{{ $message }}</p>@enderror
    </div>
    @unless ($invite)
    <div>
      <label for="team" class="b-label">Organization / team (optional)</label>
      <input id="team" name="team" value="{{ old('team') }}" placeholder="Acme Corp" class="b-input">
    </div>
    @endunless
    <div>
      <label for="email" class="b-label">Work email *</label>
      <input id="email" name="email" type="email" required value="{{ old('email') }}" autocomplete="email" placeholder="dev@organization.io" class="b-input">
      @error('email')<p class="mt-1 font-mono text-[11px] text-red-600 font-bold">{{ $message }}</p>@enderror
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label for="password" class="b-label">Passphrase *</label>
        <input id="password" name="password" type="password" required minlength="10" autocomplete="new-password" class="b-input">
        @error('password')<p class="mt-1 font-mono text-[11px] text-red-600 font-bold">{{ $message }}</p>@enderror
      </div>
      <div>
        <label for="password_confirmation" class="b-label">Ulangi *</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="b-input">
      </div>
    </div>
    <p class="font-mono text-[10px] text-zinc-600">Minimal 10 karakter, mengandung huruf dan angka.</p>
    <label class="flex items-start gap-2 pt-1 font-mono text-[10px] leading-tight text-zinc-700">
      <input type="checkbox" name="terms" value="1" required class="mt-0.5 accent-[#0033FF]">
      <span>I agree to Notifora Developer Terms, plan-based rate limits, and zero-spam policy.</span>
    </label>
    @error('terms')<p class="font-mono text-[11px] text-red-600 font-bold">Anda harus menyetujui syarat.</p>@enderror
    <button type="submit" class="b-btn w-full mt-2">Create account <span aria-hidden="true">↗</span></button>
    <p class="font-mono text-[10px] text-zinc-600">Setelah daftar, API key dibuat di Console → Settings → API Keys.</p>
  </form>
</div>
@endsection
