{{-- Authentication pages share the platform header, footer and assets. --}}
@extends('layouts.public')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/password-visibility.css') }}">
@endpush

@push('scripts')
  <script src="{{ asset('js/password-visibility.js') }}" defer></script>
@endpush
