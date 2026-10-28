<x-layouts.public title="Account deleted">
    <div class="container-page max-w-2xl py-16">
        <div class="card p-6 sm:p-8" role="status">
            <h1 class="text-2xl">Your account has been deleted</h1>
            <p class="mt-2 text-ink-600">Reference {{ substr($ref, -10) }}. What we did:</p>
            <ul class="mt-4 list-disc space-y-1 pl-5 text-sm">@foreach ($steps as $s)<li>{{ ucfirst($s) }}</li>@endforeach</ul>
            <p class="mt-4 text-xs text-ink-600">Copies in our encrypted backups are overwritten within 14 days.</p>
            <a href="{{ route('home') }}" class="btn-outline mt-6">Back to home</a>
        </div>
    </div>
</x-layouts.public>
