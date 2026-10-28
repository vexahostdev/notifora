@extends('layouts.admin')
@section('title', 'Pengaturan platform')
@section('content')
<x-page-header title="Pengaturan platform" subtitle="Flag operasional yang berlaku langsung tanpa deploy." />

<div class="card card-pad border-2 {{ ($values['signup_enabled'] ?? '1') === '1' ? 'border-line' : 'border-amber-500/80 bg-amber-500/5' }} mb-5">
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <div class="flex items-center gap-2">
        <span class="inline-block w-2.5 h-2.5 rounded-full {{ ($values['signup_enabled'] ?? '1') === '1' ? 'bg-emerald-400' : 'bg-amber-400 animate-pulse' }}"></span>
        <h2 class="font-semibold text-white text-base">Status Pendaftaran Akun</h2>
        @if (($values['signup_enabled'] ?? '1') === '1')
          <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">TERBUKA</span>
        @else
          <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/40">TERKUNCI</span>
        @endif
      </div>
      <p class="hint mt-1 max-w-xl">
        @if (($values['signup_enabled'] ?? '1') === '1')
          Publik dapat mendaftar langsung dari halaman /register.
        @else
          Pendaftaran publik diblokir (halaman /register menampilkan pesan terkunci). Hanya pengguna yang memiliki undangan tim (invite token) yang dapat mendaftar.
        @endif
      </p>
    </div>
    <form method="POST" action="{{ route('admin.settings.toggle-signup') }}">
      @csrf
      @if (($values['signup_enabled'] ?? '1') === '1')
        <button type="submit" class="btn-danger" onclick="return confirm('Kunci pendaftaran akun baru sekarang? Pengunjung tidak akan bisa mendaftar.')">
          Kunci Pendaftaran Akun
        </button>
      @else
        <button type="submit" class="btn-primary">
          Buka Pendaftaran Akun
        </button>
      @endif
    </form>
  </div>
</div>

<div class="grid lg:grid-cols-2 gap-5">
  <form method="POST" action="{{ route('admin.settings.update') }}" class="card card-pad space-y-4">
    @csrf @method('PUT')
    <h2 class="font-semibold text-white">Flag Operasional</h2>
    @foreach ($flags as $key => $label)
      <label class="flex items-start gap-3"><input type="checkbox" name="{{ $key }}" value="1" @checked(($values[$key] ?? '1') === '1') class="mt-1 accent-[#0033FF]">
        <span class="text-sm text-white">{{ $label }}</span></label>
    @endforeach

    <div class="border-t border-line pt-4">
      <label class="label" for="slm">Pesan saat pendaftaran dikunci</label>
      <textarea id="slm" name="signup_lock_message" rows="2" maxlength="500" class="input" placeholder="Pendaftaran akun baru sedang ditutup sementara untuk pengujian sistem.">{{ $values['signup_lock_message'] ?? '' }}</textarea>
      <p class="hint mt-1">Pesan ini ditampilkan kepada publik di halaman /register saat pendaftaran dikunci.</p>
    </div>

    <div class="border-t border-line pt-4">
      <label class="flex items-start gap-3"><input type="checkbox" name="maintenance" value="1" @checked(($values['maintenance'] ?? '0') === '1') class="mt-1 accent-[#0033FF]">
        <span><span class="block text-sm text-white">Mode maintenance</span><span class="hint">Console, API, dan halaman publik menampilkan 503. Operator tetap bisa masuk ke /admin.</span></span></label>
      <label class="label mt-3" for="mm">Pesan maintenance</label>
      <textarea id="mm" name="maintenance_message" rows="3" maxlength="500" class="input">{{ $values['maintenance_message'] ?? '' }}</textarea>
    </div>
    <button class="btn-primary">Simpan pengaturan</button>
  </form>

  <section class="card card-pad">
    <h2 class="font-semibold text-white mb-3">Informasi lingkungan</h2>
    <dl class="space-y-2 text-sm">@foreach ($info as $k => $v)<div><dt class="label !mb-0">{{ $k }}</dt><dd class="font-mono text-xs break-words">{{ $v }}</dd></div>@endforeach</dl>
  </section>
</div>
@endsection
