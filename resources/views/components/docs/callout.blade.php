@props(['type' => 'info', 'title' => null])
@php
$s = [
  'info' => ['border-info/40 bg-info/10', 'text-blue-300', 'Catatan'],
  'tip' => ['border-ok/40 bg-ok/10', 'text-emerald-300', 'Tips'],
  'warn' => ['border-warn/40 bg-warn/10', 'text-amber-300', 'Perhatian'],
  'danger' => ['border-err/40 bg-err/10', 'text-red-300', 'Penting'],
][$type] ?? ['border-line bg-surface-2', 'text-zinc-300', 'Catatan'];
@endphp
<aside class="my-4 rounded-md border px-4 py-3 text-sm {{ $s[0] }}" role="note">
  <p class="text-xs font-mono font-bold uppercase tracking-wider mb-1 {{ $s[1] }}">{{ $title ?? $s[2] }}</p>
  <div class="text-zinc-300 [&_p]:my-1">{{ $slot }}</div>
</aside>
