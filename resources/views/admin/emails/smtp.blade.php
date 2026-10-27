@extends('layouts.admin')
@section('title', 'SMTP & uji kirim')
@section('content')
<x-page-header title="SMTP & uji kirim" :subtitle="'Mailer aktif: '.$mailer.' · pengirim '.$cfg['from_address']">
  <a href="{{ route('admin.emails.logs') }}" class="btn-secondary">Log email</a>
</x-page-header>

<div class="grid lg:grid-cols-2 gap-5">
  <form method="POST" action="{{ route('admin.emails.smtp-update') }}" class="card card-pad space-y-4">
    @csrf @method('PUT')
    <h2 class="font-semibold text-white">Konfigurasi SMTP</h2>
    <div class="grid sm:grid-cols-3 gap-3">
      <div class="sm:col-span-2"><label class="label" for="host">Host</label><input id="host" name="host" required class="input input-mono" value="{{ old('host', $cfg['host']) }}" placeholder="smtp-relay.brevo.com">@error('host')<p class="error-text">{{ $message }}</p>@enderror</div>
      <div><label class="label" for="port">Port</label><input id="port" name="port" type="number" required class="input input-mono" value="{{ old('port', $cfg['port']) }}">@error('port')<p class="error-text">{{ $message }}</p>@enderror</div>
    </div>
    <div class="grid sm:grid-cols-2 gap-3">
      <div><label class="label" for="username">Username / login</label><input id="username" name="username" autocomplete="off" class="input input-mono" value="{{ old('username', $cfg['username']) }}"></div>
      <div><label class="label" for="password">Password / SMTP key</label><input id="password" name="password" type="password" autocomplete="new-password" class="input input-mono" placeholder="{{ $cfg['has_password'] ? '•••••••• (kosongkan untuk tetap)' : 'belum diisi' }}"></div>
    </div>
    <div>
      <label class="label" for="encryption">Enkripsi</label>
      <select id="encryption" name="encryption" class="input"><option value="smtp" @selected(old('encryption', $cfg['encryption']) === 'smtp')>STARTTLS (port 587 / 2525)</option><option value="smtps" @selected(old('encryption', $cfg['encryption']) === 'smtps')>SSL/TLS langsung (port 465)</option></select>
    </div>
    <div class="grid sm:grid-cols-2 gap-3">
      <div><label class="label" for="from_address">Alamat pengirim</label><input id="from_address" name="from_address" type="email" required class="input" value="{{ old('from_address', $cfg['from_address']) }}">@error('from_address')<p class="error-text">{{ $message }}</p>@enderror</div>
      <div><label class="label" for="from_name">Nama pengirim</label><input id="from_name" name="from_name" class="input" value="{{ old('from_name', $cfg['from_name']) }}"></div>
    </div>
    <label class="flex items-start gap-3"><input type="checkbox" name="override" value="1" @checked(old('override', $override)) class="mt-1 accent-[#0033FF]"><span><span class="block text-sm text-white">Pakai konfigurasi ini (menggantikan .env)</span><span class="hint">Password tersimpan terenkripsi dengan APP_KEY. Matikan untuk kembali ke nilai .env.</span></span></label>
    <button class="btn-primary">Simpan konfigurasi</button>
  </form>

  <div class="space-y-5">
    <section class="card card-pad">
      <h2 class="font-semibold text-white mb-1">1. Uji koneksi &amp; login</h2>
      <p class="hint mb-3">Membuka koneksi ke server SMTP dan mencoba autentikasi, <strong>tanpa mengirim email</strong>. Gunakan setelah menyimpan.</p>
      <form method="POST" action="{{ route('admin.emails.smtp-probe') }}">@csrf<button class="btn-secondary">Uji koneksi SMTP</button></form>
    </section>
    <section class="card card-pad">
      <h2 class="font-semibold text-white mb-1">2. Kirim email uji</h2>
      <form method="POST" action="{{ route('admin.emails.test') }}" class="space-y-3">
        @csrf
        <div><label class="label" for="to">Kirim ke</label><input id="to" name="to" type="email" required class="input" value="{{ old('to', auth()->user()->email) }}">@error('to')<p class="error-text">{{ $message }}</p>@enderror</div>
        <button class="btn-secondary">Kirim email uji</button>
      </form>
      <p class="hint mt-3">Hasil pengiriman tampil di <a class="text-link hover:underline" href="{{ route('admin.emails.logs') }}">Log email</a>. Bila mailer aktif <code>log</code>/<code>array</code>, email tidak benar-benar dikirim.</p>
    </section>
    <section class="card card-pad">
      <h2 class="font-semibold text-white mb-2">Agar email tidak masuk spam</h2>
      <ul class="text-xs text-zinc-400 space-y-1 list-disc pl-5">
        <li>Verifikasi domain <code>notifora.my.id</code> di penyedia SMTP (Brevo: <em>Senders, Domains &amp; Dedicated IPs</em>).</li>
        <li>Pasang record DNS <strong>SPF</strong>, <strong>DKIM</strong>, dan <strong>DMARC</strong> yang diberikan penyedia.</li>
        <li>Alamat pengirim harus pada domain yang sama dengan yang diverifikasi.</li>
        <li>Email dukungan saat ini: <code>{{ $supportEmail }}</code> (ubah di Branding &amp; kontak).</li>
      </ul>
    </section>
  </div>
</div>
@endsection
