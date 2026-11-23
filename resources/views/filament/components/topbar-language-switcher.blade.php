@php
    $currentLocale = app()->getLocale();
    $locales = [
        'he' => 'עב',
        'ar' => 'عربي',
        'en' => 'EN',
    ];
@endphp

<div
    class="ts-topbar-lang notranslate"
    translate="no"
    style="display: inline-flex; align-items: center; gap: 2px; padding: 3px; border-radius: 10px; background: rgba(148, 163, 184, 0.16); border: 1px solid rgba(148, 163, 184, 0.25); margin-inline-end: 6px;"
>
    @foreach ($locales as $code => $label)
        @php $active = $currentLocale === $code; @endphp
        <form method="POST" action="{{ route('locale.switch', $code) }}" style="margin: 0; display: inline-flex;">
            @csrf
            <button
                type="submit"
                translate="no"
                class="notranslate"
                style="border: none; border-radius: 7px; padding: 4px 8px; font-size: 0.74rem; font-weight: 700; line-height: 1.2; cursor: pointer; transition: all 0.15s ease; {{ $active ? 'background: #f59e0b; color: #0f172a; box-shadow: 0 2px 6px rgba(245, 158, 11, 0.35);' : 'background: transparent; color: inherit; opacity: 0.8;' }}"
            >
                {{ $label }}
            </button>
        </form>
    @endforeach
</div>
