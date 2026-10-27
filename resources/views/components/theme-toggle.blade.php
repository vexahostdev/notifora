@props(['class' => 'btn-ghost btn-sm'])
<button type="button"
        x-data="{ t: document.documentElement.getAttribute('data-theme') }"
        @click="t = t === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', t);
                try { localStorage.setItem('notifora_theme', t) } catch (e) {}
                window.dispatchEvent(new CustomEvent('themechange'))"
        :aria-label="t === 'dark' ? 'Beralih ke mode terang' : 'Beralih ke mode gelap'"
        :title="t === 'dark' ? 'Mode terang' : 'Mode gelap'"
        {{ $attributes->merge(['class' => $class]) }}>
  <span x-show="t === 'dark'"><x-icon name="sun" class="w-4 h-4" /></span>
  <span x-show="t !== 'dark'" x-cloak><x-icon name="moon" class="w-4 h-4" /></span>
</button>
