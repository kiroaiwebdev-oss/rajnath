@php $map = ['awaiting_photos' => ['Upload photos', 'badge-gold'], 'needs_new_photos' => ['New photos needed', 'badge-danger'], 'submitted' => ['Received', 'badge-ink'], 'in_review' => ['Being prepared', 'badge-ink'], 'completed' => ['Ready', 'badge-success']]; @endphp
<span class="{{ $map[$s][1] ?? 'badge-ink' }}">{{ $map[$s][0] ?? $s }}</span>
