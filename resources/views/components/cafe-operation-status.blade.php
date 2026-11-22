@props(['tone' => 'neutral'])

@php
    $toneClass = match ($tone) {
        'pending' => 'cafe-operation-status-pending',
        'success' => 'cafe-operation-status-success',
        'danger' => 'cafe-operation-status-danger',
        'progress' => 'cafe-operation-status-progress',
        default => '',
    };
@endphp

<span {{ $attributes->class(['cafe-operation-status', $toneClass]) }}>{{ $slot }}</span>
