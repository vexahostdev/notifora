@extends('layouts.admin')
@section('title', 'Pengaturan platform')
@section('content')
<x-page-header title="Pengaturan platform" subtitle="Flag operasional yang berlaku langsung tanpa deploy." />
<div class="grid lg:grid-cols-2 gap-5">
  <form method="POST" action="{{ route('admin.settings.update') }}" class="card card-pad space-y-4">
    @csrf @method('PUT')
    <h2 class="font-semibold text-white">Flag</h2>
    @foreach ($flags as $key => $label)
      <label class="flex items-start gap-3"><input type="checkbox" name="{{ $key }}" value="1" @checked(($values[$key] ?? '1') === '1') class="mt-1 accent-[#0033FF]">
        <span class="text-sm text-white">{{ $label }}</span></label>
    @endforeach
    <div class="border-t border-line pt-4">
      <label class="flex items-start gap-3"><input type="checkbox" name="maintenance" value="1" @checked(($values['maintenance'] ?? '0') === '1') class="mt-1 accent-[#0033FF]">
        <span><span class="block text-sm text-white">Mode maintenance</span><span class="hint">Console, API, dan halaman publik menampilkan 503. Operator tetap bisa masuk ke /admin.</span></span></label>
      <label class="label mt-3" for="mm">Pesan maintenance</label>
      <textarea id="mm" name="maintenance_message" rows="3" maxlength="500" class="input">{{ $values['maintenance_message'] ?? '' }}</textarea>
    </div>
    <button class="btn-primary">Simpan pengaturan</button>
  </form>

  <section class="card card-pad">
    <h2 class="font-semibold text-white mb-3">Informasi lingkungan</h2>
    <dl class="space-y-2 text-sm">@foreach ($info as $k => $v)<div><dt class="label !mb-0">{{ $k }}</dt><dd class="font-mono text-xs break-words">{{ $v }}</dd></div>@endforeach</dl>
  </section>
</div>
@endsection
