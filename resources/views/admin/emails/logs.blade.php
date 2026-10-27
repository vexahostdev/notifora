@extends('layouts.admin')
@section('title', 'Log email')
@section('content')
<x-page-header title="Log email sistem" :subtitle="'Mailer aktif: '.$mailer.' · 24 jam: '.($counts['sent'] ?? 0).' terkirim, '.($counts['failed'] ?? 0).' gagal'">
  <a href="{{ route('admin.emails.templates') }}" class="btn-secondary">Template</a>
</x-page-header>

<form method="POST" action="{{ route('admin.emails.test') }}" class="card card-pad mb-4 flex flex-wrap items-end gap-3">
  @csrf
  <div class="flex-1 min-w-56"><label class="label" for="to">Uji kredensial SMTP — kirim email uji ke</label><input id="to" name="to" type="email" required class="input" value="{{ old('to', auth()->user()->email) }}">@error('to')<p class="error-text">{{ $message }}</p>@enderror</div>
  <button class="btn-secondary">Kirim email uji</button>
</form>

<form method="GET" class="flex flex-wrap gap-2 mb-4">
  <input name="q" value="{{ request('q') }}" placeholder="Penerima atau subjek" class="input max-w-xs" aria-label="Cari">
  <select name="status" class="input w-auto" aria-label="Status" onchange="this.form.submit()"><option value="">Semua status</option>@foreach (['queued', 'sent', 'logged', 'failed', 'skipped'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select>
  <select name="kind" class="input w-auto" aria-label="Jenis" onchange="this.form.submit()"><option value="">Semua jenis</option>@foreach ($kinds as $k => $l)<option value="{{ $k }}" @selected(request('kind') === $k)>{{ $l }}</option>@endforeach</select>
  <button class="btn-secondary">Cari</button>
</form>
<div class="card overflow-hidden">
  @if ($logs->isEmpty())<x-empty-state icon="bell" title="Belum ada email" text="Email sistem akan tercatat di sini." />@else
  <div class="overflow-x-auto"><table class="w-full">
    <thead><tr><th class="th">Waktu</th><th class="th">Jenis</th><th class="th">Penerima</th><th class="th">Subjek</th><th class="th">Status</th></tr></thead>
    <tbody>
    @foreach ($logs as $l)
      <tr class="tr">
        <td class="td whitespace-nowrap text-zinc-500">{{ $l->created_at->setTimezone('Asia/Jakarta')->format('d M H:i:s') }}</td>
        <td class="td font-mono text-xs">{{ $l->kind }}</td>
        <td class="td font-mono text-xs">{{ $l->recipient }}@if ($l->workspace)<span class="block text-zinc-500 font-sans">{{ $l->workspace->name }}</span>@endif</td>
        <td class="td max-w-xs truncate">{{ $l->subject }}</td>
        <td class="td"><x-status-badge :status="$l->status" />@if ($l->error)<span class="block text-[10px] text-red-300 max-w-xs break-words">{{ \Illuminate\Support\Str::limit($l->error, 120) }}</span>@endif</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  <div class="px-4 py-3 border-t border-line">{{ $logs->links() }}</div>
  @endif
</div>
@endsection
