@props(['title', 'description' => null, 'feed' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} · {{ config('app.name') }}</title>
        @if ($description)
            <meta name="description" content="{{ Str::limit($description, 160) }}">
            <meta property="og:description" content="{{ Str::limit($description, 160) }}">
        @endif
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:type" content="website">
        @if ($feed)
            <link rel="alternate" type="application/atom+xml" title="{{ $title }}" href="{{ $feed }}">
        @endif
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen">
        {{ $slot }}

        {{-- Alpine ships with Livewire, which only auto-injects on pages with a component. --}}
        @livewireScripts
    </body>
</html>
