@php
    $user = auth()->user();
    $announcements = $user ? \App\Models\SystemAnnouncement::visibleFor($user)->get() : collect();
@endphp

@if ($announcements->isNotEmpty())
    <div style="display: flex; flex-direction: column; gap: 0.75rem; margin: 1rem 0 0.25rem 0;">
        @foreach ($announcements as $announcement)
            @php
                $palette = match ($announcement->type) {
                    'warning' => ['bg' => '#fffbeb', 'border' => '#F59E0B', 'title' => '#92400e', 'text' => '#78350f'],
                    'success' => ['bg' => '#ecfdf5', 'border' => '#10b981', 'title' => '#065f46', 'text' => '#064e3b'],
                    'danger' => ['bg' => '#fef2f2', 'border' => '#ef4444', 'title' => '#991b1b', 'text' => '#7f1d1d'],
                    default => ['bg' => '#eff6ff', 'border' => '#3b82f6', 'title' => '#1e40af', 'text' => '#1e3a8a'],
                };
            @endphp
            <div
                x-data="{ dismissed: sessionStorage.getItem('ts_ann_{{ $announcement->id }}') === '1' }"
                x-show="!dismissed"
                style="
                    background-color: {{ $palette['bg'] }};
                    border: 1px solid {{ $palette['border'] }};
                    border-inline-start: 5px solid {{ $palette['border'] }};
                    border-radius: 0.625rem;
                    padding: 0.85rem 1.1rem;
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 1rem;
                "
            >
                <div style="flex: 1;">
                    <div style="font-weight: 700; font-size: 0.95rem; color: {{ $palette['title'] }}; margin-bottom: 0.2rem;">
                        {{ $announcement->title }}
                    </div>
                    <div style="font-size: 0.875rem; color: {{ $palette['text'] }}; line-height: 1.5; white-space: pre-line;">{{ $announcement->body }}</div>
                </div>

                @if ($announcement->is_dismissible)
                    <button
                        type="button"
                        x-on:click="dismissed = true; sessionStorage.setItem('ts_ann_{{ $announcement->id }}', '1')"
                        aria-label="Close"
                        style="
                            border: none;
                            background: transparent;
                            color: {{ $palette['title'] }};
                            font-size: 1.15rem;
                            font-weight: 700;
                            cursor: pointer;
                            line-height: 1;
                            padding: 0.15rem 0.35rem;
                        "
                    >&times;</button>
                @endif
            </div>
        @endforeach
    </div>
@endif
