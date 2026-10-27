@extends('layouts.app')
@section('title', $channel->label())
@section('content')
<x-page-header :title="'Provider ' . $channel->label()" subtitle="Kredensial dienkripsi dan tidak pernah ditampilkan ulang.">
  <a href="{{ route('console.channels.index') }}" class="btn-secondary">← Channels</a>
</x-page-header>

<div class="grid lg:grid-cols-3 gap-5">
  <form method="POST" action="{{ route('console.channels.update', $channel->value) }}" class="card card-pad space-y-4 lg:col-span-2">
    @csrf @method('PUT')
    <div class="grid sm:grid-cols-2 gap-4">
      <div><label class="label" for="name">Nama</label><input id="name" name="name" required class="input" value="{{ old('name', $provider->name ?? \App\Models\ChannelProvider::providerFor($channel)) }}">@error('name')<p class="error-text">{{ $message }}</p>@enderror</div>
      <div><label class="label" for="status">Status</label>
        <select id="status" name="status" class="input"><option value="active" @selected(($provider->status ?? 'active') !== 'disabled')>Active</option><option value="disabled" @selected(($provider->status ?? '') === 'disabled')>Disabled</option></select></div>
    </div>
    @foreach ($fields as $key => $label)
      @php $masked = $provider?->maskedCredentials()[$key] ?? null; $secret = preg_match('/key|token|secret|password/i', $key); @endphp
      <div>
        <label class="label" for="{{ $key }}">{{ $label }}</label>
        <input id="{{ $key }}" name="{{ $key }}" class="input input-mono" autocomplete="off"
               type="{{ $secret ? 'password' : 'text' }}"
               value="{{ $secret ? '' : old($key, $masked) }}"
               placeholder="{{ $secret && $masked ? $masked.' (kosongkan = tidak diubah)' : '' }}" @if (! $provider && $key !== 'from_name') required @endif>
        @error($key)<p class="error-text">{{ $message }}</p>@enderror
      </div>
    @endforeach
    <div class="flex gap-2"><button class="btn-primary">Simpan</button></div>
  </form>

  <aside class="space-y-5">
    @if ($provider)
    <section class="card card-pad">
      <h2 class="font-semibold text-white mb-2">Test connection</h2>
      <form method="POST" action="{{ route('console.channels.test', $channel->value) }}" class="space-y-3">
        @csrf
        <div><label class="label" for="to">{{ match($channel) { \App\Enums\ChannelType::WHATSAPP => 'Nomor tujuan', \App\Enums\ChannelType::EMAIL => 'Email tujuan', default => 'Chat ID tujuan' } }}</label>
          <input id="to" name="to" required class="input input-mono"></div>
        <button class="btn-secondary">Kirim pesan uji</button>
        <p class="hint">Pesan benar-benar dikirim lewat provider.</p>
      </form>
    </section>

    <section class="card card-pad">
      <h2 class="font-semibold text-white mb-2">Webhook status delivery</h2>
      <p class="hint mb-2">Daftarkan URL ini di dashboard provider agar status Delivered/Read tercatat.</p>
      <code class="code block break-all">{{ $webhookUrl }}</code>
      <div class="mt-2"><x-copy-button :text="$webhookUrl" /></div>
    </section>

    <form method="POST" action="{{ route('console.channels.destroy', $channel->value) }}" onsubmit="return confirm('Hapus provider ini? Step yang memakainya akan gagal.')">@csrf @method('DELETE')<button class="btn-danger">Hapus provider</button></form>
    @endif
  </aside>
</div>
@endsection
