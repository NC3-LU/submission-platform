@props(['variant' => 'auto'])

@if($variant === 'white')
    <img {{ $attributes->merge(['class' => '']) }} src="{{ asset('img/logo_nc3_white.png') }}" alt="NC3 Luxembourg">
@elseif($variant === 'color')
    <img {{ $attributes->merge(['class' => '']) }} src="{{ asset('img/Logo_NC3_horizontal_coul_versB.png') }}" alt="NC3 Luxembourg">
@else
    {{-- Auto: color in light mode, white in dark mode --}}
    <img {{ $attributes->merge(['class' => 'dark:hidden']) }} src="{{ asset('img/Logo_NC3_horizontal_coul_versB.png') }}" alt="NC3 Luxembourg">
    <img {{ $attributes->merge(['class' => 'hidden dark:block']) }} src="{{ asset('img/logo_nc3_white.png') }}" alt="NC3 Luxembourg">
@endif
