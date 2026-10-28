@extends('layouts.app')
@section('title', 'Account')
@section('content')
<x-page-header title="Account" subtitle="Profil dan keamanan akun." />
<div class="grid lg:grid-cols-2 gap-5">
  <form method="POST" action="{{ route('console.account.update') }}" class="card card-pad space-y-4">
    @csrf @method('PUT')
    <h2 class="font-semibold text-white">Profil</h2>
    <div><label class="label" for="n">Nama</label><input id="n" name="name" required class="input" value="{{ old('name', auth()->user()->name) }}">@error('name')<p class="error-text" role="alert">{{ $message }}</p>@enderror</div>
    <div><label class="label" for="e">Email</label><input id="e" name="email" type="email" required class="input" value="{{ old('email', auth()->user()->email) }}">@error('email')<p class="error-text" role="alert">{{ $message }}</p>@enderror</div>
    <button class="btn-primary">Simpan</button>
  </form>
  <form method="POST" action="{{ route('console.account.password') }}" class="card card-pad space-y-4">
    @csrf @method('PUT')
    <h2 class="font-semibold text-white">Ganti password</h2>
    <div><label class="label" for="cp">Password saat ini</label><input id="cp" name="current_password" type="password" required autocomplete="current-password" class="input"></div>
    <div><label class="label" for="np">Password baru</label><input id="np" name="password" type="password" required minlength="10" autocomplete="new-password" class="input"><p class="hint">Minimal 10 karakter, huruf dan angka.</p></div>
    <div><label class="label" for="np2">Ulangi password baru</label><input id="np2" name="password_confirmation" type="password" required autocomplete="new-password" class="input"></div>
    <button class="btn-primary">Ubah password</button>
  </form>
</div>
@endsection
