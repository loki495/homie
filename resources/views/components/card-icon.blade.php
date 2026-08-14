@props(['src'])

<img
    src="{{ $src }}"
    alt=""
    {{ $attributes->merge(['class' => \App\Support\DashboardIcons::isMonochrome($src) ? 'dark:invert' : '']) }}
>
