<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class AdminSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.admin-settings';

    public static function getNavigationLabel(): string
    {
        return __('ui.settings');
    }

    public function getTitle(): string
    {
        return __('ui.settings');
    }

    public ?array $data = [];

    public function mount(): void
    {
        $user = Auth::user();
        $this->form->fill([
            'currency' => $user->currency ?? 'ILS',
            'language' => $user->language ?? 'he',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('currency')
                    ->label(__('ui.currency') ?? 'Currency')
                    ->options([
                        'USD' => 'USD ($)',
                        'ILS' => 'ILS (₪)',
                        'EUR' => 'EUR (€)',
                        'GBP' => 'GBP (£)',
                    ])
                    ->required(),
                Select::make('language')
                    ->label(__('ui.language') ?? 'Language')
                    ->options([
                        'he' => 'Hebrew',
                        'ar' => 'Arabic',
                        'en' => 'English',
                    ])
                    ->required(),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('ui.save_changes'))
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $user = Auth::user();
        $data = $this->form->getState();

        $user->update($data);

        Notification::make()
            ->success()
            ->title(__('ui.settings_saved'))
            ->send();
        
        // Refresh to apply changes (especially language)
        $this->redirect(route('filament.admin.pages.admin-settings'));
    }
}
