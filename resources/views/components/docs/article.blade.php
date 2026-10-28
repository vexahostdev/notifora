@props(['id', 'title'])
<article id="{{ $id }}" data-title="{{ $title }}" class="doc-article scroll-mt-32 py-8 first:pt-2 border-b border-line last:border-0">
  <h2 class="group flex items-center gap-2 text-xl font-semibold text-white tracking-tight">
    <a href="#{{ $id }}" class="opacity-0 group-hover:opacity-100 focus-visible:opacity-100 group-focus-within:opacity-100 text-link text-base font-mono" aria-label="Tautan ke bagian ini">#</a>{{ $title }}
  </h2>
  <div class="doc-body mt-2">{{ $slot }}</div>
</article>
