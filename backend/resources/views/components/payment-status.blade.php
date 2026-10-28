@props(['status'])
@php
    $label = \App\Models\Payment::STATUSES[$status] ?? ucfirst($status);
    $cls = match ($status) { 'captured' => 'badge-success', 'failed', 'cancelled' => 'badge-danger', default => 'badge-gold' };
@endphp
<span class="{{ $cls }}">{{ $label }}</span>
