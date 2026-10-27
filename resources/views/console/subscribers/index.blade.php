@extends('layouts.app')
@section('title', 'Subscribers')
@section('content')
<x-page-header title="Subscribers" subtitle="Dibuat otomatis saat trigger atau lewat PUT /v1/subscribers/{id}." />

<form method="GET" class="flex flex-wrap gap-2 mb-4">
  <input name="q" value="{{ request('q') }}" placeholder="Cari ID / email / telepon" class="input max-w-xs" aria-label="Cari subscriber">
  <select name="channel" class="input w-auto" onchange="this.form.submit()" aria-label="Filter channel">
    <option value="">Semua channel</option>
    @foreach (['WHATSAPP' => 'Punya nomor WA', 'EMAIL' => 'Punya email', 'TELEGRAM' => 'Punya Telegram'] as $v => $l)<option value="{{ $v }}" @selected(request('channel') === $v)>{{ $l }}</option>@endforeach
  </select>
  <button class="btn-secondary">Cari</button>
</form>

<div class="card overflow-hidden">
  @if ($subscribers->isEmpty())
    <x-empty-state icon="users" title="Belum ada subscriber" text="Subscriber muncul otomatis setelah kamu memicu event pertama." />
  @else
  <div class="overflow-x-auto">
    <table class="w-full">
      <thead><tr><th class="th">Subscriber ID</th><th class="th">Nama</th><th class="th">Email</th><th class="th">Telepon</th><th class="th">Dibuat</th></tr></thead>
      <tbody>
      @foreach ($subscribers as $s)
        <tr class="tr">
          <td class="td"><a href="{{ route('console.subscribers.show', $s) }}" class="font-mono text-xs text-white hover:text-cobalt">{{ $s->external_id }}</a></td>
          <td class="td">{{ trim($s->first_name.' '.$s->last_name) ?: '—' }}</td>
          <td class="td font-mono text-xs truncate max-w-48">{{ $s->email ? \App\Support\Phone::mask($s->email) : '—' }}</td>
          <td class="td font-mono text-xs">{{ $s->phone ? \App\Support\Phone::mask($s->phone) : '—' }}</td>
          <td class="td text-zinc-500">{{ $s->created_at->diffForHumans() }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
  <div class="px-4 py-3 border-t border-line">{{ $subscribers->links() }}</div>
  @endif
</div>
@endsection
