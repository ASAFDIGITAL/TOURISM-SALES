<?php

namespace App\Filament\Agent\Resources;

use App\Filament\Agent\Resources\StaffResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 9;

    public static function getNavigationLabel(): string
    {
        return __('ui.team_and_staff');
    }

    public static function getModelLabel(): string
    {
        return __('ui.staff_member');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.team_and_staff');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->getActiveTenantId() !== null && $user->isAgencyOwner();
    }

    public static function canCreate(): bool
    {
        $tenant = Auth::user()?->getActiveTenant();

        return $tenant !== null && $tenant->canAddMoreUsers();
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User $record */
        if ($record->id === Auth::id()) {
            return false;
        }

        return $record->agency_role === User::AGENCY_ROLE_STAFF;
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = Auth::user()?->getActiveTenantId();

        return parent::getEloquentQuery()
            ->where('tenant_id', $tenantId)
            ->where('role', 'agent');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('ui.staff_details_section'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('ui.name'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label(__('ui.email'))
                            ->email()
                            ->required()
                            ->unique(table: User::class, ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label(__('ui.phone'))
                            ->tel()
                            ->maxLength(30),
                        Forms\Components\TextInput::make('password')
                            ->label(__('ui.password'))
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->minLength(6)
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('ui.staff_permissions_section'))
                    ->description(__('ui.staff_permissions_desc'))
                    ->visible(fn (?User $record): bool => $record === null || $record->agency_role === User::AGENCY_ROLE_STAFF)
                    ->schema([
                        Forms\Components\Select::make('role_preset')
                            ->label(__('ui.quick_role_preset'))
                            ->options([
                                'sales_rep' => __('ui.preset_sales_rep'),
                                'operations' => __('ui.preset_operations'),
                                'office_manager' => __('ui.preset_office_manager'),
                            ])
                            ->placeholder(__('ui.preset_custom_placeholder'))
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if ($state) {
                                    $set('permissions', User::rolePresetPermissions($state));
                                }
                            }),

                        Forms\Components\CheckboxList::make('permissions')
                            ->label(__('ui.permissions_list_label'))
                            ->options(fn (): array => User::permissionOptions())
                            ->default(User::rolePresetPermissions('sales_rep'))
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ui.name'))
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('ui.email'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('ui.phone'))
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('agency_role')
                    ->label(__('ui.role'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === User::AGENCY_ROLE_STAFF
                        ? __('ui.agency_role_staff')
                        : __('ui.agency_role_owner'))
                    ->color(fn (?string $state): string => $state === User::AGENCY_ROLE_STAFF ? 'info' : 'warning'),
                Tables\Columns\TextColumn::make('permissions_summary')
                    ->label(__('ui.permissions_list_label'))
                    ->state(function (User $record): string {
                        if ($record->isAgencyOwner()) {
                            return __('ui.full_owner_access');
                        }

                        $perms = (array) ($record->permissions ?? []);
                        if (empty($perms)) {
                            return __('ui.basic_sales_only');
                        }

                        $labels = User::permissionOptions();

                        return collect($perms)
                            ->map(fn (string $p) => $labels[$p] ?? $p)
                            ->take(3)
                            ->implode(' • ') . (count($perms) > 3 ? ' (+' . (count($perms) - 3) . ')' : '');
                    })
                    ->wrap(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (User $record): bool => static::canDelete($record)),
            ])
            ->defaultSort('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaff::route('/'),
            'create' => Pages\CreateStaff::route('/create'),
            'edit' => Pages\EditStaff::route('/{record}/edit'),
        ];
    }
}
