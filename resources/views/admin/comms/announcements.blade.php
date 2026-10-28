@extends('layouts.admin')
@section('title', 'Pengumuman')
@section('content')
<x-page-header title="Pengumuman" subtitle="Banner di atas Console semua pelanggan selama masa tayang." />
<div class="grid lg:grid-cols-3 gap-5">
  <div class="lg:col-span-2 space-y-3">
    @forelse ($items as $a)
      <div class="card card-pad">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
          <div class="min-w-0"><p class="font-semibold text-white break-words [overflow-wrap:anywhere]">{{ $a->title }} <span class="text-[10px] font-mono text-zinc-500 uppercase">{{ $a->tone }}</span></p><p class="text-sm text-zinc-400 mt-1 whitespace-pre-line break-words [overflow-wrap:anywhere]">{{ $a->body }}</p>
            <p class="hint mt-2">{{ $a->starts_at?->format('d M Y H:i') ?? 'sekarang' }} → {{ $a->ends_at?->format('d M Y H:i') ?? 'tanpa batas' }}</p></div>
          <div class="flex sm:flex-col gap-1.5 shrink-0 flex-wrap"><x-status-badge :status="$a->active ? 'active' : 'disabled'" />
            <form method="POST" action="{{ route('admin.comms.announcement.toggle', $a) }}">@csrf<button class="btn-secondary btn-sm w-full">{{ $a->active ? 'Matikan' : 'Aktifkan' }}</button></form>
            <form method="POST" action="{{ route('admin.comms.announcement.destroy', $a) }}" onsubmit="return confirm('Hapus pengumuman?')">@csrf @method('DELETE')<button class="btn-danger btn-sm w-full">Hapus</button></form></div>
        </div>
      </div>
    @empty<div class="card"><x-empty-state icon="bell" title="Belum ada pengumuman" text="Buat pengumuman pertama di samping." /></div>@endforelse
  </div>
  <aside>
    <form method="POST" action="{{ route('admin.comms.announcement.store') }}" class="card card-pad space-y-3">
      @csrf
      <h2 class="font-semibold text-white">Pengumuman baru</h2>
      <div><label class="label" for="at">Judul</label><input id="at" name="title" required maxlength="140" class="input" value="{{ old('title') }}">@error('title')<p class="error-text">{{ $message }}</p>@enderror</div>
      <div><label class="label" for="ab">Isi</label><textarea id="ab" name="body" required rows="4" maxlength="2000" class="input">{{ old('body') }}</textarea>@error('body')<p class="error-text">{{ $message }}</p>@enderror</div>
      <div><label class="label" for="ato">Jenis</label><select id="ato" name="tone" class="input">@foreach (['info' => 'Info', 'success' => 'Sukses', 'warn' => 'Peringatan', 'danger' => 'Penting'] as $value => $label)<option value="{{ $value }}" @selected(old('tone', 'info') === $value)>{{ $label }}</option>@endforeach</select></div>
      <div><label class="label" for="as">Mulai (opsional)</label><input id="as" name="starts_at" type="datetime-local" class="input" value="{{ old('starts_at') }}">@error('starts_at')<p class="error-text">{{ $message }}</p>@enderror</div>
      <div><label class="label" for="ae">Selesai (opsional)</label><input id="ae" name="ends_at" type="datetime-local" class="input" value="{{ old('ends_at') }}">@error('ends_at')<p class="error-text">{{ $message }}</p>@enderror</div>
      <button class="btn-primary w-full">Tayangkan</button>
    </form>
  </aside>
</div>
@endsection
