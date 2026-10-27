@extends('layouts.guest')
@section('title', 'Lupa password')
@section('content')
<div class="bg-paper border-[3px] border-ink shadow-brutal p-6 sm:p-9">
  <h1 class="font-display uppercase text-xl mb-2">Recover access</h1>
  <p class="font-mono text-[11px] text-zinc-600 mb-5">Masukkan email akun. Kami kirim tautan reset (berlaku 60 menit).</p>
  @if (session('status'))<p class="mb-4 font-mono text-[11px] text-cobalt font-bold" role="status">{{ session('status') }}</p>@endif
  <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
    @csrf
    <div>
      <label for="email" class="b-label">Email *</label>
      <input id="email" name="email" type="email" required value="{{ old('email') }}" class="b-input">
      @error('email')<p class="mt-1 font-mono text-[11px] text-red-600 font-bold">{{ $message }}</p>@enderror
    </div>
    <button class="b-btn w-full">Kirim tautan</button>
    <a href="{{ route('login') }}" class="block text-center font-mono text-[11px] text-cobalt hover:underline">← Kembali masuk</a>
  </form>
</div>
@endsection
