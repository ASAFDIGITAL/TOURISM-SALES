<?php

namespace App\Livewire;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AgentSupportWidget extends Component
{
    public bool $isOpen = false;

    /** 'list' | 'create' | 'chat' */
    public string $view = 'list';

    public ?int $activeTicketId = null;

    // New ticket form fields
    public string $subject = '';
    public string $category = SupportTicket::CATEGORY_GENERAL;
    public string $priority = SupportTicket::PRIORITY_NORMAL;
    public string $initialMessage = '';

    // Chat reply field
    public string $replyBody = '';

    public function toggleOpen(): void
    {
        $this->isOpen = ! $this->isOpen;

        if ($this->isOpen) {
            if ($this->view === 'chat' && $this->activeTicketId) {
                $this->markActiveTicketRead();
            } elseif ($this->tickets->isEmpty()) {
                $this->view = 'create';
            } else {
                $this->view = 'list';
            }
        }
    }

    public function closeWidget(): void
    {
        $this->isOpen = false;
    }

    public function showList(): void
    {
        $this->view = 'list';
        $this->activeTicketId = null;
        $this->resetValidation();
    }

    public function showCreateForm(): void
    {
        $this->view = 'create';
        $this->activeTicketId = null;
        $this->resetValidation();
    }

    public function openTicket(int $ticketId): void
    {
        $tenantId = $this->getTenantId();
        if (! $tenantId) {
            return;
        }

        $ticket = SupportTicket::query()
            ->where('tenant_id', $tenantId)
            ->find($ticketId);

        if (! $ticket) {
            return;
        }

        $this->isOpen = true;
        $this->activeTicketId = $ticket->id;
        $this->view = 'chat';
        $this->replyBody = '';
        $this->resetValidation();

        app(SupportTicketService::class)->markReadForAgent($ticket);
        unset($this->tickets, $this->activeTicket, $this->unreadTotal);

        $this->dispatch('support-chat-scroll');
    }

    public function createTicket(): void
    {
        $user = Auth::user();
        $tenant = $user?->getActiveTenant();

        if (! $user || ! $tenant) {
            return;
        }

        $this->validate([
            'subject' => ['required', 'string', 'min:3', 'max:180'],
            'category' => ['required', 'in:technical,billing,feature,general'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'initialMessage' => ['required', 'string', 'min:3', 'max:4000'],
        ]);

        $ticket = app(SupportTicketService::class)->createTicket(
            tenant: $tenant,
            creator: $user,
            subject: $this->subject,
            body: $this->initialMessage,
            category: $this->category,
            priority: $this->priority,
            senderType: SupportMessage::SENDER_AGENT,
        );

        $this->subject = '';
        $this->category = SupportTicket::CATEGORY_GENERAL;
        $this->priority = SupportTicket::PRIORITY_NORMAL;
        $this->initialMessage = '';

        $this->isOpen = true;
        $this->activeTicketId = $ticket->id;
        $this->view = 'chat';

        unset($this->tickets, $this->activeTicket, $this->unreadTotal);

        $this->dispatch('support-chat-scroll');
    }

    public function sendReply(): void
    {
        $user = Auth::user();
        $tenantId = $this->getTenantId();

        if (! $user || ! $tenantId || ! $this->activeTicketId) {
            return;
        }

        $this->validate([
            'replyBody' => ['required', 'string', 'min:1', 'max:4000'],
        ]);

        $ticket = SupportTicket::query()
            ->where('tenant_id', $tenantId)
            ->find($this->activeTicketId);

        if (! $ticket) {
            return;
        }

        app(SupportTicketService::class)->addReply(
            ticket: $ticket,
            sender: $user,
            body: $this->replyBody,
            senderType: SupportMessage::SENDER_AGENT,
        );

        $this->replyBody = '';
        unset($this->tickets, $this->activeTicket, $this->unreadTotal);

        $this->dispatch('support-chat-scroll');
    }

    public function pollChat(): void
    {
        if ($this->isOpen && $this->view === 'chat' && $this->activeTicketId) {
            $this->markActiveTicketRead();
        }

        unset($this->tickets, $this->activeTicket, $this->unreadTotal);
    }

    #[Computed]
    public function tickets(): Collection
    {
        $tenantId = $this->getTenantId();
        if (! $tenantId) {
            return collect();
        }

        return SupportTicket::query()
            ->where('tenant_id', $tenantId)
            ->with('latestMessage')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get();
    }

    #[Computed]
    public function activeTicket(): ?SupportTicket
    {
        $tenantId = $this->getTenantId();
        if (! $tenantId || ! $this->activeTicketId) {
            return null;
        }

        return SupportTicket::query()
            ->where('tenant_id', $tenantId)
            ->with(['messages.user'])
            ->find($this->activeTicketId);
    }

    #[Computed]
    public function unreadTotal(): int
    {
        $tenantId = $this->getTenantId();
        if (! $tenantId) {
            return 0;
        }

        return (int) SupportTicket::query()
            ->where('tenant_id', $tenantId)
            ->sum('agent_unread_count');
    }

    protected function markActiveTicketRead(): void
    {
        $ticket = $this->activeTicket;
        if ($ticket && $ticket->agent_unread_count > 0) {
            app(SupportTicketService::class)->markReadForAgent($ticket);
        }
    }

    protected function getTenantId(): ?int
    {
        return Auth::user()?->getActiveTenantId();
    }

    public function render()
    {
        return view('livewire.agent-support-widget');
    }
}
