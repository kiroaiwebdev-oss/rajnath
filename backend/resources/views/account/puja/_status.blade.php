@php $m = ['awaiting_details' => ['Details needed', 'badge-gold'], 'requested' => ['Being arranged', 'badge-ink'], 'scheduled' => ['Scheduled', 'badge-success'], 'performed' => ['Performed', 'badge-success'], 'completed' => ['Completed', 'badge-success'], 'cancelled' => ['Cancelled', 'badge-danger']]; @endphp
<span class="{{ $m[$s][1] ?? 'badge-ink' }}">{{ $m[$s][0] ?? $s }}</span>
