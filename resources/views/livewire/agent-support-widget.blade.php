<div
    wire:poll.{{ $isOpen ? '6s' : '20s' }}="pollChat"
    x-data="{
        scrollToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.chatMessages;
                if (el) el.scrollTop = el.scrollHeight;
            });
        }
    }"
    x-on:support-chat-scroll.window="scrollToBottom()"
>
    {{-- Floating Support Button (Bottom-Left) --}}
    <button
        type="button"
        wire:click="toggleOpen"
        class="ts-support-fab"
        aria-label="{{ __('ui.support_floating_button') }}"
    >
        <span class="ts-support-fab-icon">
            @if ($isOpen)
                <svg width="18" height="18" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            @else
                <svg width="18" height="18" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                </svg>
            @endif
        </span>

        <span>{{ __('ui.support_floating_button') }}</span>

        @if ($this->unreadTotal > 0)
            <span class="ts-support-fab-badge">
                {{ $this->unreadTotal }}
            </span>
        @endif
    </button>

    {{-- Floating Support Chat Drawer --}}
    @if ($isOpen)
        <div class="ts-support-drawer">
            {{-- Header --}}
            <div class="ts-support-header">
                <div class="ts-support-header-main">
                    @if ($view !== 'list')
                        <button
                            type="button"
                            wire:click="showList"
                            class="ts-support-icon-btn"
                            title="{{ __('ui.back') }}"
                        >
                            <svg width="16" height="16" class="rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                            </svg>
                        </button>
                    @else
                        <div class="ts-support-header-avatar">
                            <svg width="20" height="20" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
                            </svg>
                        </div>
                    @endif

                    <div style="min-width: 0; flex: 1;">
                        <div class="ts-support-header-title">
                            @if ($view === 'create')
                                {{ __('ui.new_support_ticket') }}
                            @elseif ($view === 'chat' && $this->activeTicket)
                                #{{ $this->activeTicket->id }} · {{ $this->activeTicket->subject }}
                            @else
                                {{ __('ui.support_widget_title') }}
                            @endif
                        </div>
                        <div class="ts-support-header-sub">
                            @if ($view === 'chat' && $this->activeTicket)
                                {{ __('ui.ticket_status_' . $this->activeTicket->status) }}
                            @else
                                {{ __('ui.support_widget_subtitle') }}
                            @endif
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    wire:click="closeWidget"
                    class="ts-support-icon-btn"
                    title="Close"
                >
                    <svg width="16" height="16" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- BODY: TICKETS LIST --}}
            @if ($view === 'list')
                <div class="ts-support-subheader">
                    <span class="ts-support-subheader-label">
                        {{ __('ui.my_support_tickets') }} ({{ $this->tickets->count() }})
                    </span>
                    <button
                        type="button"
                        wire:click="showCreateForm"
                        class="ts-support-btn-primary"
                    >
                        <span>+</span>
                        <span>{{ __('ui.new_support_ticket') }}</span>
                    </button>
                </div>

                <div class="ts-support-list">
                    @forelse ($this->tickets as $ticket)
                        <button
                            type="button"
                            wire:click="openTicket({{ $ticket->id }})"
                            class="ts-support-ticket-item"
                        >
                            <div class="ts-support-ticket-top">
                                <div style="min-width: 0; flex: 1;">
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span class="ts-support-ticket-subject">
                                            {{ $ticket->subject }}
                                        </span>
                                        @if ($ticket->agent_unread_count > 0)
                                            <span class="ts-support-unread-pill">
                                                {{ $ticket->agent_unread_count }}
                                            </span>
                                        @endif
                                    </div>
                                    @if ($ticket->latestMessage)
                                        <div class="ts-support-ticket-preview">
                                            {{ $ticket->latestMessage->isFromAdmin() ? __('ui.support_team_prefix') . ': ' : '' }}{{ \Illuminate\Support\Str::limit($ticket->latestMessage->body, 75) }}
                                        </div>
                                    @endif
                                </div>

                                <span class="ts-support-badge ts-badge-{{ $ticket->status }}">
                                    {{ __('ui.ticket_status_' . $ticket->status) }}
                                </span>
                            </div>

                            <div class="ts-support-ticket-meta">
                                <span>#{{ $ticket->id }} · {{ __('ui.ticket_category_' . $ticket->category) }}</span>
                                <span>{{ optional($ticket->last_message_at ?? $ticket->created_at)->diffForHumans() }}</span>
                            </div>
                        </button>
                    @empty
                        <div class="ts-support-empty">
                            <div class="ts-support-empty-icon">
                                <svg width="26" height="26" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                                </svg>
                            </div>
                            <p class="ts-support-ticket-subject">{{ __('ui.no_tickets_yet') }}</p>
                            <p class="ts-support-ticket-preview" style="white-space: normal; margin-top: 6px;">{{ __('ui.no_tickets_yet_desc') }}</p>
                            <button
                                type="button"
                                wire:click="showCreateForm"
                                class="ts-support-btn-primary"
                                style="margin-top: 16px; padding: 9px 18px;"
                            >
                                {{ __('ui.new_support_ticket') }}
                            </button>
                        </div>
                    @endforelse
                </div>

            {{-- BODY: CREATE NEW TICKET --}}
            @elseif ($view === 'create')
                <form wire:submit="createTicket" class="ts-support-form">
                    <div>
                        <label class="ts-support-label">
                            {{ __('ui.ticket_subject') }} *
                        </label>
                        <input
                            type="text"
                            wire:model="subject"
                            placeholder="{{ __('ui.ticket_subject_placeholder') }}"
                            class="ts-support-input"
                            required
                        />
                        @error('subject') <div class="ts-support-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="ts-support-field-grid">
                        <div>
                            <label class="ts-support-label">
                                {{ __('ui.ticket_category') }}
                            </label>
                            <select wire:model="category" class="ts-support-select">
                                @foreach (\App\Models\SupportTicket::categoryOptions() as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="ts-support-label">
                                {{ __('ui.ticket_priority') }}
                            </label>
                            <select wire:model="priority" class="ts-support-select">
                                @foreach (\App\Models\SupportTicket::priorityOptions() as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div style="flex: 1; display: flex; flex-direction: column;">
                        <label class="ts-support-label">
                            {{ __('ui.ticket_message') }} *
                        </label>
                        <textarea
                            wire:model="initialMessage"
                            rows="5"
                            placeholder="{{ __('ui.ticket_message_placeholder') }}"
                            class="ts-support-textarea"
                            style="flex: 1; resize: none;"
                            required
                        ></textarea>
                        @error('initialMessage') <div class="ts-support-error">{{ $message }}</div> @enderror
                    </div>

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="ts-support-btn-primary ts-support-btn-full"
                    >
                        <span wire:loading.remove wire:target="createTicket">{{ __('ui.submit_support_ticket') }}</span>
                        <span wire:loading wire:target="createTicket">{{ __('ui.sending') }}...</span>
                    </button>
                </form>

            {{-- BODY: LIVE CONVERSATION --}}
            @elseif ($view === 'chat' && $this->activeTicket)
                <div
                    x-ref="chatMessages"
                    x-init="scrollToBottom()"
                    class="ts-support-messages"
                >
                    @foreach ($this->activeTicket->messages as $msg)
                        @php
                            $isAdmin = $msg->isFromAdmin();
                        @endphp
                        <div class="ts-msg-row {{ $isAdmin ? 'ts-msg-row-end' : 'ts-msg-row-start' }}">
                            <div class="ts-msg-bubble {{ $isAdmin ? 'ts-msg-bubble-admin' : 'ts-msg-bubble-agent' }}">
                                <div class="ts-msg-meta {{ $isAdmin ? 'ts-msg-meta-admin' : 'ts-msg-meta-agent' }}">
                                    <span>
                                        {{ $isAdmin ? __('ui.support_team_prefix') : ($msg->user?->name ?? __('ui.you')) }}
                                    </span>
                                    <span class="ts-msg-time">
                                        {{ $msg->created_at->format('H:i') }}
                                    </span>
                                </div>
                                <div>{{ $msg->body }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <form wire:submit="sendReply" class="ts-support-composer">
                    @if ($this->activeTicket->isClosed())
                        <p style="margin: 0 0 8px 0; text-align: center; font-size: 0.72rem; color: #64748b;">
                            {{ __('ui.ticket_closed_reopen_hint') }}
                        </p>
                    @endif
                    <div class="ts-support-composer-row">
                        <textarea
                            wire:model="replyBody"
                            rows="2"
                            placeholder="{{ __('ui.write_reply_placeholder') }}"
                            x-on:keydown.enter.prevent="if (!$event.shiftKey) { $wire.sendReply(); }"
                            class="ts-support-textarea"
                            style="flex: 1; resize: none; padding: 8px 11px;"
                            required
                        ></textarea>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="ts-support-send-btn"
                            title="{{ __('ui.send') }}"
                        >
                            <svg width="18" height="18" class="rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                            </svg>
                        </button>
                    </div>
                    @error('replyBody') <div class="ts-support-error">{{ $message }}</div> @enderror
                </form>
            @endif
        </div>
    @endif
</div>
