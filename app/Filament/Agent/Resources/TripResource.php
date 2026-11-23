<?php

namespace App\Filament\Agent\Resources;

use App\Filament\Agent\Resources\TripResource\Pages;
use App\Filament\Agent\Resources\TripResource\RelationManagers;
use App\Models\Customer;
use App\Models\Trip;
use App\Services\AviationDirectoryService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TripResource extends Resource
{
    protected static ?string $model = Trip::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-americas';

    public static function getModelLabel(): string
    {
        return __('ui.trip');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.trips');
    }

    public static function getNavigationLabel(): string
    {
        return __('ui.trips');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && ! $user->hasAgentPermission(\App\Models\User::PERM_VIEW_ALL_TRIPS)) {
            $query->where('created_by_user_id', $user->id);
        }

        return $query;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasAgentPermission(\App\Models\User::PERM_DELETE_RECORDS) ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->hasAgentPermission(\App\Models\User::PERM_DELETE_RECORDS) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Trip Details')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make(__('ui.basic_info') ?? 'פרטים כלליים')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Select::make('customer_id')
                                            ->label(__('ui.customer'))
                                            ->relationship(
                                                name: 'customer',
                                                titleAttribute: 'name',
                                                modifyQueryUsing: fn (Builder $query) => $query->orderBy('name'),
                                            )
                                            ->getOptionLabelFromRecordUsing(fn (Customer $record): string => trim($record->name . ($record->phone ? ' — ' . $record->phone : '')))
                                            ->searchable(['name', 'phone', 'email'])
                                            ->preload()
                                            ->createOptionForm([
                                                Forms\Components\Grid::make(2)
                                                    ->schema([
                                                        Forms\Components\TextInput::make('name')
                                                            ->label(__('ui.name'))
                                                            ->required()
                                                            ->maxLength(255),
                                                        Forms\Components\TextInput::make('phone')
                                                            ->label(__('ui.phone'))
                                                            ->tel()
                                                            ->maxLength(255),
                                                        Forms\Components\TextInput::make('email')
                                                            ->label(__('ui.email'))
                                                            ->email()
                                                            ->maxLength(255),
                                                        Forms\Components\TextInput::make('passport_number')
                                                            ->label(__('ui.passport_number'))
                                                            ->maxLength(255),
                                                    ]),
                                                Forms\Components\Textarea::make('address')
                                                    ->label(__('ui.address'))
                                                    ->rows(2),
                                                Forms\Components\Textarea::make('notes')
                                                    ->label(__('ui.notes'))
                                                    ->rows(2),
                                            ])
                                            ->createOptionUsing(function (array $data): int {
                                                $data['tenant_id'] = auth()->user()->getActiveTenantId();

                                                return Customer::create($data)->getKey();
                                            })
                                            ->createOptionModalHeading(__('ui.quick_new_customer'))
                                            ->createOptionAction(fn (Forms\Components\Actions\Action $action) => $action
                                                ->label(__('ui.quick_new_customer'))
                                                ->tooltip(__('ui.quick_new_customer'))
                                                ->modalSubmitActionLabel(__('ui.quick_new_customer'))
                                            )
                                            ->required(),
                                        Forms\Components\TextInput::make('title')
                                            ->label(__('ui.title'))
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\Select::make('destination')
                                            ->label(__('ui.destination'))
                                            ->searchable()
                                            ->options(fn (): array => app(AviationDirectoryService::class)->searchDestinations('', 30))
                                            ->getSearchResultsUsing(fn (string $search): array => app(AviationDirectoryService::class)->searchDestinations($search, 25))
                                            ->getOptionLabelUsing(fn ($value): ?string => $value ? (string) $value : null)
                                            ->placeholder(__('ui.search_destination_placeholder'))
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('custom_value')
                                                    ->label(__('ui.destination'))
                                                    ->required()
                                                    ->maxLength(255),
                                            ])
                                            ->createOptionUsing(fn (array $data): string => trim((string) $data['custom_value']))
                                            ->createOptionModalHeading(__('ui.custom_destination')),
                                        Forms\Components\DatePicker::make('start_date')
                                            ->label(__('ui.start_date'))
                                            ->native(false),
                                        Forms\Components\DatePicker::make('end_date')
                                            ->label(__('ui.end_date'))
                                            ->native(false),
                                        Forms\Components\Select::make('status')
                                            ->label(__('ui.status'))
                                            ->options([
                                                'quote' => __('ui.quote'),
                                                'draft' => __('ui.draft'),
                                                'confirmed' => __('ui.confirmed'),
                                                'completed' => __('ui.completed'),
                                                'cancelled' => __('ui.cancelled'),
                                            ])
                                            ->default('draft')
                                            ->required(),
                                        Forms\Components\TextInput::make('total_amount')
                                            ->label(__('ui.total_amount'))
                                            ->numeric()
                                            ->prefix(fn () => match (auth()->user()->getActiveTenant()?->currency ?? 'USD') {
                                                'ILS' => '₪',
                                                'EUR' => '€',
                                                'GBP' => '£',
                                                default => '$',
                                            })
                                            ->required(),
                                        Forms\Components\Select::make('created_by_user_id')
                                            ->label(__('ui.assigned_sales_rep'))
                                            ->options(fn (): array => \App\Models\User::query()
                                                ->where('tenant_id', auth()->user()?->getActiveTenantId())
                                                ->where('role', 'agent')
                                                ->orderBy('name')
                                                ->pluck('name', 'id')
                                                ->all())
                                            ->default(fn () => auth()->id())
                                            ->searchable()
                                            ->visible(fn (): bool => auth()->user()?->isAgencyOwner() ?? false),
                                    ]),
                                Forms\Components\Textarea::make('notes')
                                    ->label(__('ui.notes'))
                                    ->columnSpanFull(),
                            ]),
                        Forms\Components\Tabs\Tab::make(__('ui.itinerary'))
                            ->schema([
                                Forms\Components\Repeater::make('hotels')
                                    ->label(__('ui.hotels'))
                                    ->schema([
                                        Forms\Components\TextInput::make('name')->label(__('ui.hotel_name')),
                                        Forms\Components\DatePicker::make('from')->label(__('ui.start_date'))->native(false),
                                        Forms\Components\DatePicker::make('until')->label(__('ui.end_date'))->native(false),
                                        Forms\Components\TextInput::make('rooms')->label(__('ui.rooms_count'))->numeric(),
                                    ])
                                    ->columns(4)
                                    ->columnSpanFull(),
                                
                                Forms\Components\Repeater::make('flights')
                                    ->label(__('ui.flights'))
                                    ->schema([
                                        Forms\Components\Select::make('type')
                                            ->label(__('ui.flight_type'))
                                            ->options([
                                                'outbound' => __('ui.outbound'),
                                                'return' => __('ui.return'),
                                                'connection' => __('ui.connection'),
                                            ])
                                            ->default('outbound')
                                            ->columnSpan(['default' => 6, 'md' => 2]),

                                        Forms\Components\Select::make('airline')
                                            ->label(__('ui.airline'))
                                            ->searchable()
                                            ->options(fn (): array => app(AviationDirectoryService::class)->searchAirlines('', 30))
                                            ->getSearchResultsUsing(fn (string $search): array => app(AviationDirectoryService::class)->searchAirlines($search, 25))
                                            ->getOptionLabelUsing(fn ($value): ?string => $value ? (string) $value : null)
                                            ->placeholder(__('ui.search_airline_placeholder'))
                                            ->live()
                                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                                if (blank($get('flight_number'))) {
                                                    $code = app(AviationDirectoryService::class)->extractAirlineCode($state);
                                                    if ($code) {
                                                        $set('flight_number', $code);
                                                    }
                                                }
                                            })
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('custom_value')
                                                    ->label(__('ui.airline'))
                                                    ->required()
                                                    ->maxLength(120),
                                            ])
                                            ->createOptionUsing(fn (array $data): string => trim((string) $data['custom_value']))
                                            ->createOptionModalHeading(__('ui.custom_airline'))
                                            ->columnSpan(['default' => 6, 'md' => 2]),

                                        Forms\Components\TextInput::make('flight_number')
                                            ->label(__('ui.flight_number'))
                                            ->placeholder('LY315')
                                            ->maxLength(32)
                                            ->columnSpan(['default' => 6, 'md' => 2]),

                                        Forms\Components\Select::make('from_airport')
                                            ->label(__('ui.from_airport'))
                                            ->searchable()
                                            ->options(fn (): array => app(AviationDirectoryService::class)->searchAirports('', 30))
                                            ->getSearchResultsUsing(fn (string $search): array => app(AviationDirectoryService::class)->searchAirports($search, 25))
                                            ->getOptionLabelUsing(fn ($value): ?string => $value ? (string) $value : null)
                                            ->placeholder(__('ui.search_airport_placeholder'))
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('custom_value')
                                                    ->label(__('ui.from_airport'))
                                                    ->required()
                                                    ->maxLength(160),
                                            ])
                                            ->createOptionUsing(fn (array $data): string => trim((string) $data['custom_value']))
                                            ->createOptionModalHeading(__('ui.custom_airport'))
                                            ->columnSpan(['default' => 6, 'md' => 3]),

                                        Forms\Components\Select::make('to_airport')
                                            ->label(__('ui.to_airport'))
                                            ->searchable()
                                            ->options(fn (): array => app(AviationDirectoryService::class)->searchAirports('', 30))
                                            ->getSearchResultsUsing(fn (string $search): array => app(AviationDirectoryService::class)->searchAirports($search, 25))
                                            ->getOptionLabelUsing(fn ($value): ?string => $value ? (string) $value : null)
                                            ->placeholder(__('ui.search_airport_placeholder'))
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('custom_value')
                                                    ->label(__('ui.to_airport'))
                                                    ->required()
                                                    ->maxLength(160),
                                            ])
                                            ->createOptionUsing(fn (array $data): string => trim((string) $data['custom_value']))
                                            ->createOptionModalHeading(__('ui.custom_airport'))
                                            ->columnSpan(['default' => 6, 'md' => 3]),

                                        Forms\Components\DateTimePicker::make('departure_time')
                                            ->label(__('ui.departure_time') ?? 'זמן המראה')
                                            ->native(false)
                                            ->columnSpan(['default' => 6, 'md' => 3]),

                                        Forms\Components\DateTimePicker::make('arrival_time')
                                            ->label(__('ui.arrival_time'))
                                            ->native(false)
                                            ->columnSpan(['default' => 6, 'md' => 3]),
                                    ])
                                    ->columns(6)
                                    ->columnSpanFull(),

                                Forms\Components\Repeater::make('passengers')
                                    ->label(__('ui.passengers'))
                                    ->schema([
                                        Forms\Components\TextInput::make('name')->label(__('ui.passenger_name')),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),

                                Forms\Components\RichEditor::make('trip_summary')
                                    ->label(__('ui.trip_summary'))
                                    ->columnSpanFull(),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('ui.customer'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label(__('ui.title'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('destination')
                    ->label(__('ui.destination'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('ui.creation_date'))
                    ->dateTime('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label(__('ui.start_date'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label(__('ui.total_amount'))
                    ->money(fn ($record) => $record->tenant->currency ?? 'USD', locale: 'en')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->label(__('ui.paid'))
                    ->money(fn ($record) => $record->tenant->currency ?? 'USD', locale: 'en')
                    ->badge()
                    ->color(fn ($record) => $record->paid_amount >= $record->total_amount ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('ui.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("ui.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'quote' => 'primary',
                        'draft' => 'gray',
                        'confirmed' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('portal_views_count')
                    ->label(__('ui.portal_views'))
                    ->badge()
                    ->color(fn ($state): string => ((int) $state) > 0 ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state): string => '👁 ' . (int) $state)
                    ->tooltip(fn (Trip $record): ?string => $record->portal_last_viewed_at
                        ? __('ui.portal_last_viewed', ['time' => $record->portal_last_viewed_at->format('d/m/Y H:i')])
                        : null)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('ui.status'))
                    ->options([
                        'quote' => __('ui.quote'),
                        'draft' => __('ui.draft'),
                        'confirmed' => __('ui.confirmed'),
                        'completed' => __('ui.completed'),
                        'cancelled' => __('ui.cancelled'),
                    ]),
                Tables\Filters\SelectFilter::make('payment_state')
                    ->label(__('ui.payment_status_filter'))
                    ->options([
                        'unpaid' => __('ui.payment_filter_unpaid'),
                        'partial' => __('ui.payment_filter_partial'),
                        'paid' => __('ui.payment_filter_paid'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $val = $data['value'] ?? null;
                        if (! $val) {
                            return $query;
                        }

                        return match ($val) {
                            'unpaid' => $query
                                ->where('status', '!=', Trip::STATUS_CANCELLED)
                                ->where('total_amount', '>', 0)
                                ->whereRaw('COALESCE((SELECT SUM(amount) FROM payments WHERE payments.trip_id = trips.id), 0) < trips.total_amount'),
                            'partial' => $query
                                ->where('status', '!=', Trip::STATUS_CANCELLED)
                                ->whereRaw('COALESCE((SELECT SUM(amount) FROM payments WHERE payments.trip_id = trips.id), 0) > 0')
                                ->whereRaw('COALESCE((SELECT SUM(amount) FROM payments WHERE payments.trip_id = trips.id), 0) < trips.total_amount'),
                            'paid' => $query
                                ->where('total_amount', '>', 0)
                                ->whereRaw('COALESCE((SELECT SUM(amount) FROM payments WHERE payments.trip_id = trips.id), 0) >= trips.total_amount'),
                            default => $query,
                        };
                    }),
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label(__('ui.customer'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('created_by_user_id')
                    ->label(__('ui.sales_rep'))
                    ->relationship(
                        'createdBy',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('tenant_id', auth()->user()?->getActiveTenantId())
                    )
                    ->preload()
                    ->visible(fn (): bool => auth()->user()?->hasAgentPermission(\App\Models\User::PERM_VIEW_ALL_TRIPS) ?? false),
                Tables\Filters\Filter::make('departure_dates')
                    ->label(__('ui.departure_date_range'))
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('ui.from_date'))
                            ->native(false),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('ui.to_date'))
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('start_date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('start_date', '<=', $date));
                    }),
                Tables\Filters\SelectFilter::make('portal_viewed')
                    ->label(__('ui.portal_views'))
                    ->options([
                        'viewed' => __('ui.portal_filter_viewed'),
                        'not_viewed' => __('ui.portal_filter_not_viewed'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'viewed' => $query->where('portal_views_count', '>', 0),
                            'not_viewed' => $query->where(fn ($q) => $q->whereNull('portal_views_count')->orWhere('portal_views_count', 0)),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('convert_quote')
                    ->label(__('ui.convert_quote_to_trip'))
                    ->icon('heroicon-o-check-badge')
                    ->color('primary')
                    ->visible(fn (Trip $record): bool => $record->isQuote())
                    ->requiresConfirmation()
                    ->action(function (Trip $record) {
                        $record->update([
                            'status' => Trip::STATUS_CONFIRMED,
                            'quote_approved_at' => $record->quote_approved_at ?? now(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title(__('ui.quote_converted_success'))
                            ->send();
                    }),
                Tables\Actions\Action::make('open_portal')
                    ->label(__('ui.client_portal'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->url(fn (Trip $record): string => $record->getPortalUrl(), shouldOpenInNewTab: true),
                \App\Filament\Agent\Actions\SendTripMessageAction::makeTableAction(),
                Tables\Actions\Action::make('download_itinerary')
                    ->label(__('ui.download_itinerary'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function (Trip $record, \App\Services\TripDocumentationService $service) {
                        $content = $service->getItineraryPdfContent($record);
                        return response()->streamDownload(function () use ($content) {
                            echo $content;
                        }, "{$record->title}_Itinerary.pdf");
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentsRelationManager::class,
            RelationManagers\AttachmentsRelationManager::class,
            RelationManagers\RemindersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrips::route('/'),
            'create' => Pages\CreateTrip::route('/create'),
            'view' => Pages\ViewTrip::route('/{record}'),
            'edit' => Pages\EditTrip::route('/{record}/edit'),
        ];
    }
}
