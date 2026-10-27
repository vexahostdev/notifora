@props(['channel'])
@php
$v = is_object($channel) ? $channel->value : (string) $channel;
$m = [
  'WHATSAPP' => ['WA', 'text-emerald-400 border-emerald-500/30 bg-emerald-500/10', 'WhatsApp'],
  'EMAIL' => ['@', 'text-blue-400 border-blue-500/30 bg-blue-500/10', 'Email'],
  'TELEGRAM' => ['TG', 'text-sky-400 border-sky-500/30 bg-sky-500/10', 'Telegram'],
  'IN_APP' => ['IN', 'text-amber-400 border-amber-500/30 bg-amber-500/10', 'In-App'],
][$v] ?? ['?', 'text-zinc-400 border-line', $v];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 text-xs"]) }} title="{{ $m[2] }}">
  <span class="inline-flex w-6 h-6 items-center justify-center rounded border font-mono text-[10px] font-bold {{ $m[1] }}">{{ $m[0] }}</span>
  <span class="text-zinc-300">{{ $m[2] }}</span>
</span>
