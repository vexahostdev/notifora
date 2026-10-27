@extends('layouts.app')
@section('title', 'Webhooks')
@section('content')
<x-page-header title="Webhooks" subtitle="Terima callback status pengiriman di server kamu. Setiap request bertanda tangan HMAC-SHA256." />

@if ($newSecret)
  <section class="card card-pad mb-6 border-ok/50" role="alert">
    <h2 class="font-semibold text-emerald-300 mb-1">Signing secret — salin sekarang</h2>
    <p class="hint mb-3">Tidak akan ditampilkan lagi. Verifikasi header <code>X-Notifora-Signature: t=&lt;unix&gt;,v1=&lt;hex&gt;</code> = HMAC-SHA256(secret, "t.body"), toleransi 5 menit.</p>
    <div class="flex flex-wrap items-center gap-2"><code class="code flex-1 min-w-0 break-all select-all">{{ $newSecret }}</code><x-copy-button :text="$newSecret" /></div>
  </section>
@endif

<form method="POST" action="{{ route('console.webhooks.store') }}" class="card card-pad space-y-4 mb-6">
  @csrf
  <div><label class="label" for="wu">Endpoint URL (https)</label><input id="wu" name="url" type="url" required value="{{ old('url') }}" class="input input-mono" placeholder="https://api.contoh.com/notifora/webhook">@error('url')<p class="error-text">{{ $message }}</p>@enderror</div>
  <fieldset><legend class="label">Event</legend>
    <div class="grid sm:grid-cols-3 gap-2">
      @foreach ($events as $e)<label class="flex items-center gap-2 text-sm font-mono"><input type="checkbox" name="events[]" value="{{ $e }}" class="accent-[#0033FF]" @checked(in_array($e, old('events', ['message.failed', 'execution.completed'])))> {{ $e }}</label>@endforeach
    </div></fieldset>
  <button class="btn-primary"><x-icon name="plus" />Tambah webhook</button>
</form>

<div class="card overflow-hidden mb-6">
  <h2 class="font-semibold text-white px-5 pt-4 pb-3">Endpoint terdaftar</h2>
  @if ($subs->isEmpty())<p class="px-5 pb-6 text-sm text-zinc-500">Belum ada webhook.</p>@else
  <div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="th">URL</th><th class="th">Event</th><th class="th">Status</th><th class="th">Gagal beruntun</th><th class="th"></th></tr></thead><tbody>
    @foreach ($subs as $s)<tr class="tr">
      <td class="td font-mono text-xs break-all max-w-xs">{{ $s->url }}</td>
      <td class="td text-xs text-zinc-400">{{ implode(', ', $s->events) }}</td>
      <td class="td"><x-status-badge :status="$s->status" /></td>
      <td class="td font-mono">{{ $s->consecutive_failures }}</td>
      <td class="td text-right whitespace-nowrap">
        <form method="POST" action="{{ route('console.webhooks.toggle', $s) }}" class="inline">@csrf<button class="btn-secondary btn-sm">{{ $s->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
        <form method="POST" action="{{ route('console.webhooks.destroy', $s) }}" class="inline" onsubmit="return confirm('Hapus webhook?')">@csrf @method('DELETE')<button class="btn-danger btn-sm">Hapus</button></form>
      </td></tr>@endforeach
  </tbody></table></div>@endif
</div>

<div class="card overflow-hidden">
  <h2 class="font-semibold text-white px-5 pt-4 pb-3">Pengiriman terakhir</h2>
  @if ($deliveries->isEmpty())<p class="px-5 pb-6 text-sm text-zinc-500">Belum ada pengiriman.</p>@else
  <div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="th">Waktu</th><th class="th">Event</th><th class="th">Status</th><th class="th">HTTP</th><th class="th">Percobaan</th><th class="th"></th></tr></thead><tbody>
    @foreach ($deliveries as $d)<tr class="tr">
      <td class="td text-zinc-500">{{ $d->created_at->diffForHumans() }}</td><td class="td font-mono text-xs">{{ $d->event_type }}</td>
      <td class="td"><x-status-badge :status="$d->status" /></td><td class="td font-mono">{{ $d->http_status ?? '—' }}</td><td class="td font-mono">{{ $d->attempt }}</td>
      <td class="td text-right"><form method="POST" action="{{ route('console.webhooks.replay', $d->id) }}">@csrf<button class="btn-ghost btn-sm">Kirim ulang</button></form></td></tr>@endforeach
  </tbody></table></div>@endif
</div>
@endsection
