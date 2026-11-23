<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrialEmailTemplateResource\Pages;
use App\Models\Tenant;
use App\Models\TrialEmailTemplate;
use App\Services\TrialOnboardingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TrialEmailTemplateResource extends Resource
{
    protected static ?string $model = TrialEmailTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?int $navigationSort = 7;

    public static function getNavigationLabel(): string
    {
        return __('ui.trial_templates_and_updates');
    }

    public static function getModelLabel(): string
    {
        return __('ui.email_template');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.trial_templates_and_updates');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        $linesToArray = static function ($state): array {
            if (is_array($state)) {
                return array_values(array_filter(array_map('trim', $state)));
            }

            $lines = preg_split('/\r\n|\r|\n/', (string) $state) ?: [];

            return array_values(array_filter(array_map('trim', $lines)));
        };

        $arrayToLines = static function ($state): string {
            if (is_array($state)) {
                return implode("\n", array_filter(array_map(fn ($v) => is_array($v) ? ($v['text'] ?? '') : (string) $v, $state)));
            }

            return (string) $state;
        };

        return $form
            ->schema([
                Forms\Components\Section::make(__('ui.template_settings_section'))
                    ->description(__('ui.template_placeholders_help'))
                    ->schema([
                        Forms\Components\TextInput::make('step')
                            ->label(__('ui.template_step_label'))
                            ->disabled()
                            ->formatStateUsing(fn (string $state): string => __("ui.drip_step_label_{$state}")),

                        Forms\Components\TextInput::make('cta_path')
                            ->label(__('ui.cta_destination_path'))
                            ->placeholder('/agent/billing-and-invoices')
                            ->required()
                            ->maxLength(160),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('ui.active'))
                            ->default(true),
                    ])
                    ->columns(3),

                Forms\Components\Tabs::make('Languages')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('עברית')
                            ->schema([
                                Forms\Components\TextInput::make('subject_he')
                                    ->label(__('ui.subject') . ' (עברית)')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('heading_he')
                                    ->label(__('ui.email_heading') . ' (עברית)')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('intro_he')
                                    ->label(__('ui.email_intro_body') . ' (עברית)')
                                    ->rows(4)
                                    ->required(),
                                Forms\Components\Textarea::make('highlights_he')
                                    ->label(__('ui.email_bullets_label') . ' (עברית)')
                                    ->helperText(__('ui.one_item_per_line'))
                                    ->rows(4)
                                    ->formatStateUsing($arrayToLines)
                                    ->dehydrateStateUsing($linesToArray),
                                Forms\Components\TextInput::make('cta_label_he')
                                    ->label(__('ui.cta_button_label') . ' (עברית)')
                                    ->required()
                                    ->maxLength(120),
                            ]),

                        Forms\Components\Tabs\Tab::make('العربية')
                            ->schema([
                                Forms\Components\TextInput::make('subject_ar')
                                    ->label(__('ui.subject') . ' (العربية)')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('heading_ar')
                                    ->label(__('ui.email_heading') . ' (العربية)')
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('intro_ar')
                                    ->label(__('ui.email_intro_body') . ' (العربية)')
                                    ->rows(4),
                                Forms\Components\Textarea::make('highlights_ar')
                                    ->label(__('ui.email_bullets_label') . ' (العربية)')
                                    ->helperText(__('ui.one_item_per_line'))
                                    ->rows(4)
                                    ->formatStateUsing($arrayToLines)
                                    ->dehydrateStateUsing($linesToArray),
                                Forms\Components\TextInput::make('cta_label_ar')
                                    ->label(__('ui.cta_button_label') . ' (العربية)')
                                    ->maxLength(120),
                            ]),

                        Forms\Components\Tabs\Tab::make('English')
                            ->schema([
                                Forms\Components\TextInput::make('subject_en')
                                    ->label(__('ui.subject') . ' (English)')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('heading_en')
                                    ->label(__('ui.email_heading') . ' (English)')
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('intro_en')
                                    ->label(__('ui.email_intro_body') . ' (English)')
                                    ->rows(4),
                                Forms\Components\Textarea::make('highlights_en')
                                    ->label(__('ui.email_bullets_label') . ' (English)')
                                    ->helperText(__('ui.one_item_per_line'))
                                    ->rows(4)
                                    ->formatStateUsing($arrayToLines)
                                    ->dehydrateStateUsing($linesToArray),
                                Forms\Components\TextInput::make('cta_label_en')
                                    ->label(__('ui.cta_button_label') . ' (English)')
                                    ->maxLength(120),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('step')
                    ->label(__('ui.template_step_label'))
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): string => __("ui.drip_step_label_{$state}")),

                Tables\Columns\TextColumn::make('subject_he')
                    ->label(__('ui.subject'))
                    ->weight('bold')
                    ->wrap()
                    ->searchable(['subject_he', 'subject_ar', 'subject_en']),

                Tables\Columns\TextColumn::make('sent_count')
                    ->label(__('ui.total_sent_count'))
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_sent_at')
                    ->label(__('ui.last_sent_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('ui.active')),
            ])
            ->actions([
                Tables\Actions\Action::make('send_template')
                    ->label(__('ui.send_template_now_btn'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->modalHeading(fn (TrialEmailTemplate $record): string => __('ui.send_template_modal_title', [
                        'step' => __("ui.drip_step_label_{$record->step}"),
                    ]))
                    ->modalSubmitActionLabel(__('ui.send_now'))
                    ->form([
                        Forms\Components\Radio::make('target')
                            ->label(__('ui.send_to_label'))
                            ->options([
                                'trial' => __('ui.all_trial_agencies'),
                                'all_active' => __('ui.all_active_agents'),
                                'selected' => __('ui.specific_agents'),
                                'test' => __('ui.send_test_email_option'),
                            ])
                            ->default('trial')
                            ->live()
                            ->required(),

                        Forms\Components\Select::make('tenant_ids')
                            ->label(__('ui.select_agents'))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => Tenant::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->visible(fn (Get $get): bool => $get('target') === 'selected')
                            ->required(fn (Get $get): bool => $get('target') === 'selected'),

                        Forms\Components\TextInput::make('test_email')
                            ->label(__('ui.test_email_address'))
                            ->email()
                            ->default(fn () => auth()->user()?->email)
                            ->visible(fn (Get $get): bool => $get('target') === 'test')
                            ->required(fn (Get $get): bool => $get('target') === 'test'),

                        Forms\Components\Select::make('test_locale')
                            ->label(__('ui.message_language'))
                            ->options([
                                'he' => 'עברית',
                                'ar' => 'العربية',
                                'en' => 'English',
                            ])
                            ->default('he')
                            ->visible(fn (Get $get): bool => $get('target') === 'test'),
                    ])
                    ->action(function (TrialEmailTemplate $record, array $data, TrialOnboardingService $service) {
                        $target = (string) ($data['target'] ?? 'trial');

                        if ($target === 'test') {
                            $count = $service->sendTemplateManually(
                                template: $record,
                                tenants: collect(),
                                testEmail: (string) ($data['test_email'] ?? ''),
                                testLocale: (string) ($data['test_locale'] ?? 'he'),
                            );
                        } else {
                            $tenants = match ($target) {
                                'all_active' => Tenant::with('plan')->where('status', 'active')->get(),
                                'selected' => Tenant::with('plan')->whereIn('id', (array) ($data['tenant_ids'] ?? []))->get(),
                                default => Tenant::with('plan')->where('status', 'active')->where('is_trial', true)->get(),
                            };

                            $count = $service->sendTemplateManually($record, $tenants);
                        }

                        Notification::make()
                            ->success()
                            ->title(__('ui.template_sent_success', ['count' => $count]))
                            ->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->label(__('ui.edit_template_content')),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrialEmailTemplates::route('/'),
            'edit' => Pages\EditTrialEmailTemplate::route('/{record}/edit'),
        ];
    }
}
