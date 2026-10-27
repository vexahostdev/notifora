@extends('layouts.app')
@section('title', $subscriber->external_id)
@section('content')
@php
  $canEdit = $currentRole->atLeast(\App\Enums\MemberRole::DEVELOPER);
  $canReveal = $currentRole->atLeast(\App\Enums\MemberRole::ADMIN);
  $val = fn ($v) => $v ? ($reveal ? $v : \App\Support\Phone::mask($v)) : '—';
  $prefValue = function ($wf, $ch) use ($prefs) {
      $p = $prefs->first(fn ($x) => $x->workflow_id === $wf && $x->channel === $ch->value);
      return $p ? ($p->enabled ? 'on' : 'off') : 'default';
  };
@endphp
<x-page-header :title="$subscriber->external_id" :subtitle="trim($subscriber->first_name.' '.$subscriber->last_name) ?: null">
  <a href="{{ route('console.subscribers.index') }}" class="btn-secondary">← Daftar</a>
  @if ($canReveal && ! $reveal)<a href="{{ request()->fullUrlWithQuery(['reveal' => 1]) }}" class="btn-secondary">Tampilkan data asli</a>@endif
</x-page-header>

<div class="grid lg:grid-cols-3 gap-5">
  <section class="card card-pad lg:col-span-1">
    <h2 class="font-semibold text-white mb-3">Profil</h2>
    <dl class="space-y-2 text-sm">
      <div><dt class="label !mb-0">Email</dt><dd class="font-mono text-xs break-all">{{ $val($subscriber->email) }}</dd></div>
      <div><dt class="label !mb-0">Telepon (WhatsApp)</dt><dd class="font-mono text-xs">{{ $val($subscriber->phone) }}</dd></div>
      <div><dt class="label !mb-0">Telegram chat ID</dt><dd class="font-mono text-xs">{{ $val($subscriber->telegram_chat_id) }}</dd></div>
      <div><dt class="label !mb-0">Locale / zona waktu</dt><dd class="font-mono text-xs">{{ $subscriber->locale }} · {{ $subscriber->timezone }}</dd></div>
      @if ($subscriber->data)<div><dt class="label !mb-0">Data kustom</dt><dd><pre class="code">{{ json_encode($subscriber->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></dd></div>@endif
    </dl>
    @if ($reveal)<p class="hint mt-3">Akses data asli dicatat di audit log.</p>@endif
  </section>

  <section class="card card-pad lg:col-span-2">
    <h2 class="font-semibold text-white mb-1">Preferensi notifikasi</h2>
    <p class="hint mb-3">“Default” = ikuti pengaturan global. Opt-out global selalu berlaku, termasuk untuk workflow kritis.</p>
    <form method="POST" action="{{ route('console.subscribers.preferences', $subscriber) }}">
      @csrf
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead><tr><th class="th">Scope</th>@foreach ($channels as $ch)<th class="th">{{ $ch->label() }}</th>@endforeach</tr></thead>
          <tbody>
          @foreach (array_merge([['*', 'Global (semua workflow)']], $workflows->map(fn ($w) => [$w->id, $w->name])->all()) as [$wid, $wname])
            <tr class="tr"><td class="td text-white">{{ $wname }}</td>
              @foreach ($channels as $ch)
                <td class="td">
                  <select name="pref[{{ $wid }}|{{ $ch->value }}]" class="input !py-1 !text-xs w-24" aria-label="{{ $wname }} {{ $ch->label() }}" @disabled(! $canEdit)>
                    @php $cur = $prefValue($wid === '*' ? null : $wid, $ch); @endphp
                    <option value="default" @selected($cur === 'default')>Default</option><option value="on" @selected($cur === 'on')>On</option><option value="off" @selected($cur === 'off')>Off</option>
                  </select>
                </td>
              @endforeach
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      @if ($canEdit)<div class="mt-4"><button class="btn-primary">Simpan preferensi</button></div>@endif
    </form>
  </section>
</div>

<section class="card mt-5 overflow-hidden">
  <h2 class="font-semibold text-white px-5 pt-4 pb-3">20 pesan terakhir</h2>
  @if ($logs->isEmpty())<p class="px-5 pb-6 text-sm text-zinc-500">Belum ada pesan.</p>@else
  <div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="th">Waktu</th><th class="th">Channel</th><th class="th">Status</th><th class="th"></th></tr></thead><tbody>
    @foreach ($logs as $l)<tr class="tr"><td class="td text-zinc-400">{{ $l->created_at->diffForHumans() }}</td><td class="td"><x-channel-icon :channel="$l->channel_type" /></td><td class="td"><x-status-badge :status="$l->status" /></td><td class="td text-right"><a class="text-cobalt hover:underline text-xs" href="{{ route('console.logs.show', $l->id) }}">Detail</a></td></tr>@endforeach
  </tbody></table></div>@endif
</section>

@if ($canEdit)
<section class="card card-pad mt-5 border-err/30">
  <h2 class="font-semibold text-red-300 mb-1">Hapus subscriber (erasure)</h2>
  <p class="hint mb-3">Menghapus profil, preferensi, inbox, dan event terkait secara permanen. Tidak dapat dibatalkan.</p>
  <form method="POST" action="{{ route('console.subscribers.destroy', $subscriber) }}" class="flex flex-wrap gap-2" onsubmit="return confirm('Hapus permanen?')">
    @csrf @method('DELETE')
    <input name="confirm" class="input input-mono max-w-xs" placeholder="Ketik: {{ $subscriber->external_id }}" aria-label="Konfirmasi subscriber ID" autocomplete="off">
    <button class="btn-danger">Hapus permanen</button>
  </form>
</section>
@endif
@endsection
