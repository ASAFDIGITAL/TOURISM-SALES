<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionInvoiceResource\Pages;
use App\Models\SubscriptionInvoice;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionInvoiceResource extends Resource
{
    protected static ?string $model = SubscriptionInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('ui.subscription_invoices');
    }

    public static function getModelLabel(): string
    {
        return __('ui.subscription_invoice');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.subscription_invoices');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label(__('ui.agent'))
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('plan.name_he')
                    ->label(__('ui.plan'))
                    ->badge()
                    ->color('primary')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('provider')
                    ->label(__('ui.payment_provider'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'upay' ? 'UPay' : 'PayPlus')
                    ->color(fn (?string $state): string => $state === 'upay' ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('billing_cycle')
                    ->label(__('ui.billing_cycle_label'))
                    ->formatStateUsing(fn (string $state): string => $state === 'yearly' ? __('ui.billing_yearly') : __('ui.billing_monthly')),
                Tables\Columns\TextColumn::make('subtotal_amount')
                    ->label(__('ui.subtotal_before_vat'))
                    ->formatStateUsing(fn ($state, SubscriptionInvoice $record): string => $record->getCurrencySymbol() . number_format((float) $state, 2)),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label(__('ui.total_amount'))
                    ->weight('bold')
                    ->formatStateUsing(fn ($state, SubscriptionInvoice $record): string => $record->getCurrencySymbol() . number_format((float) $state, 2)),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('ui.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('ui.invoice_status_' . $state))
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('payplus_invoice_number')
                    ->label(__('ui.payplus_invoice_no'))
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label(__('ui.paid_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('ui.status'))
                    ->options([
                        'paid' => __('ui.invoice_status_paid'),
                        'pending' => __('ui.invoice_status_pending'),
                        'failed' => __('ui.invoice_status_failed'),
                        'refunded' => __('ui.invoice_status_refunded'),
                    ]),
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label(__('ui.agent'))
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('open_invoice_pdf')
                    ->label(__('ui.download_invoice_pdf'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->visible(fn (SubscriptionInvoice $record): bool => filled($record->payplus_invoice_url))
                    ->url(fn (SubscriptionInvoice $record): string => (string) $record->payplus_invoice_url, shouldOpenInNewTab: true),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionInvoices::route('/'),
        ];
    }
}
