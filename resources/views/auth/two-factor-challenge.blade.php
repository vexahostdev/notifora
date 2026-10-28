@extends('layouts.guest')
@section('title', 'Verifikasi 2FA')
@section('content')
<div class="bg-paper border-[3px] border-ink shadow-brutal p-4 sm:p-9" x-data="{
  recovery: @js(old('recovery_code') !== null),
  init() { this.$nextTick(() => (this.recovery ? this.$refs.recoveryInput : this.$refs.codeInput)?.focus()); },
  toggle() {
    this.recovery = !this.recovery;
    this.$nextTick(() => (this.recovery ? this.$refs.recoveryInput : this.$refs.codeInput)?.focus());
  }
}">
  <h1 class="font-display uppercase text-xl mb-2">Two-factor check</h1>
  <p class="font-mono text-[11px] text-zinc-600 mb-5" x-show="!recovery">Masukkan kode 6 digit dari aplikasi authenticator Anda.</p>
  <p class="font-mono text-[11px] text-zinc-600 mb-5" x-show="recovery" x-cloak>Masukkan salah satu kode pemulihan (sekali pakai).</p>

  <form method="POST" action="{{ route('two-factor.verify') }}" class="space-y-4">
    @csrf
    <div x-show="!recovery">
      <label for="code" class="b-label">Kode 2FA</label>
      <input x-ref="codeInput" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" class="b-input tracking-[.4em] text-center text-base" :disabled="recovery" :required="!recovery" value="{{ old('code') }}">
    </div>
    <div x-show="recovery" x-cloak>
      <label for="recovery_code" class="b-label">Kode pemulihan</label>
      <input x-ref="recoveryInput" id="recovery_code" name="recovery_code" autocomplete="off" placeholder="xxxxxx-xxxx" class="b-input" :disabled="!recovery" :required="recovery" value="{{ old('recovery_code') }}">
    </div>
    @error('code')<p class="font-mono text-[11px] text-red-600 font-bold" role="alert">{{ $message }}</p>@enderror
    <button class="b-btn w-full">Verifikasi</button>
    <button type="button" @click="toggle()" class="block w-full text-center font-mono text-[11px] text-cobalt hover:underline" x-text="recovery ? '← Pakai kode authenticator' : 'Pakai kode pemulihan'">Pakai kode pemulihan</button>
    <a href="{{ route('login') }}" class="block text-center font-mono text-[11px] hover:underline">Batal</a>
  </form>
</div>
@endsection
