@extends('layouts.app')
@section('title', 'API Keys')
@section('content')
<x-page-header title="API Keys" :subtitle="'Environment ' . $currentEnv->type->value . ' · prefix ' . $currentEnv->type->keyPrefix()" />

@if ($newKey)
  <section class="card card-pad mb-6 border-ok/50" role="alert">
    <h2 class="font-semibold text-emerald-300 mb-1">API key dibuat — salin sekarang</h2>
    <p class="hint mb-3">Key ini <strong>tidak akan ditampilkan lagi</strong>. Simpan di secret manager, jangan di repo.</p>
    <div class="flex flex-wrap items-center gap-2"><code class="code flex-1 min-w-0 break-all select-all">{{ $newKey }}</code><x-copy-button :text="$newKey" /></div>
    <pre class="code mt-3">curl -X POST {{ $apiUrl }}/v1/events/trigger \
  -H "Authorization: Bearer {{ $newKey }}" \
  -H "Content-Type: application/json" \
  -d '{"workflow":"order-confirmed","to":{"subscriberId":"usr_1"}}'</pre>
  </section>
@endif

<form method="POST" action="{{ route('console.api-keys.store') }}" class="card card-pad flex flex-wrap items-end gap-3 mb-6">
  @csrf
  <div class="flex-1 min-w-48"><label class="label" for="kn">Nama key</label><input id="kn" name="name" required maxlength="80" class="input" placeholder="mis. backend-production">@error('name')<p class="error-text">{{ $message }}</p>@enderror</div>
  <button class="btn-primary"><x-icon name="plus" />Buat key</button>
</form>

<div class="card overflow-hidden">
  @if ($keys->isEmpty())
    <x-empty-state icon="key" title="Belum ada API key" text="Buat key untuk memanggil API dari server kamu." />
  @else
  <div class="overflow-x-auto"><table class="w-full">
    <thead><tr><th class="th">Nama</th><th class="th">Key</th><th class="th">Terakhir dipakai</th><th class="th">Dibuat</th><th class="th">Status</th><th class="th"></th></tr></thead>
    <tbody>
    @foreach ($keys as $k)
      <tr class="tr">
        <td class="td text-white">{{ $k->name }}</td>
        <td class="td font-mono text-xs">{{ $k->masked() }}</td>
        <td class="td text-zinc-500">{{ $k->last_used_at?->diffForHumans() ?? 'belum pernah' }}</td>
        <td class="td text-zinc-500">{{ $k->created_at->format('d M Y') }}</td>
        <td class="td">@if ($k->isRevoked())<x-status-badge status="disabled" />@else<x-status-badge status="active" />@endif</td>
        <td class="td text-right">@unless ($k->isRevoked())
          <form method="POST" action="{{ route('console.api-keys.destroy', $k->id) }}" onsubmit="return confirm('Cabut key ini? Aplikasi yang memakainya akan menerima 401.')">@csrf @method('DELETE')<button class="btn-danger btn-sm">Cabut</button></form>
        @endunless</td>
      </tr>
    @endforeach
    </tbody></table></div>
  @endif
</div>
@endsection
