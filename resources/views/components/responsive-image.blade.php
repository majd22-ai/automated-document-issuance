@props(['src', 'alt' => '', 'sizes' => '100vw', 'priority' => 'auto', 'loading' => 'eager'])
@php
  $image = \App\Support\ResponsiveImages::image($src);
  $webp = \App\Support\ResponsiveImages::srcset($src);
  $avif = \App\Support\ResponsiveImages::srcset($src, 'avif');
@endphp
@if($avif)<picture style="display:contents"><source type="image/avif" srcset="{{ $avif }}" sizes="{{ $sizes }}">@endif
<img src="{{ asset($src) }}" @if($webp)srcset="{{ $webp }}" sizes="{{ $sizes }}"@endif alt="{{ $alt }}" width="{{ $attributes->get('width', $image['width'] ?? null) }}" height="{{ $attributes->get('height', $image['height'] ?? null) }}" loading="{{ $loading }}" fetchpriority="{{ $priority }}" {{ $attributes->except(['width', 'height']) }}>
@if($avif)</picture>@endif
