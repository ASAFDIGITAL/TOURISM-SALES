<?php

namespace App\Filament\Agent\Resources\HotelResource\Pages;

use App\Filament\Agent\Resources\HotelResource;
use App\Services\HotelSpreadsheetService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListHotels extends ListRecords
{
    protected static string $resource = HotelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_excel_template')
                ->label(__('ui.download_hotels_template'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $locale = app()->getLocale();
                    $service = app(HotelSpreadsheetService::class);

                    if (class_exists(\ZipArchive::class)) {
                        $content = $service->generateTemplateXlsx($locale);

                        return response()->streamDownload(
                            fn () => print($content),
                            'hotels-template.xlsx',
                            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                        );
                    }

                    $csv = $service->generateTemplateCsv($locale);

                    return response()->streamDownload(
                        fn () => print($csv),
                        'hotels-template.csv',
                        ['Content-Type' => 'text/csv; charset=UTF-8'],
                    );
                }),

            Actions\Action::make('import_hotels_excel')
                ->label(__('ui.import_hotels_excel'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->modalHeading(__('ui.import_hotels_modal_heading'))
                ->modalDescription(__('ui.import_hotels_modal_desc'))
                ->modalSubmitActionLabel(__('ui.import_now_btn'))
                ->form([
                    Forms\Components\FileUpload::make('spreadsheet')
                        ->label(__('ui.excel_or_csv_file'))
                        ->disk('local')
                        ->directory('imports/hotels')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                            'text/plain',
                            'application/csv',
                            'application/vnd.ms-excel',
                            'application/octet-stream',
                        ])
                        ->maxSize(5120)
                        ->helperText(__('ui.import_hotels_file_helper'))
                        ->required(),
                    Forms\Components\Toggle::make('update_existing')
                        ->label(__('ui.update_existing_hotels_toggle'))
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    $relativePath = (string) ($data['spreadsheet'] ?? '');
                    $disk = Storage::disk('local');

                    if ($relativePath === '' || ! $disk->exists($relativePath)) {
                        Notification::make()
                            ->title(__('ui.import_failed_title'))
                            ->danger()
                            ->send();

                        return;
                    }

                    $fullPath = $disk->path($relativePath);
                    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

                    try {
                        if (! in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
                            throw new \RuntimeException(__('ui.import_invalid_file_type'));
                        }

                        $tenantId = (int) auth()->user()?->getActiveTenantId();
                        $result = app(HotelSpreadsheetService::class)->importFromPath(
                            filePath: $fullPath,
                            tenantId: $tenantId,
                            updateExisting: (bool) ($data['update_existing'] ?? true),
                        );

                        Notification::make()
                            ->title(__('ui.hotels_import_success_title'))
                            ->body(__('ui.hotels_import_success_body', [
                                'created' => $result['created'],
                                'updated' => $result['updated'],
                                'skipped' => $result['skipped'],
                            ]))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('ui.import_failed_title'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    } finally {
                        $disk->delete($relativePath);
                    }
                }),

            Actions\CreateAction::make(),
        ];
    }
}
