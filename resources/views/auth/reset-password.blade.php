@extends('layouts.guest')
@section('title', 'Reset password')
@section('content')
<div class="bg-paper border-[3px] border-ink shadow-brutal p-6 sm:p-9">
  <h1 class="font-display uppercase text-xl mb-5">Set new passphrase</h1>
  <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div>
      <label for="email" class="b-label">Email *</label>
      <input id="email" name="email" type="email" required value="{{ old('email', $email) }}" class="b-input">
      @error('email')<p class="mt-1 font-mono text-[11px] text-red-600 font-bold">{{ $message }}</p>@enderror
    </div>
    <div>
      <label for="password" class="b-label">Password baru *</label>
      <input id="password" name="password" type="password" required minlength="10" autocomplete="new-password" class="b-input">
      @error('password')<p class="mt-1 font-mono text-[11px] text-red-600 font-bold">{{ $message }}</p>@enderror
    </div>
    <div>
      <label for="password_confirmation" class="b-label">Ulangi password *</label>
      <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="b-input">
    </div>
    <button class="b-btn w-full">Simpan password</button>
  </form>
</div>
@endsection
