<?php

namespace App\Filament\Agent\Actions;

use App\Models\Trip;
use App\Services\TripDocumentationService;
use Filament\Actions\Action as PageAction;
use Filament\Forms;
use Filament\Tables\Actions\Action as TableAction;

class DownloadItineraryAction
{
    /**
     * @return array<string, string>
     */
    public static function languageOptions(): array
    {
        return [
            'he' => 'עברית (Hebrew)',
            'ar' => 'العربية (Arabic)',
            'en' => 'English',
        ];
    }

    private static function defaultLanguage(Trip $trip): string
    {
        $lang = $trip->tenant?->language ?? app()->getLocale();

        return in_array($lang, ['he', 'ar', 'en'], true) ? $lang : 'he';
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private static function formSchema(Trip $trip): array
    {
        return [
            Forms\Components\Radio::make('language')
                ->label(__('ui.choose_document_language'))
                ->options(self::languageOptions())
                ->default(self::defaultLanguage($trip))
                ->required(),
        ];
    }

    private static function streamDownload(Trip $trip, ?string $lang)
    {
        $resolvedLang = in_array($lang, ['he', 'ar', 'en'], true) ? $lang : self::defaultLanguage($trip);
        $content = app(TripDocumentationService::class)->getItineraryPdfContent($trip, $resolvedLang);
        $safeTitle = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', $trip->title ?: 'Trip');

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, "{$safeTitle}_Itinerary_{$resolvedLang}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public static function makeTableAction(): TableAction
    {
        return TableAction::make('download_itinerary')
            ->label(__('ui.download_itinerary'))
            ->icon('heroicon-o-document-arrow-down')
            ->color('success')
            ->modalHeading(__('ui.download_itinerary_modal_title'))
            ->modalSubmitActionLabel(__('ui.download_pdf_btn'))
            ->modalWidth('sm')
            ->form(fn (Trip $record): array => self::formSchema($record))
            ->action(fn (Trip $record, array $data) => self::streamDownload($record, $data['language'] ?? null));
    }

    public static function makeHeaderAction(): PageAction
    {
        return PageAction::make('download_itinerary')
            ->label(__('ui.download_itinerary'))
            ->icon('heroicon-o-document-arrow-down')
            ->color('success')
            ->modalHeading(__('ui.download_itinerary_modal_title'))
            ->modalSubmitActionLabel(__('ui.download_pdf_btn'))
            ->modalWidth('sm')
            ->form(fn ($livewire): array => self::formSchema($livewire->record))
            ->action(fn (array $data, $livewire) => self::streamDownload($livewire->record, $data['language'] ?? null));
    }
}
