@extends('layouts.admin')
@section('title', 'Event pembayaran')
@section('content')
<x-page-header title="Event webhook pembayaran" subtitle="Setiap callback Xendit yang diterima (idempoten).">
  <a href="{{ route('admin.payments.index') }}" class="btn-secondary">← Pembayaran</a>
</x-page-header>
<div class="card overflow-hidden">
  @if ($events->isEmpty())<x-empty-state icon="card" title="Belum ada event" text="Callback Xendit akan tercatat di sini." />@else
  <div class="overflow-x-auto"><table class="w-full">
    <thead><tr><th class="th">Waktu</th><th class="th">Event</th><th class="th">Invoice</th><th class="th">Hasil</th><th class="th">IP</th><th class="th">Keterangan</th></tr></thead>
    <tbody>
    @foreach ($events as $e)
      <tr class="tr">
        <td class="td whitespace-nowrap text-zinc-500">{{ $e->created_at->setTimezone('Asia/Jakarta')->format('d M H:i:s') }}</td>
        <td class="td font-mono text-xs">{{ $e->event_type }}<span class="block text-[10px] text-zinc-500">{{ \Illuminate\Support\Str::limit($e->event_id, 28) }}</span></td>
        <td class="td">@if ($e->payment_id)<a href="{{ route('admin.payments.show', $e->payment_id) }}" class="text-link text-xs">lihat</a>@else<span class="text-zinc-500">—</span>@endif</td>
        <td class="td font-mono text-xs {{ $e->processing_status === 'failed' ? 'text-err' : '' }}">{{ $e->processing_status }}</td>
        <td class="td font-mono text-xs text-zinc-500">{{ $e->ip }}</td>
        <td class="td text-xs text-zinc-400">{{ $e->processing_error }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  <div class="px-4 py-3 border-t border-line">{{ $events->links() }}</div>
  @endif
</div>
@endsection
