@props(['title', 'text' => null, 'icon' => 'list'])
<div class="flex flex-col items-center justify-center text-center py-14 px-6">
  <div class="w-11 h-11 rounded-full bg-zinc-800 flex items-center justify-center text-zinc-400 mb-3"><x-icon :name="$icon" class="w-5 h-5" /></div>
  <p class="font-medium text-zinc-200">{{ $title }}</p>
  @if ($text)<p class="text-sm text-zinc-500 mt-1 max-w-sm">{{ $text }}</p>@endif
  @if (trim($slot))<div class="mt-4">{{ $slot }}</div>@endif
</div>
