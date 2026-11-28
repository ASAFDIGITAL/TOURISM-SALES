<?php

namespace App\Filament\Agent\Resources;

use App\Filament\Agent\Resources\HotelResource\Pages;
use App\Models\Hotel;
use App\Models\User;
use App\Services\AviationDirectoryService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class HotelResource extends Resource
{
    protected static ?string $model = Hotel::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('ui.hotel_or_apartment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.hotels_and_apartments');
    }

    public static function getNavigationLabel(): string
    {
        return __('ui.hotels_and_apartments');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAgentPermission(User::PERM_DELETE_RECORDS) ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->hasAgentPermission(User::PERM_DELETE_RECORDS) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('ui.hotel_details_section'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label(__('ui.accommodation_type'))
                                    ->options([
                                        Hotel::TYPE_HOTEL => __('ui.accommodation_type_hotel'),
                                        Hotel::TYPE_APARTMENT => __('ui.accommodation_type_apartment'),
                                    ])
                                    ->default(Hotel::TYPE_HOTEL)
                                    ->required(),
                                Forms\Components\TextInput::make('name')
                                    ->label(__('ui.accommodation_name'))
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
                                Forms\Components\Select::make('stars')
                                    ->label(__('ui.hotel_stars'))
                                    ->options([
                                        1 => '★ (1)',
                                        2 => '★★ (2)',
                                        3 => '★★★ (3)',
                                        4 => '★★★★ (4)',
                                        5 => '★★★★★ (5)',
                                    ]),
                                Forms\Components\TextInput::make('default_supplier')
                                    ->label(__('ui.default_supplier'))
                                    ->placeholder(__('ui.supplier_name_placeholder'))
                                    ->maxLength(160),
                                Forms\Components\TextInput::make('phone')
                                    ->label(__('ui.phone'))
                                    ->tel()
                                    ->maxLength(64),
                                Forms\Components\TextInput::make('email')
                                    ->label(__('ui.email'))
                                    ->email()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('address')
                                    ->label(__('ui.address'))
                                    ->maxLength(255),
                            ]),
                        Forms\Components\Textarea::make('notes')
                            ->label(__('ui.notes'))
                            ->placeholder(__('ui.hotel_notes_placeholder'))
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('ui.active'))
                            ->helperText(__('ui.hotel_active_helper'))
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label(__('ui.accommodation_type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === Hotel::TYPE_APARTMENT
                        ? __('ui.accommodation_type_apartment')
                        : __('ui.accommodation_type_hotel'))
                    ->color(fn (?string $state): string => $state === Hotel::TYPE_APARTMENT ? 'primary' : 'info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ui.accommodation_name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('destination')
                    ->label(__('ui.destination'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('address')
                    ->label(__('ui.address'))
                    ->searchable()
                    ->limit(32)
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('stars')
                    ->label(__('ui.hotel_stars'))
                    ->formatStateUsing(fn ($state): string => $state ? str_repeat('★', (int) $state) . ' (' . $state . ')' : '-')
                    ->badge()
                    ->color(fn ($state): string => $state ? 'warning' : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('ui.phone'))
                    ->searchable()
                    ->copyable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('default_supplier')
                    ->label(__('ui.default_supplier'))
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('ui.active')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('ui.created_at'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('ui.accommodation_type'))
                    ->options([
                        Hotel::TYPE_HOTEL => __('ui.accommodation_type_hotel'),
                        Hotel::TYPE_APARTMENT => __('ui.accommodation_type_apartment'),
                    ]),
                Tables\Filters\SelectFilter::make('stars')
                    ->label(__('ui.hotel_stars'))
                    ->options([
                        5 => '★★★★★ (5)',
                        4 => '★★★★ (4)',
                        3 => '★★★ (3)',
                        2 => '★★ (2)',
                        1 => '★ (1)',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('ui.active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Hotel $record): bool => static::canDelete($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => static::canDeleteAny()),
                ]),
            ])
            ->defaultSort('name', 'asc')
            ->emptyStateHeading(__('ui.no_saved_hotels_yet'))
            ->emptyStateDescription(__('ui.no_saved_hotels_desc'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHotels::route('/'),
            'create' => Pages\CreateHotel::route('/create'),
            'edit' => Pages\EditHotel::route('/{record}/edit'),
        ];
    }
}
