@extends('layouts.admin')
@section('title', 'Abuse & blokir')
@section('content')
<x-page-header title="Abuse & blokir" subtitle="Blokir IP dan domain email penyalahguna. Berlaku untuk web dan API." />

<div class="grid lg:grid-cols-3 gap-5">
  <div class="lg:col-span-2 space-y-5">
    <section class="card overflow-hidden">
      <h2 class="font-semibold text-white px-5 pt-4 pb-3">Daftar blokir ({{ $blocks->count() }})</h2>
      <div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="th">Tipe</th><th class="th">Nilai</th><th class="th">Alasan</th><th class="th">Berakhir</th><th class="th"></th></tr></thead><tbody>
        @forelse ($blocks as $b)
          <tr class="tr"><td class="td font-mono text-xs">{{ $b->type }}</td><td class="td font-mono text-white">{{ $b->value }}</td><td class="td text-xs text-zinc-400">{{ $b->reason }}<span class="block text-[10px] text-zinc-500">oleh {{ $b->creator?->name ?? '—' }} · {{ $b->created_at->diffForHumans() }}</span></td><td class="td text-zinc-500 whitespace-nowrap">{{ $b->expires_at?->format('d M Y H:i') ?? 'permanen' }}</td>
          <td class="td text-right"><form method="POST" action="{{ route('admin.abuse.destroy', $b) }}" onsubmit="return confirm('Cabut blokir?')">@csrf @method('DELETE')<button class="btn-secondary btn-sm">Cabut</button></form></td></tr>
        @empty<tr><td class="td text-zinc-500" colspan="5">Belum ada blokir.</td></tr>@endforelse
      </tbody></table></div>
    </section>

    <section class="card overflow-hidden">
      <h2 class="font-semibold text-white px-5 pt-4 pb-1">Pantauan: banyak pesan gagal (7 hari)</h2>
      <p class="hint px-5 pb-3">Kandidat spam atau konfigurasi rusak.</p>
      <div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="th">Workspace</th><th class="th">Plan</th><th class="th text-right">Event</th><th class="th text-right">Terkirim</th><th class="th text-right">Gagal</th></tr></thead><tbody>
        @forelse ($watch as $w)
          <tr class="tr"><td class="td"><a href="{{ route('admin.tenants.show', $w->id) }}" class="text-white hover:text-link">{{ $w->name }}</a>@if ($w->suspended_at)<span class="ml-1 text-[10px] font-mono text-err">SUSPEND</span>@endif</td><td class="td font-mono text-xs">{{ $w->plan }}</td><td class="td text-right font-mono">{{ number_format($w->events) }}</td><td class="td text-right font-mono">{{ number_format($w->sent) }}</td><td class="td text-right font-mono text-err">{{ number_format($w->failed) }}</td></tr>
        @empty<tr><td class="td text-zinc-500" colspan="5">Tidak ada yang mencurigakan.</td></tr>@endforelse
      </tbody></table></div>
    </section>

    <section class="card overflow-hidden">
      <h2 class="font-semibold text-white px-5 pt-4 pb-3">Workspace ditangguhkan ({{ $suspended->count() }})</h2>
      <div class="overflow-x-auto"><table class="w-full"><tbody>
        @forelse ($suspended as $w)<tr class="tr"><td class="td"><a href="{{ route('admin.tenants.show', $w) }}" class="text-white hover:text-link">{{ $w->name }}</a></td><td class="td text-xs text-zinc-400">{{ $w->suspended_reason }}</td><td class="td text-zinc-500 whitespace-nowrap">{{ $w->suspended_at->diffForHumans() }}</td></tr>
        @empty<tr><td class="td text-zinc-500">Tidak ada.</td></tr>@endforelse
      </tbody></table></div>
    </section>
  </div>

  <aside>
    <form method="POST" action="{{ route('admin.abuse.store') }}" class="card card-pad space-y-3">
      @csrf
      <h2 class="font-semibold text-white">Tambah blokir</h2>
      <div><label class="label" for="bt">Tipe</label><select id="bt" name="type" class="input"><option value="ip">Alamat IP</option><option value="email_domain">Domain email (saat daftar)</option></select></div>
      <div><label class="label" for="bv">Nilai</label><input id="bv" name="value" required maxlength="191" class="input input-mono" value="{{ old('value') }}" placeholder="203.0.113.7 / contoh.com">@error('value')<p class="error-text">{{ $message }}</p>@enderror</div>
      <div><label class="label" for="br">Alasan</label><input id="br" name="reason" maxlength="255" class="input"></div>
      <div><label class="label" for="be">Berakhir (opsional)</label><input id="be" name="expires_at" type="datetime-local" class="input"></div>
      <button class="btn-danger w-full">Blokir</button>
    </form>
  </aside>
</div>
@endsection
