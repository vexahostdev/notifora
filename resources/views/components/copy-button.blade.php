@props(['text', 'label' => 'Salin'])
<button type="button" x-data="copy(@js($text))" @click="copy()" {{ $attributes->merge(['class' => 'btn-secondary btn-sm']) }}>
  <x-icon name="copy" class="w-3.5 h-3.5" /><span x-text="done ? 'Tersalin ✓' : @js($label)">{{ $label }}</span>
</button>
