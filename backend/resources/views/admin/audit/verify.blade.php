<x-layouts.admin title="Audit integrity">
    <div class="card max-w-xl p-6" role="status">
        @if ($result['ok'])
            <p class="badge-success">Chain intact</p>
            <p class="mt-3 text-sm">All {{ number_format($result['checked']) }} records verified. No record was altered, removed or inserted out of order.</p>
        @else
            <p class="badge-danger">Chain broken</p>
            <p class="mt-3 text-sm">Verification failed at record #{{ $result['broken_at'] }} after {{ $result['checked'] }} valid records. Treat this as a security incident (see docs/security/incident-response.md).</p>
        @endif
        <a href="{{ route('admin.audit.index') }}" class="btn-outline mt-5">Back to audit log</a>
    </div>
</x-layouts.admin>
