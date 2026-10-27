@extends('layouts.app')
@section('title', 'Team')
@section('content')
<x-page-header title="Team" subtitle="Kelola anggota workspace dan role." />

<form method="POST" action="{{ route('console.team.invite') }}" class="card card-pad flex flex-wrap items-end gap-3 mb-6">
  @csrf
  <div class="flex-1 min-w-52"><label class="label" for="ie">Email</label><input id="ie" name="email" type="email" required class="input" value="{{ old('email') }}">@error('email')<p class="error-text">{{ $message }}</p>@enderror</div>
  <div><label class="label" for="ir">Role</label><select id="ir" name="role" class="input">@foreach ($roles as $r)@if ($r !== \App\Enums\MemberRole::OWNER || $currentRole === \App\Enums\MemberRole::OWNER)<option value="{{ $r->value }}" @selected($r === \App\Enums\MemberRole::DEVELOPER)>{{ $r->value }}</option>@endif @endforeach</select></div>
  <button class="btn-primary">Kirim undangan</button>
</form>

<div class="card overflow-hidden mb-6">
  <div class="overflow-x-auto"><table class="w-full">
    <thead><tr><th class="th">Nama</th><th class="th">Email</th><th class="th">Role</th><th class="th"></th></tr></thead>
    <tbody>
    @foreach ($members as $m)
      <tr class="tr">
        <td class="td text-white">{{ $m->user->name }} @if ($m->user_id === auth()->id())<span class="text-xs text-zinc-500">(Anda)</span>@endif</td>
        <td class="td font-mono text-xs">{{ $m->user->email }}</td>
        <td class="td">
          <form method="POST" action="{{ route('console.team.update', $m) }}" class="flex items-center gap-2">@csrf @method('PUT')
            <select name="role" class="input !py-1 !text-xs w-32" aria-label="Role {{ $m->user->name }}" onchange="this.form.submit()" @disabled($m->role === \App\Enums\MemberRole::OWNER && $currentRole !== \App\Enums\MemberRole::OWNER)>
              @foreach ($roles as $r)@if ($r !== \App\Enums\MemberRole::OWNER || $currentRole === \App\Enums\MemberRole::OWNER)<option value="{{ $r->value }}" @selected($m->role === $r)>{{ $r->value }}</option>@endif @endforeach
            </select>
          </form>
        </td>
        <td class="td text-right">@if ($m->user_id !== auth()->id())<form method="POST" action="{{ route('console.team.destroy', $m) }}" onsubmit="return confirm('Hapus anggota ini?')">@csrf @method('DELETE')<button class="btn-danger btn-sm">Hapus</button></form>@endif</td>
      </tr>
    @endforeach
    </tbody></table></div>
</div>

@if ($invites->isNotEmpty())
<div class="card overflow-hidden">
  <h2 class="font-semibold text-white px-5 pt-4 pb-3">Undangan tertunda</h2>
  <div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="th">Email</th><th class="th">Role</th><th class="th">Berlaku sampai</th><th class="th"></th></tr></thead><tbody>
    @foreach ($invites as $i)<tr class="tr"><td class="td font-mono text-xs">{{ $i->email }}</td><td class="td">{{ $i->role }}</td><td class="td text-zinc-500">{{ $i->expires_at->format('d M Y') }}</td>
      <td class="td text-right"><form method="POST" action="{{ route('console.team.invite.cancel', $i->id) }}">@csrf @method('DELETE')<button class="btn-ghost btn-sm">Batalkan</button></form></td></tr>@endforeach
  </tbody></table></div>
</div>
@endif
@endsection
