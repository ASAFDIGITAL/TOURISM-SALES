<?php

namespace App\Filament\Agent\Resources\TripResource\RelationManagers;

use App\Services\ReceiptService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('ui.payments');
    }

    public static function getModelLabel(): string
    {
        return __('ui.payment');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('amount')
                    ->label(__('ui.amount'))
                    ->numeric()
                    ->prefix(fn () => auth()->user()->tenant->currency === 'ILS' ? '₪' : (auth()->user()->tenant->currency === 'EUR' ? '€' : '$'))
                    ->required(),
                Forms\Components\Select::make('method')
                    ->label(__('ui.method'))
                    ->options([
                        'credit' => __('ui.credit'),
                        'cash' => __('ui.cash'),
                        'transfer' => __('ui.transfer'),
                        'check' => __('ui.check'),
                    ])
                    ->required(),
                Forms\Components\DatePicker::make('paid_at')
                    ->label(__('ui.paid_at'))
                    ->default(now())
                    ->required(),
                Forms\Components\TextInput::make('cheque_number')
                    ->label(__('ui.cheque_number'))
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('ui.amount'))
                    ->money(fn ($record) => $record->tenant->currency ?? 'USD', locale: 'en')
                    ->sortable(),
                Tables\Columns\TextColumn::make('method')
                    ->label(__('ui.method'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("ui.{$state}")),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label(__('ui.paid_at'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('receipt.receipt_number')
                    ->label(__('ui.receipt_number') ?? 'Receipt #')
                    ->default('-'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = auth()->user()->tenant_id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download_receipt')
                    ->label(__('ui.receipt'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn ($record) => $record->receipt_id !== null)
                    ->action(function ($record, ReceiptService $service) {
                        $receipt = $record->receipt;
                        $content = $service->getPdfContent($receipt);
                        
                        return response()->streamDownload(function () use ($content) {
                            echo $content;
                        }, "{$receipt->receipt_number}.pdf");
                    }),
                Tables\Actions\Action::make('generate_receipt')
                    ->label(__('ui.receipt'))
                    ->icon('heroicon-o-document-text')
                    ->hidden(fn ($record) => $record->receipt_id !== null)
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading(__('ui.generate_receipt_confirmation') ?? 'הנפקת קבלה חדשה')
                    ->action(function ($record, ReceiptService $service) {
                        $receipt = $service->generate($record);
                        $content = $service->getPdfContent($receipt);
                        
                        return response()->streamDownload(function () use ($content) {
                            echo $content;
                        }, "{$receipt->receipt_number}.pdf");
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('generate_consolidated_receipt')
                        ->label(__('ui.receipt') . ' (מרוכזת)')
                        ->icon('heroicon-o-document-duplicate')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Support\Collection $records, ReceiptService $service) {
                            // Only generate for payments without a receipt
                            $paymentsWithoutReceipt = $records->whereNull('receipt_id');
                            
                            if ($paymentsWithoutReceipt->isEmpty()) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('כל התשלומים שנבחרו כבר משויכים לקבלות.')
                                    ->send();
                                return;
                            }

                            $receipt = $service->generate($paymentsWithoutReceipt);
                            $content = $service->getPdfContent($receipt);
                            
                            return response()->streamDownload(function () use ($content) {
                                echo $content;
                            }, "{$receipt->receipt_number}.pdf");
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
