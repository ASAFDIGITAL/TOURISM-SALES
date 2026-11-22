<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportTicketResource\Pages;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('ui.support_tickets');
    }

    public static function getModelLabel(): string
    {
        return __('ui.support_ticket');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.support_tickets');
    }

    public static function getNavigationBadge(): ?string
    {
        $unread = (int) SupportTicket::query()->sum('admin_unread_count');
        if ($unread > 0) {
            return (string) $unread;
        }

        $open = SupportTicket::query()
            ->where('status', '!=', SupportTicket::STATUS_CLOSED)
            ->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $unread = (int) SupportTicket::query()->sum('admin_unread_count');

        return $unread > 0 ? 'danger' : 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('ui.new_support_ticket'))
                    ->schema([
                        Forms\Components\Select::make('tenant_id')
                            ->label(__('ui.agent'))
                            ->relationship('tenant', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('subject')
                            ->label(__('ui.ticket_subject'))
                            ->required()
                            ->maxLength(180),

                        Forms\Components\Select::make('category')
                            ->label(__('ui.ticket_category'))
                            ->options(SupportTicket::categoryOptions())
                            ->default(SupportTicket::CATEGORY_GENERAL)
                            ->required(),

                        Forms\Components\Select::make('priority')
                            ->label(__('ui.ticket_priority'))
                            ->options(SupportTicket::priorityOptions())
                            ->default(SupportTicket::PRIORITY_NORMAL)
                            ->required(),

                        Forms\Components\Textarea::make('initial_message')
                            ->label(__('ui.ticket_message'))
                            ->rows(5)
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['tenant', 'user', 'latestMessage']))
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label(__('ui.agent'))
                    ->description(fn (SupportTicket $record): ?string => $record->user?->name)
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('subject')
                    ->label(__('ui.ticket_subject'))
                    ->description(fn (SupportTicket $record): ?string => $record->latestMessage
                        ? Str::limit($record->latestMessage->body, 70)
                        : null)
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('admin_unread_count')
                    ->label(__('ui.unread_messages'))
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? __('ui.unread_count_badge', ['count' => $state]) : __('ui.read_label'))
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('category')
                    ->label(__('ui.ticket_category'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('ui.ticket_category_' . $state))
                    ->color('gray'),

                Tables\Columns\TextColumn::make('priority')
                    ->label(__('ui.ticket_priority'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('ui.ticket_priority_' . $state))
                    ->color(fn (string $state): string => match ($state) {
                        SupportTicket::PRIORITY_URGENT => 'danger',
                        SupportTicket::PRIORITY_HIGH => 'warning',
                        SupportTicket::PRIORITY_NORMAL => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('ui.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('ui.ticket_status_' . $state))
                    ->color(fn (string $state): string => match ($state) {
                        SupportTicket::STATUS_OPEN => 'danger',
                        SupportTicket::STATUS_IN_PROGRESS => 'info',
                        SupportTicket::STATUS_WAITING_AGENT => 'warning',
                        default => 'success',
                    }),

                Tables\Columns\TextColumn::make('last_message_at')
                    ->label(__('ui.last_activity'))
                    ->since()
                    ->tooltip(fn (SupportTicket $record): ?string => $record->last_message_at?->format('d/m/Y H:i'))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('ui.status'))
                    ->options(SupportTicket::statusOptions()),

                Tables\Filters\SelectFilter::make('priority')
                    ->label(__('ui.ticket_priority'))
                    ->options(SupportTicket::priorityOptions()),

                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label(__('ui.agent'))
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('unread_only')
                    ->label(__('ui.only_unread_tickets'))
                    ->query(fn (Builder $query): Builder => $query->where('admin_unread_count', '>', 0)),
            ])
            ->actions([
                Tables\Actions\Action::make('open_chat')
                    ->label(__('ui.open_conversation'))
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('primary')
                    ->button()
                    ->url(fn (SupportTicket $record): string => static::getUrl('view', ['record' => $record])),

                Tables\Actions\Action::make('toggle_close')
                    ->label(fn (SupportTicket $record): string => $record->isClosed() ? __('ui.reopen_ticket') : __('ui.close_ticket'))
                    ->icon(fn (SupportTicket $record): string => $record->isClosed() ? 'heroicon-o-arrow-path' : 'heroicon-o-check-circle')
                    ->color(fn (SupportTicket $record): string => $record->isClosed() ? 'warning' : 'success')
                    ->action(function (SupportTicket $record): void {
                        app(SupportTicketService::class)->updateStatus(
                            $record,
                            $record->isClosed() ? SupportTicket::STATUS_OPEN : SupportTicket::STATUS_CLOSED,
                        );
                    }),
            ])
            ->recordUrl(fn (SupportTicket $record): string => static::getUrl('view', ['record' => $record]))
            ->defaultSort('last_message_at', 'desc')
            ->poll('15s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportTickets::route('/'),
            'create' => Pages\CreateSupportTicket::route('/create'),
            'view' => Pages\ViewSupportTicket::route('/{record}'),
        ];
    }
}
