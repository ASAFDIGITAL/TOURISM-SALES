<?php

namespace App\Filament\Agent\Resources;

use App\Filament\Agent\Resources\TripResource\Pages;
use App\Filament\Agent\Resources\TripResource\RelationManagers;
use App\Models\Trip;
use Filament\Forms;
use Filament\Forms\Form;
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
                                            ->relationship('customer', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required(),
                                        Forms\Components\TextInput::make('title')
                                            ->label(__('ui.title'))
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('destination')
                                            ->label(__('ui.destination'))
                                            ->maxLength(255),
                                        Forms\Components\DatePicker::make('start_date')
                                            ->label(__('ui.start_date'))
                                            ->native(false),
                                        Forms\Components\DatePicker::make('end_date')
                                            ->label(__('ui.end_date'))
                                            ->native(false),
                                        Forms\Components\Select::make('status')
                                            ->label(__('ui.status'))
                                            ->options([
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
                                            ->prefix(fn () => auth()->user()->tenant->currency === 'ILS' ? '₪' : (auth()->user()->tenant->currency === 'EUR' ? '€' : '$'))
                                            ->required(),
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
                                        Forms\Components\TextInput::make('name')->label(__('ui.hotel_name'))->required(),
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
                                            ->required(),
                                        Forms\Components\TextInput::make('airline')->label(__('ui.airline')),
                                        Forms\Components\TextInput::make('flight_number')->label(__('ui.flight_number')),
                                        Forms\Components\DateTimePicker::make('departure_time')->label(__('ui.departure_time') ?? 'זמן המראה')->native(false),
                                    ])
                                    ->columns(4)
                                    ->columnSpanFull(),

                                Forms\Components\Repeater::make('passengers')
                                    ->label(__('ui.passengers'))
                                    ->schema([
                                        Forms\Components\TextInput::make('name')->label(__('ui.passenger_name'))->required(),
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
                        'draft' => 'gray',
                        'confirmed' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
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
