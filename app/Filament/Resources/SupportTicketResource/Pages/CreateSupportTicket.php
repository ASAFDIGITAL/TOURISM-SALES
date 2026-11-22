<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use App\Models\SupportMessage;
use App\Models\Tenant;
use App\Services\SupportTicketService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateSupportTicket extends CreateRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $tenant = Tenant::findOrFail($data['tenant_id']);

        return app(SupportTicketService::class)->createTicket(
            tenant: $tenant,
            creator: Auth::user(),
            subject: (string) $data['subject'],
            body: (string) $data['initial_message'],
            category: (string) ($data['category'] ?? 'general'),
            priority: (string) ($data['priority'] ?? 'normal'),
            senderType: SupportMessage::SENDER_ADMIN,
        );
    }

    protected function getRedirectUrl(): string
    {
        return SupportTicketResource::getUrl('view', ['record' => $this->record]);
    }
}
