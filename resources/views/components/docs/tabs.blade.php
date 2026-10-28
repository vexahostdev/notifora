{{-- Isi dengan beberapa <x-docs.code tab="Label"> --}}
<div class="doc-tabs my-4" x-data="{
  id: 'docs-tabs-' + crypto.randomUUID(),
  tabs: [],
  active: 0,
  select(i, focus = false) {
    this.active = (i + this.tabs.length) % this.tabs.length;
    if (focus) this.$nextTick(() => document.getElementById(this.id + '-tab-' + this.active)?.focus());
  }
}"
     x-init="tabs = [...$el.querySelectorAll('[data-tab-panel]')].map(e => e.dataset.tabPanel)"
     x-effect="$el.querySelectorAll('[data-tab-panel]').forEach((p, i) => { p.hidden = i !== active; p.id = id + '-panel-' + i; p.setAttribute('role', 'tabpanel'); p.setAttribute('aria-labelledby', id + '-tab-' + i); p.tabIndex = 0 })">
  <div role="tablist" class="flex gap-1 border-b border-line mb-[-1px] overflow-x-auto">
    <template x-for="(t, i) in tabs" :key="t">
      <button type="button" role="tab" :id="id + '-tab-' + i" :aria-controls="id + '-panel-' + i" :aria-selected="active === i" :tabindex="active === i ? 0 : -1" @click="select(i)"
              @keydown.arrow-right.prevent="select(active + 1, true)" @keydown.arrow-left.prevent="select(active - 1, true)" @keydown.home.prevent="select(0, true)" @keydown.end.prevent="select(tabs.length - 1, true)"
              :class="active === i ? 'border-cobalt text-white' : 'border-transparent text-zinc-500 hover:text-zinc-300'"
              class="px-3 py-2 text-xs font-mono font-bold border-b-2 whitespace-nowrap" x-text="t"></button>
    </template>
  </div>
  <div class="[&>.doc-code]:!my-0 [&>.doc-code]:!rounded-t-none">{{ $slot }}</div>
</div>
