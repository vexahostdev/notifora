@extends('layouts.admin')
@section('title', 'Pengguna')
@section('content')
<x-page-header title="Pengguna & operator" subtitle="Semua akun terdaftar, termasuk operator platform." />
<form method="GET" class="flex flex-wrap gap-2 mb-4">
  <input name="q" value="{{ request('q') }}" placeholder="Nama atau email" class="input max-w-xs" aria-label="Cari">
  <select name="filter" class="input w-auto" aria-label="Filter" onchange="this.form.submit()">
    <option value="">Semua</option>
    @foreach (['admins' => 'Operator platform', 'suspended' => 'Ditangguhkan', 'no2fa' => 'Tanpa 2FA', 'unverified' => 'Email belum terverifikasi'] as $v => $l)<option value="{{ $v }}" @selected(request('filter') === $v)>{{ $l }}</option>@endforeach
  </select>
  <button class="btn-secondary">Cari</button>
</form>
<div class="card overflow-hidden"><div class="overflow-x-auto"><table class="w-full">
  <thead><tr><th class="th">Pengguna</th><th class="th">Workspace</th><th class="th">2FA</th><th class="th">Email</th><th class="th">Login terakhir</th><th class="th">Dibuat</th></tr></thead>
  <tbody>
  @foreach ($users as $u)
    <tr class="tr">
      <td class="td"><a href="{{ route('admin.users.show', $u) }}" class="font-medium text-white hover:text-link">{{ $u->name }}</a>
        @if ($u->isPlatformAdmin())<span class="ml-1 text-[10px] font-mono text-amber-300 border border-amber-400/40 rounded px-1">OPERATOR</span>@endif
        @if ($u->isSuspended())<span class="ml-1 text-[10px] font-mono text-err border border-err/40 rounded px-1">SUSPEND</span>@endif
        <span class="block text-xs font-mono text-zinc-500">{{ $u->email }}</span></td>
      <td class="td font-mono">{{ $u->memberships_count }}</td>
      <td class="td">{{ $u->hasTwoFactor() ? '✓' : '—' }}</td>
      <td class="td">{{ $u->email_verified_at ? '✓' : '—' }}</td>
      <td class="td text-zinc-500 whitespace-nowrap">{{ $u->last_login_at?->diffForHumans() ?? '—' }}</td>
      <td class="td text-zinc-500 whitespace-nowrap">{{ $u->created_at->format('d M Y') }}</td>
    </tr>
  @endforeach
  </tbody></table></div>
  <div class="px-4 py-3 border-t border-line">{{ $users->links() }}</div></div>
@endsection
