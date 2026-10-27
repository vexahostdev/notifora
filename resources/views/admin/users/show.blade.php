@extends('layouts.admin')
@section('title', $u->name)
@section('content')
<x-page-header :title="$u->name" :subtitle="$u->email">
  <a href="{{ route('admin.users.index') }}" class="btn-secondary">← Pengguna</a>
</x-page-header>

@if ($u->isSuspended())<div class="mb-5 rounded-md border border-err/40 bg-err/10 px-4 py-3 text-sm text-red-200" role="alert"><strong>Ditangguhkan</strong> — {{ $u->suspended_reason }}</div>@endif

<div class="grid lg:grid-cols-3 gap-5">
  <div class="lg:col-span-2 space-y-5">
    <section class="card card-pad">
      <dl class="grid sm:grid-cols-3 gap-4 text-sm">
        <div><dt class="label !mb-0">ID</dt><dd class="font-mono text-xs">{{ $u->id }}</dd></div>
        <div><dt class="label !mb-0">Email terverifikasi</dt><dd>{{ $u->email_verified_at ? $u->email_verified_at->translatedFormat('d M Y') : 'Belum' }}</dd></div>
        <div><dt class="label !mb-0">2FA</dt><dd>{{ $u->hasTwoFactor() ? 'Aktif' : 'Tidak aktif' }}</dd></div>
        <div><dt class="label !mb-0">Login terakhir</dt><dd>{{ $u->last_login_at?->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i') ?? '—' }}</dd></div>
        <div><dt class="label !mb-0">IP terakhir</dt><dd class="font-mono text-xs">{{ $u->last_login_ip ?? '—' }}</dd></div>
        <div><dt class="label !mb-0">Terdaftar</dt><dd>{{ $u->created_at->translatedFormat('d M Y H:i') }}</dd></div>
      </dl>
    </section>
    <section class="card overflow-hidden">
      <h2 class="font-semibold text-white px-5 pt-4 pb-3">Keanggotaan workspace</h2>
      <div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="th">Workspace</th><th class="th">Peran</th><th class="th">Plan</th></tr></thead><tbody>
        @forelse ($memberships as $m)<tr class="tr"><td class="td"><a href="{{ route('admin.tenants.show', $m->workspace) }}" class="text-white hover:text-link">{{ $m->workspace->name }}</a>@if ($m->workspace->suspended_at)<span class="ml-1 text-[10px] font-mono text-err">SUSPEND</span>@endif</td><td class="td font-mono text-xs">{{ $m->role->value }}</td><td class="td">{{ $m->workspace->planConfig()['label'] }}</td></tr>
        @empty<tr><td class="td text-zinc-500" colspan="3">Tidak tergabung di workspace mana pun.</td></tr>@endforelse
      </tbody></table></div>
    </section>
  </div>

  <aside class="space-y-5">
    <section class="card card-pad space-y-2">
      <h2 class="font-semibold text-white mb-1">Tindakan akun</h2>
      <form method="POST" action="{{ route('admin.users.send-reset', $u) }}">@csrf<button class="btn-secondary w-full">Kirim tautan reset password</button></form>
      @unless ($u->email_verified_at)<form method="POST" action="{{ route('admin.users.verify-email', $u) }}">@csrf<button class="btn-secondary w-full">Tandai email terverifikasi</button></form>@endunless
      @if ($u->hasTwoFactor())<form method="POST" action="{{ route('admin.users.reset-2fa', $u) }}" onsubmit="return confirm('Reset 2FA pengguna ini?')">@csrf<button class="btn-secondary w-full">Reset 2FA</button></form>@endif
    </section>

    <section class="card card-pad">
      <h2 class="font-semibold text-white mb-2">Operator platform</h2>
      <p class="hint mb-3">{{ $u->isPlatformAdmin() ? 'Pengguna ini adalah operator platform.' : 'Beri akses penuh ke panel /admin (wajib 2FA).' }}</p>
      @if ($u->isPlatformAdmin() && auth()->user()->adminRole() === 'super')
        <form method="POST" action="{{ route('admin.users.role', $u) }}" class="mb-3">@csrf @method('PUT')
          <label class="label" for="role">Peran operator</label>
          <div class="flex gap-2">
            <select id="role" name="role" class="input">@foreach (config('admin.roles') as $k => $r)<option value="{{ $k }}" @selected($u->adminRole() === $k)>{{ $r['label'] }}</option>@endforeach</select>
            <button class="btn-secondary">Simpan</button>
          </div>
          <p class="hint mt-1">Super: semua bagian · Finance: pembayaran, plan, laporan · Support: tenant, pengguna, email · Operasional: ops &amp; sistem.</p>
        </form>
      @elseif ($u->isPlatformAdmin())
        <p class="hint mb-3">Peran: <strong>{{ config('admin.roles.'.$u->adminRole().'.label') }}</strong></p>
      @endif
      <form method="POST" action="{{ route('admin.users.toggle-admin', $u) }}" onsubmit="return confirm('{{ $u->isPlatformAdmin() ? 'Cabut' : 'Beri' }} akses operator?')">@csrf
        <button class="{{ $u->isPlatformAdmin() ? 'btn-danger' : 'btn-primary' }} w-full">{{ $u->isPlatformAdmin() ? 'Cabut akses operator' : 'Jadikan operator' }}</button></form>
    </section>

    <section class="card card-pad">
      <h2 class="font-semibold text-white mb-2">{{ $u->isSuspended() ? 'Aktifkan akun' : 'Tangguhkan akun' }}</h2>
      @if ($u->isSuspended())
        <form method="POST" action="{{ route('admin.users.unsuspend', $u) }}">@csrf<button class="btn-primary w-full">Cabut penangguhan</button></form>
      @else
        <form method="POST" action="{{ route('admin.users.suspend', $u) }}" class="space-y-2" onsubmit="return confirm('Tangguhkan akun ini?')">@csrf
          <input name="reason" required maxlength="255" class="input" placeholder="Alasan" aria-label="Alasan"><button class="btn-danger w-full">Tangguhkan</button></form>
      @endif
    </section>
  </aside>
</div>
@endsection
