<?php

namespace App\Filament\Agent\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class MobileAppNotifications extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?int $navigationSort = 95;

    protected static string $view = 'filament.agent.pages.mobile-app-notifications';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('ui.mobile_app_nav');
    }

    public function getTitle(): string
    {
        return __('ui.mobile_app_title');
    }

    public function mount(): void
    {
        $user = Auth::user();

        $this->form->fill([
            'notify_collections' => (bool) ($user->notify_collections ?? true),
            'notify_flights' => (bool) ($user->notify_flights ?? true),
            'notify_reminders' => (bool) ($user->notify_reminders ?? true),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('ui.notification_preferences'))
                    ->description(__('ui.notification_preferences_desc'))
                    ->schema([
                        Toggle::make('notify_collections')
                            ->label(__('ui.pref_collections_label'))
                            ->helperText(__('ui.pref_collections_help'))
                            ->onColor('primary'),
                        Toggle::make('notify_flights')
                            ->label(__('ui.pref_flights_label'))
                            ->helperText(__('ui.pref_flights_help'))
                            ->onColor('primary'),
                        Toggle::make('notify_reminders')
                            ->label(__('ui.pref_reminders_label'))
                            ->helperText(__('ui.pref_reminders_help'))
                            ->onColor('primary'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            \App\Filament\Agent\Actions\RequestSmsAccessAction::make(),
        ];
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
        $state = $this->form->getState();

        $user->update([
            'notify_collections' => (bool) ($state['notify_collections'] ?? false),
            'notify_flights' => (bool) ($state['notify_flights'] ?? false),
            'notify_reminders' => (bool) ($state['notify_reminders'] ?? false),
        ]);

        Notification::make()
            ->success()
            ->title(__('ui.settings_saved'))
            ->send();
    }

    public function getVapidPublicKey(): string
    {
        return (string) config('services.webpush.public_key', '');
    }

    public function getDevicesCount(): int
    {
        return Auth::user()->pushSubscriptions()->count();
    }
}
