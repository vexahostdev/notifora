@extends('layouts.docs')
@section('title', $section['title'])
@section('description', $section['summary'])
@section('content')
<nav class="text-xs text-zinc-500 mb-4" aria-label="Breadcrumb">
  <a href="{{ route('docs.index') }}" class="hover:text-white">Docs</a> <span class="mx-1">/</span> <span class="text-zinc-300">{{ $section['title'] }}</span>
</nav>
<div class="flex items-start gap-3">
  <span class="mt-1 inline-flex w-9 h-9 items-center justify-center rounded-md bg-cobalt/15 text-cobalt shrink-0"><x-icon :name="$section['icon']" class="w-5 h-5" /></span>
  <div>
    <h1 class="text-3xl font-bold text-white tracking-tight">{{ $section['title'] }}</h1>
    <p class="mt-2 text-zinc-400 leading-7">{{ $section['summary'] }}</p>
  </div>
</div>

{{-- Di halaman ini --}}
<div class="mt-6 rounded-md border border-line bg-surface-2 p-4">
  <p class="text-[11px] font-mono font-bold uppercase tracking-wider text-zinc-500 mb-2">Di halaman ini</p>
  <ul class="grid sm:grid-cols-2 gap-x-6 gap-y-1">
    @foreach ($section['articles'] as [$aid, $atitle])<li><a href="#{{ $aid }}" class="text-[13px] text-link hover:underline">{{ $atitle }}</a></li>@endforeach
  </ul>
</div>

<div class="mt-6">@include("docs.pages.$slug")</div>

<nav class="mt-10 grid sm:grid-cols-2 gap-3" aria-label="Halaman sebelumnya dan berikutnya">
  @if ($prev)<a href="{{ route('docs.show', $prev[0]) }}" class="card card-pad hover:border-zinc-600"><span class="text-[11px] font-mono uppercase text-zinc-500">← Sebelumnya</span><span class="block font-medium text-white mt-1">{{ $prev[1]['title'] }}</span></a>@else<span></span>@endif
  @if ($next)<a href="{{ route('docs.show', $next[0]) }}" class="card card-pad hover:border-zinc-600 sm:text-right"><span class="text-[11px] font-mono uppercase text-zinc-500">Berikutnya →</span><span class="block font-medium text-white mt-1">{{ $next[1]['title'] }}</span></a>@endif
</nav>
@endsection
