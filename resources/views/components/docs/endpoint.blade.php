@props(['method' => 'GET', 'path'])
@php
$c = ['GET' => 'bg-info/15 text-blue-300 border-info/40', 'POST' => 'bg-ok/15 text-emerald-300 border-ok/40', 'PUT' => 'bg-warn/15 text-amber-300 border-warn/40', 'DELETE' => 'bg-err/15 text-red-300 border-err/40'][$method] ?? 'bg-zinc-500/15 text-zinc-300 border-zinc-500/40';
@endphp
<div class="my-3 flex flex-wrap items-center gap-3 rounded-md border border-line bg-surface px-3 py-2">
  <span class="rounded border px-2 py-0.5 text-[11px] font-mono font-bold {{ $c }}">{{ $method }}</span>
  <code class="font-mono text-[13px] text-zinc-200 break-all">{{ $path }}</code>
</div>
