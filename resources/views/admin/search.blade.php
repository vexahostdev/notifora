@extends('layouts.admin')
@section('title', 'Pencarian')
@section('content')
@php $rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'); @endphp
@php $sub = $q === '' ? 'Cari user, workspace, invoice, pesan, atau email.' : $total.' hasil untuk "'.$q.'"'; @endphp
<x-page-header title="Pencarian" :subtitle="$sub" />
<form method="GET" action="{{ route('admin.search') }}" class="mb-6 flex gap-2"><input name="q" value="{{ $q }}" class="input max-w-md" placeholder="Email, nama, slug, no. invoice, ID pesan…" aria-label="Cari" autofocus><button class="btn-primary">Cari</button></form>

@if ($q !== '' && mb_strlen($q) < 2)<p class="text-sm text-zinc-400">Ketik minimal 2 karakter.</p>@endif
@if (mb_strlen($q) >= 2 && $total === 0)<div class="card"><x-empty-state icon="filter" title="Tidak ditemukan" text="Coba kata kunci lain." /></div>@endif

<div class="space-y-5">
  @if ($r['workspaces']->isNotEmpty())<section class="card overflow-hidden"><h2 class="font-semibold text-white px-5 pt-4 pb-3">Workspace</h2><div class="overflow-x-auto"><table class="w-full"><tbody>
    @foreach ($r['workspaces'] as $w)<tr class="tr"><td class="td"><a href="{{ route('admin.tenants.show', $w) }}" class="text-white hover:text-link">{{ $w->name }}</a><span class="block text-xs font-mono text-zinc-500">{{ $w->slug }}</span></td><td class="td text-zinc-400">{{ $w->owner?->email }}</td><td class="td font-mono text-xs">{{ $w->plan }}</td></tr>@endforeach
  </tbody></table></div></section>@endif

  @if ($r['users']->isNotEmpty())<section class="card overflow-hidden"><h2 class="font-semibold text-white px-5 pt-4 pb-3">Pengguna</h2><div class="overflow-x-auto"><table class="w-full"><tbody>
    @foreach ($r['users'] as $u)<tr class="tr"><td class="td"><a href="{{ route('admin.users.show', $u) }}" class="text-white hover:text-link">{{ $u->name }}</a></td><td class="td font-mono text-xs">{{ $u->email }}</td><td class="td text-zinc-500">{{ $u->created_at->format('d M Y') }}</td></tr>@endforeach
  </tbody></table></div></section>@endif

  @if ($r['payments']->isNotEmpty())<section class="card overflow-hidden"><h2 class="font-semibold text-white px-5 pt-4 pb-3">Pembayaran</h2><div class="overflow-x-auto"><table class="w-full"><tbody>
    @foreach ($r['payments'] as $p)<tr class="tr"><td class="td"><a href="{{ route('admin.payments.show', $p->id) }}" class="font-mono text-xs text-white hover:text-link">{{ $p->number }}</a></td><td class="td">{{ $p->workspace?->name }}</td><td class="td text-right font-mono">{{ $rp($p->amount) }}</td><td class="td"><x-status-badge :status="$p->status" /></td></tr>@endforeach
  </tbody></table></div></section>@endif

  @if ($r['messages']->isNotEmpty())<section class="card overflow-hidden"><h2 class="font-semibold text-white px-5 pt-4 pb-3">Pesan</h2><div class="overflow-x-auto"><table class="w-full"><tbody>
    @foreach ($r['messages'] as $m)<tr class="tr"><td class="td font-mono text-xs">{{ $m->id }}</td><td class="td">{{ $m->environment?->workspace?->name }}</td><td class="td font-mono text-xs">{{ $m->destination_masked }}</td><td class="td"><x-status-badge :status="$m->status" /></td></tr>@endforeach
  </tbody></table></div></section>@endif

  @if ($r['emails']->isNotEmpty())<section class="card overflow-hidden"><h2 class="font-semibold text-white px-5 pt-4 pb-3">Email sistem</h2><div class="overflow-x-auto"><table class="w-full"><tbody>
    @foreach ($r['emails'] as $e)<tr class="tr"><td class="td font-mono text-xs">{{ $e->recipient }}</td><td class="td">{{ \Illuminate\Support\Str::limit($e->subject, 60) }}</td><td class="td"><x-status-badge :status="$e->status" /></td><td class="td text-zinc-500">{{ $e->created_at->diffForHumans() }}</td></tr>@endforeach
  </tbody></table></div></section>@endif
</div>
@endsection
