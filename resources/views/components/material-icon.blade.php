@props(['name'])

<span {{ $attributes->merge(['class' => 'icon']) }} aria-hidden="true">{{ $name }}</span>
