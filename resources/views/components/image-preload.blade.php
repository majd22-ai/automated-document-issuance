@props(['src', 'sizes'])
@php
  $format = \App\Support\ResponsiveImages::preferredFormat($src);
  $candidates = \App\Support\ResponsiveImages::image($src)[$format] ?? [];
@endphp
@if($candidates)
<link rel="preload" as="image" type="image/{{ $format }}" href="{{ asset(reset($candidates)) }}" imagesrcset="{{ \App\Support\ResponsiveImages::srcset($src, $format) }}" imagesizes="{{ $sizes }}" fetchpriority="high" {{ $attributes }}>
@endif
