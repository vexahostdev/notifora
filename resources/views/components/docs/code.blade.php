@props(['lang' => 'text', 'title' => null, 'tab' => null])
<div @if ($tab) data-tab-panel="{{ $tab }}" @endif x-data="{ copied: false }" class="doc-code my-4 rounded-md border border-line overflow-hidden bg-surface">
  <div class="flex items-center justify-between px-3 py-1.5 border-b border-line bg-surface-2">
    <span class="text-[11px] font-mono uppercase tracking-wider text-zinc-500">{{ $title ?? $lang }}</span>
    <button type="button" class="text-[11px] font-mono text-zinc-400 hover:text-white px-1.5 py-0.5 rounded hover:bg-white/5"
            @click="navigator.clipboard.writeText($refs.c.innerText).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
            x-text="copied ? 'Tersalin ✓' : 'Salin'">Salin</button>
  </div>
  <pre class="p-3 overflow-x-auto text-[12.5px] leading-6 font-mono text-zinc-300"><code x-ref="c">{!! e(trim((string) $slot, "\r\n")) !!}</code></pre>
</div>
