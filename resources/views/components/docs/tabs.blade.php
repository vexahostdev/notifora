{{-- Isi dengan beberapa <x-docs.code tab="Label"> --}}
<div class="doc-tabs my-4" x-data="{ tabs: [], active: 0 }"
     x-init="tabs = [...$el.querySelectorAll('[data-tab-panel]')].map(e => e.dataset.tabPanel)"
     x-effect="$el.querySelectorAll('[data-tab-panel]').forEach((p, i) => { p.hidden = i !== active })">
  <div role="tablist" class="flex gap-1 border-b border-line mb-[-1px] overflow-x-auto">
    <template x-for="(t, i) in tabs" :key="t">
      <button type="button" role="tab" :aria-selected="active === i" @click="active = i"
              :class="active === i ? 'border-cobalt text-white' : 'border-transparent text-zinc-500 hover:text-zinc-300'"
              class="px-3 py-2 text-xs font-mono font-bold border-b-2 whitespace-nowrap" x-text="t"></button>
    </template>
  </div>
  <div class="[&>.doc-code]:!my-0 [&>.doc-code]:!rounded-t-none">{{ $slot }}</div>
</div>
