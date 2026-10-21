@props(['name', 'size' => '1em'])
<svg {{ $attributes->class(['site-icon']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="{{ asset('assets/icons/outline.svg') }}#{{ $name }}" /></svg>
