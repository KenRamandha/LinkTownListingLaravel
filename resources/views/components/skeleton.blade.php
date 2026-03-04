@props(['class' => 'w-full h-4'])

<div {{ $attributes->merge(['class' => 'bg-gray-200 animate-pulse rounded-lg ' . $class]) }}></div>