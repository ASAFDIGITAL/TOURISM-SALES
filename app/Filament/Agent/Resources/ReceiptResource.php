<?php

namespace App\Filament\Agent\Resources;

use App\Filament\Agent\Resources\ReceiptResource\Pages;
use App\Filament\Agent\Resources\ReceiptResource\RelationManagers;
use App\Models\Receipt;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReceiptResource extends Resource
{
    protected static ?string $model = Receipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    public static function getNavigationLabel(): string
    {
        return __('ui.receipts_report');
    }

    public static function getModelLabel(): string
    {
        return __('ui.receipt');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.receipts_report');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('receipt_number')
                    ->label(__('ui.receipt_number'))
                    ->readOnly(),
                Forms\Components\DateTimePicker::make('created_at')
                    ->label(__('ui.date_uploaded'))
                    ->readOnly(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('receipt_number')
                    ->label(__('ui.receipt_number'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('trip.customer.name')
                    ->label(__('ui.customer'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('trip.title')
                    ->label(__('ui.trip'))
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('payments_sum_amount')
                    ->label(__('ui.amount'))
                    ->sum('payments', 'amount')
                    ->money(fn ($record) => $record->tenant->currency ?? 'USD', locale: 'en')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('ui.date_uploaded'))
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('ui.start_date')),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('ui.end_date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label(__('ui.receipt'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (Receipt $record, \App\Services\ReceiptService $service) {
                        $content = $service->getPdfContent($record);
                        return response()->streamDownload(function () use ($content) {
                            echo $content;
                        }, "{$record->receipt_number}.pdf");
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageReceipts::route('/'),
        ];
    }
}
