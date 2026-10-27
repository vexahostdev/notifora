@extends('layouts.plain')
@section('title', 'Autentikasi dua faktor')
@section('content')
@php
  $back = $user->isPlatformAdmin() && ! $user->currentMembership() ? route('admin.dashboard') : ($user->currentMembership() ? route('console.account') : route('landing'));
@endphp
<x-page-header title="Autentikasi dua faktor (2FA)" subtitle="Lapisan keamanan tambahan memakai aplikasi authenticator (Google Authenticator, Authy, 1Password, dll).">
  <a href="{{ $back }}" class="btn-secondary">← Kembali</a>
</x-page-header>

@if ($mustKeep && ! $user->hasTwoFactor())
  <div class="mb-5 rounded-md border border-warn/40 bg-warn/10 px-4 py-3 text-sm text-amber-200" role="alert">Operator platform <strong>wajib</strong> mengaktifkan 2FA untuk membuka panel admin.</div>
@endif

@if ($recoveryCodes)
  <section class="card card-pad mb-5 border-ok/50" role="alert">
    <h2 class="font-semibold text-emerald-300 mb-1">Kode pemulihan — simpan sekarang</h2>
    <p class="hint mb-3">Tiap kode bisa dipakai <strong>sekali</strong> bila Anda kehilangan perangkat authenticator. Kode ini tidak akan ditampilkan lagi.</p>
    <div class="grid grid-cols-2 gap-2 font-mono text-sm">@foreach ($recoveryCodes as $c)<code class="code !py-1.5 select-all">{{ $c }}</code>@endforeach</div>
    <div class="mt-3"><x-copy-button :text="implode("\n", $recoveryCodes)" label="Salin semua" /></div>
  </section>
@endif

@if ($user->hasTwoFactor())
  <section class="card card-pad mb-5">
    <div class="flex items-center justify-between"><h2 class="font-semibold text-white">Status</h2><x-status-badge status="ACTIVE" /></div>
    <p class="text-sm text-zinc-400 mt-2">2FA aktif sejak {{ $user->two_factor_confirmed_at->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i') }} WIB. Sisa kode pemulihan: <strong class="text-white">{{ count($user->two_factor_recovery_codes ?? []) }}</strong>.</p>
  </section>

  <section class="card card-pad mb-5">
    <h2 class="font-semibold text-white mb-2">Buat ulang kode pemulihan</h2>
    <form method="POST" action="{{ route('security.2fa.recovery') }}" class="flex flex-wrap items-end gap-3">
      @csrf
      <div class="flex-1 min-w-48"><label class="label" for="rp">Password</label><input id="rp" name="password" type="password" required class="input" autocomplete="current-password">@error('password')<p class="error-text">{{ $message }}</p>@enderror</div>
      <button class="btn-secondary">Buat kode baru</button>
    </form>
  </section>

  @unless ($mustKeep)
  <section class="card card-pad border-err/30">
    <h2 class="font-semibold text-red-300 mb-2">Nonaktifkan 2FA</h2>
    <form method="POST" action="{{ route('security.2fa.disable') }}" class="grid sm:grid-cols-2 gap-3">
      @csrf @method('DELETE')
      <div><label class="label" for="dp">Password</label><input id="dp" name="password" type="password" required class="input" autocomplete="current-password"></div>
      <div><label class="label" for="dc">Kode 2FA / kode pemulihan</label><input id="dc" name="code" required class="input input-mono" autocomplete="one-time-code">@error('code')<p class="error-text">{{ $message }}</p>@enderror</div>
      <div class="sm:col-span-2"><button class="btn-danger">Nonaktifkan</button></div>
    </form>
  </section>
  @endunless
@elseif ($secret)
  <section class="card card-pad" x-data="qrCode({ text: @js($uri) })">
    <h2 class="font-semibold text-white mb-3">Pindai dan konfirmasi</h2>
    <ol class="list-decimal pl-5 space-y-1 text-sm text-zinc-300 mb-4">
      <li>Buka aplikasi authenticator, pilih tambah akun, lalu pindai kode QR ini.</li>
      <li>Atau masukkan kunci manual di bawah.</li>
      <li>Masukkan kode 6 digit yang muncul untuk mengonfirmasi.</li>
    </ol>
    <div class="flex flex-wrap items-start gap-6">
      <canvas x-ref="canvas" class="rounded-md bg-[#fff] p-2 w-44 h-44" aria-label="Kode QR 2FA"></canvas>
      <div class="min-w-0 flex-1">
        <p class="label">Kunci manual</p>
        <code class="code block break-all select-all">{{ $secret }}</code>
        <form method="POST" action="{{ route('security.2fa.confirm') }}" class="mt-4 flex items-end gap-3">
          @csrf
          <div class="flex-1"><label class="label" for="c">Kode 6 digit</label><input id="c" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus class="input input-mono tracking-widest">@error('code')<p class="error-text">{{ $message }}</p>@enderror</div>
          <button class="btn-primary">Aktifkan</button>
        </form>
      </div>
    </div>
  </section>
@else
  <section class="card card-pad">
    <h2 class="font-semibold text-white">2FA belum aktif</h2>
    <p class="text-sm text-zinc-400 mt-1 mb-4">Saat masuk, Anda akan diminta kode dari aplikasi authenticator selain password.</p>
    <form method="POST" action="{{ route('security.2fa.start') }}">@csrf<button class="btn-primary">Mulai aktifkan 2FA</button></form>
  </section>
@endif
@endsection
