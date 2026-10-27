@extends('layouts.admin')
@section('title', 'Backup database')
@section('content')
<x-page-header title="Backup database" :subtitle="'Driver: '.$driver.' · disimpan '.$keep.' backup terbaru · '.($enabled ? 'terjadwal harian 03:15' : 'jadwal otomatis nonaktif')">
  <form method="POST" action="{{ route('admin.system.backups.run') }}">@csrf<button class="btn-primary">Cadangkan sekarang</button></form>
</x-page-header>

@if ($error)<div class="card card-pad mb-4 border-err/50" role="alert"><p class="text-sm text-red-300"><strong>Backup terakhir gagal:</strong> {{ $error }}</p></div>@endif
<div class="card card-pad mb-4">
  <p class="text-sm text-zinc-300">Backup berhasil terakhir: <strong class="text-white">{{ $last ? \Carbon\Carbon::createFromTimestamp($last)->diffForHumans() : 'belum pernah' }}</strong></p>
  <p class="hint mt-1">Berkas disimpan di <code>storage/app/backups</code> pada server yang sama. <strong>Salin juga ke penyimpanan luar server</strong> (S3/Backblaze/rclone) dan uji restore secara berkala. MySQL memerlukan binary <code>mysqldump</code>.</p>
</div>

<div class="card overflow-hidden">
  @if (! $files)<x-empty-state icon="list" title="Belum ada backup" text="Klik “Cadangkan sekarang” atau tunggu jadwal harian." />@else
  <div class="overflow-x-auto"><table class="w-full">
    <thead><tr><th class="th">Berkas</th><th class="th">Ukuran</th><th class="th">Dibuat</th><th class="th"></th></tr></thead>
    <tbody>
    @foreach ($files as $f)
      <tr class="tr">
        <td class="td font-mono text-xs">{{ $f['name'] }}</td>
        <td class="td">{{ number_format($f['size'] / 1024, 1, ',', '.') }} KB</td>
        <td class="td text-zinc-500">{{ \Carbon\Carbon::createFromTimestamp($f['at'])->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}</td>
        <td class="td text-right whitespace-nowrap">
          <a class="btn-secondary btn-sm" href="{{ route('admin.system.backups.download', $f['name']) }}">Unduh</a>
          <form method="POST" action="{{ route('admin.system.backups.destroy', $f['name']) }}" class="inline" onsubmit="return confirm('Hapus backup ini?')">@csrf @method('DELETE')<button class="btn-danger btn-sm">Hapus</button></form>
        </td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  @endif
</div>
@endsection
