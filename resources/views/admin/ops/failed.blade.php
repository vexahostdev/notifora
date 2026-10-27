@extends('layouts.admin')
@section('title', 'Job gagal')
@section('content')
<x-page-header title="Job gagal" subtitle="Antrean yang melempar exception setelah semua percobaan.">
  <a href="{{ route('admin.ops.index') }}" class="btn-secondary">← Operasional</a>
  @if ($jobs->total())
  <form method="POST" action="{{ route('admin.ops.retry-all') }}" class="inline" onsubmit="return confirm('Ulangi semua job gagal?')">@csrf<button class="btn-primary">Ulangi semua</button></form>
  <form method="POST" action="{{ route('admin.ops.flush') }}" class="inline" onsubmit="return confirm('Hapus SEMUA job gagal?')">@csrf<button class="btn-danger">Hapus semua</button></form>
  @endif
</x-page-header>
<div class="card overflow-hidden">
  @if ($jobs->isEmpty())<x-empty-state icon="check" title="Tidak ada job gagal" text="Semua antrean sehat." />@else
  <div class="overflow-x-auto"><table class="w-full">
    <thead><tr><th class="th">Job</th><th class="th">Antrean</th><th class="th">Error</th><th class="th">Gagal pada</th><th class="th"></th></tr></thead>
    <tbody>
    @foreach ($jobs as $j)
      <tr class="tr">
        <td class="td font-mono text-xs text-white">{{ $j->job }}</td><td class="td font-mono text-xs">{{ $j->queue }}</td>
        <td class="td text-xs text-red-300 max-w-md break-words">{{ $j->error }}</td>
        <td class="td text-zinc-500 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($j->failed_at)->diffForHumans() }}</td>
        <td class="td text-right whitespace-nowrap">
          <form method="POST" action="{{ route('admin.ops.retry', $j->uuid) }}" class="inline">@csrf<button class="btn-secondary btn-sm">Ulangi</button></form>
          <form method="POST" action="{{ route('admin.ops.forget', $j->uuid) }}" class="inline" onsubmit="return confirm('Hapus job ini?')">@csrf @method('DELETE')<button class="btn-danger btn-sm">Hapus</button></form>
        </td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  <div class="px-4 py-3 border-t border-line">{{ $jobs->links() }}</div>
  @endif
</div>
@endsection
