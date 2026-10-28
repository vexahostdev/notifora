@extends('layouts.admin')
@section('title', $ws->name)
@section('content')
@php $rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'); $cfg = $ws->planConfig(); @endphp
<x-page-header :title="$ws->name" :subtitle="$ws->slug.' · '.$ws->id">
  <a href="{{ route('admin.tenants.index') }}" class="btn-secondary">← Tenants</a>
  <a href="{{ route('admin.tenants.export', $ws) }}" class="btn-secondary">Ekspor data (JSON)</a>
</x-page-header>

@if ($ws->isSuspended())
  <div class="mb-5 rounded-md border border-err/40 bg-err/10 px-4 py-3 text-sm text-red-200" role="alert">
    <strong>Ditangguhkan</strong> sejak {{ $ws->suspended_at->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i') }} WIB — {{ $ws->suspended_reason }}
  </div>
@endif

<div class="grid lg:grid-cols-3 gap-5">
  <div class="lg:col-span-2 space-y-5">
    {{-- Ringkasan --}}
    <section class="card card-pad">
      <div class="grid sm:grid-cols-3 gap-4">
        <div><p class="label">Plan efektif</p><p class="text-lg font-semibold text-white">{{ $cfg['label'] }}</p><p class="hint">tersimpan: {{ $ws->plan }}{{ $ws->inGrace() ? ' · masa tenggang' : '' }}</p></div>
        <div><p class="label">Berlaku sampai</p><p class="font-mono text-sm">{{ $ws->plan_expires_at?->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i') ?? '—' }}</p></div>
        <div><p class="label">Dibuat</p><p class="font-mono text-sm">{{ $ws->created_at->translatedFormat('d M Y') }}</p></div>
        <div><p class="label">Event bulan ini</p><p class="font-mono text-sm">{{ number_format($stats['events_month']) }} / {{ number_format($cfg['events']) }}</p></div>
        <div><p class="label">Gagal kirim bulan ini</p><p class="font-mono text-sm {{ $stats['failed_month'] ? 'text-err' : '' }}">{{ number_format($stats['failed_month']) }}</p></div>
        <div><p class="label">Rate limit / retensi</p><p class="font-mono text-sm">{{ number_format($cfg['rpm']) }} rpm · {{ $cfg['retention_days'] }} hari</p></div>
        <div><p class="label">Workflow</p><p class="font-mono text-sm">{{ $stats['workflows'] }}</p></div>
        <div><p class="label">Subscriber</p><p class="font-mono text-sm">{{ number_format($stats['subscribers']) }}</p></div>
        <div><p class="label">Webhook / total event</p><p class="font-mono text-sm">{{ $stats['webhooks'] }} · {{ number_format($stats['events_total']) }}</p></div>
      </div>
    </section>

    {{-- Anggota --}}
    <section class="card overflow-hidden">
      <h2 class="font-semibold text-white px-5 pt-4 pb-3">Anggota ({{ $members->count() }})</h2>
      <div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="th">Nama</th><th class="th">Email</th><th class="th">Peran</th><th class="th">2FA</th><th class="th"></th></tr></thead><tbody>
        @foreach ($members as $m)
          <tr class="tr">
            <td class="td"><a href="{{ route('admin.users.show', $m->user) }}" class="text-white hover:text-link">{{ $m->user->name }}</a>@if ($m->user->isSuspended())<span class="ml-1 text-[10px] font-mono text-err">SUSPEND</span>@endif</td>
            <td class="td font-mono text-xs">{{ $m->user->email }}</td>
            <td class="td"><span class="font-mono text-xs">{{ $m->role->value }}</span></td>
            <td class="td">{{ $m->user->hasTwoFactor() ? '✓' : '—' }}</td>
            <td class="td text-right whitespace-nowrap">
              @unless ($m->user->isPlatformAdmin())
              <form method="POST" action="{{ route('admin.tenants.impersonate', [$ws, $m->user]) }}" class="inline" onsubmit="return confirm(@js('Masuk sebagai '.$m->user->email.'? Aksi ini dicatat di audit.'))">@csrf<button class="btn-secondary btn-sm">Impersonasi</button></form>
              @endunless
              @if ($m->role !== \App\Enums\MemberRole::OWNER)
              <form method="POST" action="{{ route('admin.tenants.owner', [$ws, $m]) }}" class="inline" onsubmit="return confirm('Jadikan Owner? Owner lama diturunkan ke Admin.')">@csrf<button class="btn-ghost btn-sm">Jadikan owner</button></form>
              @endif
              @if ($m->role !== \App\Enums\MemberRole::OWNER)
                <form method="POST" action="{{ route('admin.tenants.member.remove', [$ws, $m]) }}" class="inline" onsubmit="return confirm('Hapus anggota ini dari workspace?')">@csrf @method('DELETE')<button class="btn-danger btn-sm">Hapus</button></form>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody></table></div>
    </section>

    {{-- API key & provider --}}
    <section class="card overflow-hidden">
      <div class="flex items-center justify-between px-5 pt-4 pb-3"><h2 class="font-semibold text-white">API key & provider</h2>
        <form method="POST" action="{{ route('admin.tenants.revoke-keys', $ws) }}" onsubmit="return confirm('Cabut SEMUA API key aktif workspace ini?')">@csrf<button class="btn-danger btn-sm">Cabut semua key</button></form></div>
      <div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="th">Nama</th><th class="th">Key</th><th class="th">Env</th><th class="th">Terakhir dipakai</th><th class="th">Status</th></tr></thead><tbody>
        @forelse ($keys as $k)<tr class="tr"><td class="td">{{ $k->name }}</td><td class="td font-mono text-xs">{{ $k->masked() }}</td><td class="td font-mono text-xs">{{ $k->environment->type->value }}</td><td class="td text-zinc-500">{{ $k->last_used_at?->diffForHumans() ?? '—' }}</td><td class="td"><x-status-badge :status="$k->isRevoked() ? 'disabled' : 'active'" /></td></tr>
        @empty<tr><td class="td text-zinc-500" colspan="5">Belum ada key.</td></tr>@endforelse
      </tbody></table></div>
      <div class="border-t border-line px-5 py-3 flex flex-wrap gap-2">
        @forelse ($providers as $p)<span class="inline-flex items-center gap-2 text-xs"><x-channel-icon :channel="$p->channel" /><x-status-badge :status="$p->status" /></span>@empty<span class="text-xs text-zinc-500">Belum ada provider.</span>@endforelse
      </div>
    </section>

    {{-- Pembayaran --}}
    <section class="card overflow-hidden">
      <h2 class="font-semibold text-white px-5 pt-4 pb-3">Pembayaran terakhir</h2>
      <div class="overflow-x-auto"><table class="w-full"><tbody>
        @forelse ($payments as $p)<tr class="tr"><td class="td"><a href="{{ route('admin.payments.show', $p->id) }}" class="font-mono text-xs text-white hover:text-link">{{ $p->number }}</a></td><td class="td">{{ $p->planLabel() }} · {{ $p->months }} bln</td><td class="td text-right font-mono">{{ $rp($p->amount) }}</td><td class="td"><x-status-badge :status="$p->status" /></td><td class="td text-zinc-500">{{ $p->created_at->diffForHumans() }}</td></tr>
        @empty<tr><td class="td text-zinc-500">Belum ada pembayaran.</td></tr>@endforelse
      </tbody></table></div>
    </section>

    {{-- Audit --}}
    <div class="grid md:grid-cols-2 gap-5">
      <section class="card card-pad"><h2 class="font-semibold text-white mb-2">Audit operator</h2>
        <ul class="space-y-1.5 text-xs">@forelse ($adminAudit as $l)<li><span class="font-mono text-amber-300">{{ $l->action }}</span> <span class="text-zinc-500">· {{ $l->admin?->name ?? '—' }} · {{ $l->created_at->diffForHumans() }}</span></li>@empty<li class="text-zinc-500">Belum ada.</li>@endforelse</ul></section>
      <section class="card card-pad"><h2 class="font-semibold text-white mb-2">Audit workspace</h2>
        <ul class="space-y-1.5 text-xs">@forelse ($tenantAudit as $l)<li><span class="font-mono text-zinc-300">{{ $l->action }}</span> <span class="text-zinc-500">· {{ $l->user?->name ?? 'sistem' }} · {{ $l->created_at->diffForHumans() }}</span></li>@empty<li class="text-zinc-500">Belum ada.</li>@endforelse</ul></section>
    </div>
  </div>

  {{-- Kolom aksi --}}
  <aside class="space-y-5">
    <section class="card card-pad">
      <h2 class="font-semibold text-white mb-3">Plan (grant manual)</h2>
      <form method="POST" action="{{ route('admin.tenants.plan', $ws) }}" class="space-y-3">
        @csrf @method('PUT')
        <div><label class="label" for="plan">Plan</label>
          <select id="plan" name="plan" class="input"><option value="free" @selected($ws->plan === 'free')>Free</option>@foreach ($plans as $k => $p)<option value="{{ $k }}" @selected($ws->plan === $k)>{{ $p['label'] }}{{ ($p['active'] ?? true) ? '' : ' (nonaktif)' }}</option>@endforeach</select></div>
        <div><label class="label" for="exp">Berlaku sampai (kosong = tanpa batas)</label><input id="exp" type="datetime-local" name="expires_at" class="input" value="{{ $ws->plan_expires_at?->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i') }}"></div>
        <div><label class="label" for="pn">Catatan (wajib, masuk audit)</label><input id="pn" name="note" required maxlength="255" class="input" placeholder="mis. kompensasi downtime / paket kemitraan"></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="notify" value="1" class="accent-[#0033FF]"> Kirim email ke admin workspace</label>
        <button class="btn-primary w-full">Terapkan plan</button>
      </form>
    </section>

    <section class="card card-pad">
      <h2 class="font-semibold text-white mb-1">Batas kustom</h2>
      <p class="hint mb-3">Menimpa nilai plan. Kosongkan semua untuk mengikuti plan.</p>
      <form method="POST" action="{{ route('admin.tenants.limits', $ws) }}" class="space-y-3">
        @csrf @method('PUT')
        <div><label class="label" for="le">Event / bulan</label><input id="le" name="events" type="number" min="1" class="input" value="{{ $ws->custom_limits['events'] ?? '' }}" placeholder="{{ number_format($cfg['events']) }}"></div>
        <div><label class="label" for="lr">Rate limit (req/menit)</label><input id="lr" name="rpm" type="number" min="1" class="input" value="{{ $ws->custom_limits['rpm'] ?? '' }}"></div>
        <div><label class="label" for="ld">Retensi log (hari)</label><input id="ld" name="retention_days" type="number" min="1" class="input" value="{{ $ws->custom_limits['retention_days'] ?? '' }}"></div>
        <button class="btn-secondary w-full">Simpan batas</button>
      </form>
    </section>

    <section class="card card-pad">
      <h2 class="font-semibold text-white mb-3">{{ $ws->isSuspended() ? 'Aktifkan kembali' : 'Tangguhkan workspace' }}</h2>
      @if ($ws->isSuspended())
        <form method="POST" action="{{ route('admin.tenants.unsuspend', $ws) }}">@csrf<button class="btn-primary w-full">Cabut penangguhan</button></form>
      @else
        <form method="POST" action="{{ route('admin.tenants.suspend', $ws) }}" class="space-y-3" onsubmit="return confirm('Tangguhkan workspace ini? API key ditolak dan Console dikunci.')">
          @csrf
          <div><label class="label" for="sr">Alasan</label><input id="sr" name="reason" required maxlength="255" list="reasons" class="input"><datalist id="reasons">@foreach ($reasons as $r)<option value="{{ $r }}">@endforeach</datalist></div>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="notify" value="1" checked class="accent-[#0033FF]"> Beri tahu admin workspace via email</label>
          <button class="btn-danger w-full">Tangguhkan</button>
        </form>
      @endif
    </section>

    <section class="card card-pad">
      <h2 class="font-semibold text-white mb-2">Catatan internal</h2>
      <form method="POST" action="{{ route('admin.tenants.notes', $ws) }}" class="space-y-2">@csrf @method('PUT')
        <textarea name="notes" rows="4" class="input" aria-label="Catatan internal">{{ $ws->admin_notes }}</textarea>
        <p class="hint">Tidak terlihat pelanggan.</p><button class="btn-secondary btn-sm">Simpan</button></form>
    </section>

    <section class="card card-pad border-err/30">
      <h2 class="font-semibold text-red-300 mb-1">Hapus permanen</h2>
      <p class="hint mb-3">Menghapus workspace beserta SEMUA datanya (event, log, subscriber, provider). Tidak dapat dibatalkan. Ekspor dulu bila perlu.</p>
      <form method="POST" action="{{ route('admin.tenants.destroy', $ws) }}" class="space-y-2" onsubmit="return confirm('Hapus permanen workspace ini?')">@csrf @method('DELETE')
        <input name="confirm" class="input input-mono" placeholder="Ketik: {{ $ws->slug }}" autocomplete="off" aria-label="Konfirmasi slug">@error('confirm')<p class="error-text">{{ $message }}</p>@enderror
        <button class="btn-danger w-full">Hapus workspace</button></form>
    </section>
  </aside>
</div>
@endsection
