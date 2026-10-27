@extends('layouts.app')
@section('title', 'Audit Log')
@section('content')
<x-page-header title="Audit log" subtitle="Aksi sensitif di workspace ini." />
<form method="GET" class="flex gap-2 mb-4"><input name="action" value="{{ request('action') }}" class="input max-w-xs" placeholder="Filter aksi, mis. apikey" aria-label="Filter aksi"><button class="btn-secondary">Filter</button></form>
<div class="card overflow-hidden">
  @if ($logs->isEmpty())<x-empty-state icon="shield" title="Belum ada catatan" />@else
  <div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="th">Waktu</th><th class="th">Pengguna</th><th class="th">Aksi</th><th class="th">Target</th><th class="th">IP</th></tr></thead><tbody>
    @foreach ($logs as $l)<tr class="tr"><td class="td text-zinc-500 whitespace-nowrap">{{ $l->created_at->format('d M H:i') }}</td><td class="td">{{ $l->user?->name ?? 'sistem' }}</td><td class="td font-mono text-xs">{{ $l->action }}</td><td class="td font-mono text-xs truncate max-w-48">{{ $l->target_type }} {{ $l->target_id }}</td><td class="td font-mono text-xs text-zinc-500">{{ $l->ip }}</td></tr>@endforeach
  </tbody></table></div>
  <div class="px-4 py-3 border-t border-line">{{ $logs->links() }}</div>@endif
</div>
@endsection
