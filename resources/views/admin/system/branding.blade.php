@extends('layouts.admin')
@section('title', 'Branding & kontak')
@section('content')
<x-page-header title="Branding & kontak" subtitle="Nama, logo, warna aksen, footer, dan kontak. Berlaku langsung di situs, Console, docs, dan email." />

<form method="POST" action="{{ route('admin.system.branding.update') }}" enctype="multipart/form-data" class="grid lg:grid-cols-2 gap-5">
  @csrf @method('PUT')

  <section class="card card-pad space-y-4">
    <h2 class="font-semibold text-white">Identitas</h2>
    <div><label class="label" for="brand_name">Nama merek</label><input id="brand_name" name="brand_name" required maxlength="40" class="input" value="{{ old('brand_name', $v['brand_name'] ?? 'Notifora') }}">@error('brand_name')<p class="error-text">{{ $message }}</p>@enderror</div>
    <div><label class="label" for="brand_tagline">Tagline</label><input id="brand_tagline" name="brand_tagline" maxlength="140" class="input" value="{{ old('brand_tagline', $v['brand_tagline'] ?? '') }}" placeholder="Satu API untuk semua notifikasi"></div>
    <div class="grid sm:grid-cols-2 gap-3 items-end">
      <div><label class="label" for="brand_accent">Warna aksen</label>
        <div class="flex gap-2"><input type="color" value="{{ old('brand_accent', $accent) }}" class="h-10 w-12 rounded border border-line bg-transparent p-0.5" oninput="document.getElementById('brand_accent').value=this.value" aria-label="Pilih warna">
          <input id="brand_accent" name="brand_accent" required pattern="#[0-9A-Fa-f]{6}" class="input input-mono" value="{{ old('brand_accent', $accent) }}"></div>
        @error('brand_accent')<p class="error-text">{{ $message }}</p>@enderror</div>
      <p class="hint">Dipakai untuk tombol utama &amp; sorotan. Pilih warna yang kontras dengan teks putih.</p>
    </div>
    <div><label class="label" for="brand_footer">Teks footer</label><input id="brand_footer" name="brand_footer" maxlength="200" class="input" value="{{ old('brand_footer', $v['brand_footer'] ?? '© {year} notifora. All rights reserved. Created by notifora') }}"><p class="hint"><code>{year}</code> diganti tahun berjalan.</p></div>
  </section>

  <section class="card card-pad space-y-4">
    <h2 class="font-semibold text-white">Logo &amp; favicon</h2>
    <div class="flex items-center gap-4">
      <span class="w-14 h-14 rounded-md bg-[#fff] border border-line flex items-center justify-center p-1 shrink-0"><img src="{{ $logo }}" alt="Logo saat ini" class="max-w-full max-h-full"></span>
      <div class="flex-1"><label class="label" for="brand_logo">Logo (PNG/JPG/WebP, maks. 1 MB, persegi)</label><input id="brand_logo" name="brand_logo" type="file" accept="image/png,image/jpeg,image/webp" class="input">@error('brand_logo')<p class="error-text">{{ $message }}</p>@enderror
        @if (!empty($v['brand_logo_path']))<label class="flex items-center gap-2 mt-2 text-xs text-zinc-400"><input type="checkbox" name="remove_logo" value="1" class="accent-[#0033FF]"> Hapus logo kustom (kembali ke bawaan)</label>@endif</div>
    </div>
    <div class="flex items-center gap-4">
      <span class="w-14 h-14 rounded-md bg-surface border border-line flex items-center justify-center shrink-0"><img src="{{ $favicon }}" alt="Favicon saat ini" class="w-8 h-8"></span>
      <div class="flex-1"><label class="label" for="brand_favicon">Favicon (PNG/ICO, maks. 256 KB)</label><input id="brand_favicon" name="brand_favicon" type="file" accept="image/png,image/x-icon" class="input">@error('brand_favicon')<p class="error-text">{{ $message }}</p>@enderror
        @if (!empty($v['brand_favicon_path']))<label class="flex items-center gap-2 mt-2 text-xs text-zinc-400"><input type="checkbox" name="remove_favicon" value="1" class="accent-[#0033FF]"> Hapus favicon kustom</label>@endif</div>
    </div>
    <p class="hint">Logo kustom disajikan dari <code>storage/</code>; jalankan <code>php artisan storage:link</code> sekali di server.</p>
  </section>

  <section class="card card-pad space-y-4">
    <h2 class="font-semibold text-white">Kontak &amp; dukungan</h2>
    <div class="grid sm:grid-cols-2 gap-3">
      <div><label class="label" for="support_email">Email dukungan</label><input id="support_email" name="support_email" type="email" class="input" value="{{ old('support_email', $v['support_email'] ?? config('notifora.support_email')) }}">@error('support_email')<p class="error-text">{{ $message }}</p>@enderror</div>
      <div><label class="label" for="support_whatsapp">WhatsApp dukungan</label><input id="support_whatsapp" name="support_whatsapp" class="input" value="{{ old('support_whatsapp', $v['support_whatsapp'] ?? '') }}" placeholder="+62…"></div>
    </div>
    <div><label class="label" for="mail_from_name">Nama pengirim email</label><input id="mail_from_name" name="mail_from_name" maxlength="60" class="input" value="{{ old('mail_from_name', $v['mail_from_name'] ?? '') }}" placeholder="Sama dengan nama merek"></div>
    <div class="grid sm:grid-cols-2 gap-3">
      @foreach (['social_instagram' => 'Instagram', 'social_x' => 'X (Twitter)', 'social_linkedin' => 'LinkedIn', 'social_github' => 'GitHub'] as $k => $l)
        <div><label class="label" for="{{ $k }}">{{ $l }}</label><input id="{{ $k }}" name="{{ $k }}" type="url" class="input" value="{{ old($k, $v[$k] ?? '') }}" placeholder="https://">@error($k)<p class="error-text">{{ $message }}</p>@enderror</div>
      @endforeach
    </div>
  </section>

  <section class="card card-pad space-y-4">
    <h2 class="font-semibold text-white">Profil perusahaan</h2>
    <p class="hint -mt-2">Dipakai di footer email dan dokumen penagihan.</p>
    <div><label class="label" for="company_legal_name">Nama badan usaha</label><input id="company_legal_name" name="company_legal_name" class="input" value="{{ old('company_legal_name', $v['company_legal_name'] ?? '') }}"></div>
    <div><label class="label" for="company_address">Alamat</label><textarea id="company_address" name="company_address" rows="3" maxlength="300" class="input">{{ old('company_address', $v['company_address'] ?? '') }}</textarea></div>
    <button class="btn-primary">Simpan branding</button>
  </section>
</form>
@endsection
