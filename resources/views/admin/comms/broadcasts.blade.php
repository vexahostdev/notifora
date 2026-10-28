@extends('layouts.admin')
@section('title', 'Broadcast email')
@section('content')
<x-page-header title="Broadcast email" subtitle="Kirim email ke admin/owner workspace. Dikirim lewat antrean dan tercatat di log email." />
<div class="grid lg:grid-cols-3 gap-5">
  <div class="lg:col-span-2 card overflow-hidden">
    <h2 class="font-semibold text-white px-5 pt-4 pb-3">Riwayat</h2>
    <div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="th">Subjek</th><th class="th">Audiens</th><th class="th text-right">Penerima</th><th class="th text-right">Terkirim</th><th class="th">Status</th><th class="th">Oleh</th></tr></thead><tbody>
      @forelse ($items as $b)
        <tr class="tr"><td class="td text-white">{{ $b->subject }}<span class="block text-[10px] text-zinc-500">{{ $b->created_at->diffForHumans() }}</span></td><td class="td font-mono text-xs">{{ $b->audience }}</td><td class="td text-right font-mono">{{ $b->recipients }}</td><td class="td text-right font-mono">{{ $b->delivered }}</td><td class="td font-mono text-xs">{{ $b->status }}</td><td class="td text-zinc-500">{{ $b->creator?->name }}</td></tr>
      @empty<tr><td class="td text-zinc-500" colspan="6">Belum ada broadcast.</td></tr>@endforelse
    </tbody></table></div>
  </div>
  <aside x-data="{
    audience: @js(old('audience', $audience ?? 'all')),
    count: {{ $count }},
    updating: false,
    countError: false,
    countRequest: 0,
    init() { if (this.audience !== @js($audience ?? 'all')) this.updateAudience(); },
    async updateAudience() {
      const request = ++this.countRequest;
      this.updating = true; this.countError = false;
      try {
        const response = await fetch('{{ route('admin.comms.broadcasts') }}?audience=' + encodeURIComponent(this.audience), { headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok) throw new Error('count-unavailable');
        if (request === this.countRequest) this.count = data.count;
      } catch {
        if (request === this.countRequest) this.countError = true;
      } finally {
        if (request === this.countRequest) this.updating = false;
      }
    }
  }">
    <form method="POST" action="{{ route('admin.comms.broadcast.send') }}" class="card card-pad space-y-3">
      @csrf
      <h2 class="font-semibold text-white">Broadcast baru</h2>
      <div><label class="label" for="ba">Audiens</label>
        <select id="ba" name="audience" class="input" x-model="audience" @change="updateAudience()">@foreach ($audiences as $k => $l)<option value="{{ $k }}" @selected(old('audience', $audience ?? 'all') === $k)>{{ $l }}</option>@endforeach</select>
        <p class="hint mt-1"><span x-text="count"></span> penerima <span x-show="updating">· menghitung ulang…</span></p>
        <p class="error-text" x-show="countError" x-cloak role="alert">Jumlah penerima gagal dimuat. Pilih ulang audiens untuk mencoba lagi.</p></div>
      <div><label class="label" for="bs">Subjek</label><input id="bs" name="subject" required maxlength="180" class="input" value="{{ old('subject') }}">@error('subject')<p class="error-text">{{ $message }}</p>@enderror</div>
      <div><label class="label" for="bb">Isi</label><textarea id="bb" name="body" required rows="7" maxlength="5000" class="input">{{ old('body') }}</textarea><p class="hint">Mendukung **tebal** dan tautan https://…</p>@error('body')<p class="error-text">{{ $message }}</p>@enderror</div>
      <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirm" value="1" class="mt-1 accent-[#0033FF]" @checked(old('confirm'))> Saya sudah memeriksa isi dan audiens; email tidak dapat ditarik kembali.</label>@error('confirm')<p class="error-text">{{ $message }}</p>@enderror
      <button class="btn-primary w-full" :disabled="updating || countError || count === 0"><span x-text="updating ? 'Menghitung…' : (countError ? 'Penerima belum tersedia' : (count === 0 ? 'Tidak ada penerima' : 'Kirim ke ' + count + ' penerima'))">Kirim ke {{ $count }} penerima</span></button>
    </form>
  </aside>
</div>
@endsection
