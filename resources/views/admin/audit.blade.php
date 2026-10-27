@extends('layouts.admin')
@section('title', 'Audit')
@section('content')
<x-page-header title="Audit" subtitle="Jejak aksi operator dan aktivitas semua tenant." />
<div class="flex flex-wrap items-center gap-3 mb-4">
  <div class="inline-flex rounded-md border border-line overflow-hidden text-sm">
    <a href="{{ route('admin.audit') }}" class="px-3 py-1.5 {{ $scope === 'platform' ? 'bg-cobalt text-white' : 'text-zinc-400 hover:text-white' }}">Operator</a>
    <a href="{{ route('admin.audit', ['scope' => 'tenants']) }}" class="px-3 py-1.5 {{ $scope === 'tenants' ? 'bg-cobalt text-white' : 'text-zinc-400 hover:text-white' }}">Tenant</a>
  </div>
  <form method="GET" class="flex gap-2"><input type="hidden" name="scope" value="{{ $scope }}"><input name="action" value="{{ request('action') }}" placeholder="Awalan aksi, mis. tenant." class="input input-mono max-w-xs" aria-label="Filter aksi"><button class="btn-secondary">Filter</button></form>
</div>
<div class="card overflow-hidden">
  @if ($logs->isEmpty())<x-empty-state icon="shield" title="Belum ada catatan" text="Aksi akan tercatat di sini." />@else
  <div class="overflow-x-auto"><table class="w-full">
    <thead><tr><th class="th">Waktu</th><th class="th">Pelaku</th><th class="th">Aksi</th><th class="th">Workspace</th><th class="th">Detail</th></tr></thead>
    <tbody>
    @foreach ($logs as $l)
      @php
        $actor = $scope === 'platform' ? ($l->admin?->name ?? '—') : ($l->user?->name ?? (str_starts_with($l->action, 'admin.') ? 'Notifora Support' : 'sistem'));
        $meta = $l->meta ?? [];
      @endphp
      <tr class="tr">
        <td class="td whitespace-nowrap text-zinc-500">{{ $l->created_at->setTimezone('Asia/Jakarta')->format('d M Y H:i:s') }}@if ($scope === 'platform' && $l->ip)<span class="block text-[10px] font-mono">{{ $l->ip }}</span>@endif</td>
        <td class="td">{{ $actor }}</td>
        <td class="td font-mono text-xs {{ $scope === 'platform' ? 'text-amber-300' : 'text-white' }}">{{ $l->action }}@if ($scope === 'platform' && $l->target_type)<span class="block text-[10px] text-zinc-500">{{ $l->target_type }} {{ \Illuminate\Support\Str::limit((string) $l->target_id, 24) }}</span>@endif</td>
        <td class="td">@if ($l->workspace)<a href="{{ route('admin.tenants.show', $l->workspace) }}" class="hover:text-link">{{ $l->workspace->name }}</a>@else<span class="text-zinc-500">—</span>@endif</td>
        <td class="td font-mono text-[11px] text-zinc-400 max-w-sm break-words">{{ $meta ? \Illuminate\Support\Str::limit(json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 160) : '' }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  <div class="px-4 py-3 border-t border-line">{{ $logs->links() }}</div>
  @endif
</div>
@endsection
