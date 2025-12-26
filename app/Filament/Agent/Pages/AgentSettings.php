<?php

namespace App\Filament\Agent\Pages;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class AgentSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.agent.pages.agent-settings';

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
        $tenant = Auth::user()->tenant;
        $this->form->fill([
            'name' => $tenant->name,
            'logo_path' => $tenant->logo_path,
            'currency' => $tenant->currency,
            'language' => $tenant->language,
            'receipt_prefix' => $tenant->receipt_prefix,
            'receipt_next_number' => $tenant->receipt_next_number,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label(__('ui.name'))
                    ->required(),
                TextInput::make('receipt_prefix')
                    ->label(__('ui.receipt_prefix') ?? 'קידומת קבלה')
                    ->placeholder(__('ui.receipt_prefix_placeholder') ?? 'למשל: INV (השאר ריק למספרים בלבד)')
                    ->nullable(),
                TextInput::make('receipt_next_number')
                    ->label(__('ui.receipt_next_number') ?? 'מספר קבלה הבא')
                    ->numeric()
                    ->required(),
                FileUpload::make('logo_path')
                    ->label(__('ui.logo') ?? 'לוגו')
                    ->disk('public')
                    ->directory('logos')
                    ->image()
                    ->preserveFilenames(),
                Select::make('currency')
                    ->label(__('ui.currency'))
                    ->options([
                        'USD' => 'USD ($)',
                        'ILS' => 'ILS (₪)',
                        'EUR' => 'EUR (€)',
                        'GBP' => 'GBP (£)',
                    ])
                    ->required(),
                Select::make('language')
                    ->label(__('ui.language'))
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
        $tenant = Auth::user()->tenant;
        $data = $this->form->getState();

        // Ensure logo_path is a string if it's an array
        if (isset($data['logo_path']) && is_array($data['logo_path'])) {
            $data['logo_path'] = reset($data['logo_path']);
        }

        $tenant->update($data);

        Notification::make()
            ->success()
            ->title(__('ui.settings_saved'))
            ->send();
        
        // Refresh to apply changes (especially language)
        $this->redirect(route('filament.agent.pages.agent-settings'));
    }
}
