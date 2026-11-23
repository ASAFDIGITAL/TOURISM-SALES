<?php

namespace App\Filament\Resources\SubscriptionInvoiceResource\Pages;

use App\Filament\Resources\SubscriptionInvoiceResource;
use App\Services\PayPlusService;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionInvoices extends ListRecords
{
    protected static string $resource = SubscriptionInvoiceResource::class;

    public function getSubheading(): ?string
    {
        $payPlus = app(PayPlusService::class);

        if ($payPlus->isConfigured()) {
            return __('ui.payplus_status_connected', ['env' => strtoupper((string) config('payplus.env'))]);
        }

        return __('ui.payplus_status_waiting_keys');
    }
}
