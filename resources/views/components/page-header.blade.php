@props(['title', 'subtitle' => null])
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
  <div class="min-w-0">
    <h1 class="text-xl font-semibold text-white truncate">{{ $title }}</h1>
    @if ($subtitle)<p class="text-sm text-zinc-500 mt-1">{{ $subtitle }}</p>@endif
  </div>
  <div class="flex items-center gap-2 flex-wrap">{{ $slot }}</div>
</div>
