@php
    $toastPayloads = [];

    if (session('toast')) {
        $toastPayloads[] = session('toast');
    }

    if ($errors->any()) {
        $toastPayloads[] = [
            'type' => 'error',
            'title' => 'Please review the form',
            'message' => $errors->first(),
        ];
    }
@endphp

@if ($toastPayloads !== [])
    <div class="pointer-events-none fixed right-4 top-24 z-[70] flex w-full max-w-sm flex-col gap-3 sm:right-6" data-toast-stack>
        @foreach ($toastPayloads as $toast)
            <div
                class="pointer-events-auto ys-toast {{ ($toast['type'] ?? 'success') === 'error' ? 'ys-toast-error' : 'ys-toast-success' }}"
                data-toast
            >
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-current/20">
                        @if (($toast['type'] ?? 'success') === 'error')
                            <span class="text-sm leading-none">!</span>
                        @else
                            <span class="text-sm leading-none">&#10003;</span>
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">{{ $toast['title'] ?? 'Notice' }}</p>
                        <p class="mt-1 text-sm text-current/80">{{ $toast['message'] ?? '' }}</p>
                    </div>
                    <button type="button" class="shrink-0 text-current/70 transition hover:text-current" data-toast-dismiss aria-label="Dismiss toast">&times;</button>
                </div>
            </div>
        @endforeach
    </div>
@endif
