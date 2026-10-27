@extends('layouts.app')
@section('title', 'Channels')
@section('content')
<x-page-header title="Channels" subtitle="Hubungkan provider pengiriman untuk environment ini." />
<div class="grid sm:grid-cols-2 gap-4">
  @foreach ($channels as $ch)
    @php $p = $providers[$ch->value] ?? null; @endphp
    <article class="card card-pad">
      <div class="flex items-center justify-between">
        <x-channel-icon :channel="$ch" />
        @if ($ch === \App\Enums\ChannelType::IN_APP)<x-status-badge status="active" />
        @elseif ($p)<x-status-badge :status="$p->status" />
        @else<span class="text-xs text-zinc-500 font-mono">Belum dikonfigurasi</span>@endif
      </div>
      <p class="text-sm text-zinc-400 mt-3">
        @switch($ch)
          @case(\App\Enums\ChannelType::WHATSAPP) VexaHost WhatsApp Gateway. @break
          @case(\App\Enums\ChannelType::EMAIL) Postivo (REST). @break
          @case(\App\Enums\ChannelType::TELEGRAM) Telegram Bot API. @break
          @default Inbox bawaan, selalu aktif — tidak perlu konfigurasi.
        @endswitch
      </p>
      @if ($p)<p class="hint font-mono">{{ $p->name }} · {{ $p->last_tested_at ? 'diuji '.$p->last_tested_at->diffForHumans() : 'belum pernah diuji' }}</p>@endif
      @if ($ch !== \App\Enums\ChannelType::IN_APP && $currentRole->atLeast(\App\Enums\MemberRole::DEVELOPER))
        <a href="{{ route('console.channels.edit', $ch->value) }}" class="btn-secondary mt-4">{{ $p ? 'Kelola' : 'Hubungkan' }}</a>
      @endif
    </article>
  @endforeach
</div>
@endsection
