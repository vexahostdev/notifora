@extends('layouts.admin')
@section('title', 'Template email')
@section('content')
<x-page-header title="Template email sistem" subtitle="Atur awalan subjek, catatan tambahan, dan aktif/nonaktif per jenis email.">
  <a href="{{ route('admin.emails.logs') }}" class="btn-secondary">← Log email</a>
</x-page-header>
<div class="card overflow-hidden"><div class="divide-y divide-line">
@foreach ($kinds as $kind => $label)
  @php $t = $templates[$kind] ?? null; $locked = in_array($kind, config('admin.email_locked'), true); @endphp
  <form method="POST" action="{{ route('admin.emails.template-update', $kind) }}" class="p-4 grid lg:grid-cols-12 gap-3 items-end">
    @csrf @method('PUT')
    <div class="lg:col-span-3"><p class="font-medium text-white">{{ $label }}</p><p class="text-xs font-mono text-zinc-500">{{ $kind }}</p></div>
    <div class="lg:col-span-2"><label class="label" for="p-{{ $kind }}">Awalan subjek</label><input id="p-{{ $kind }}" name="subject_prefix" maxlength="60" class="input" value="{{ $t?->subject_prefix }}" placeholder="[Notifora]"></div>
    <div class="lg:col-span-4"><label class="label" for="n-{{ $kind }}">Catatan tambahan</label><input id="n-{{ $kind }}" name="extra_note" maxlength="1000" class="input" value="{{ $t?->extra_note }}" placeholder="Ditambahkan di bawah isi email"></div>
    <div class="lg:col-span-2">
      @if ($locked)<p class="text-xs text-zinc-500">Wajib aktif (keamanan)</p>
      @else<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="enabled" value="1" @checked($t?->enabled ?? true) class="accent-[#0033FF]"> Aktif</label>@endif
    </div>
    <div class="lg:col-span-1 text-right"><button class="btn-secondary btn-sm">Simpan</button></div>
  </form>
@endforeach
</div></div>
@endsection
