@php($customFavicon = \App\Services\Brand::get('brand_favicon_path'))
@if ($customFavicon)
  <link rel="icon" href="{{ \App\Services\Brand::faviconUrl() }}">
@else
  <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
  <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
@endif
<meta name="theme-color" content="{{ \App\Services\Brand::accent() }}">
@if (\App\Services\Brand::hasCustomAccent())
  <style>:root,:root[data-theme="light"]{--color-cobalt:{{ \App\Services\Brand::accent() }};--color-cobalt-hover:{{ \App\Services\Brand::accentHover() }}}</style>
@endif
