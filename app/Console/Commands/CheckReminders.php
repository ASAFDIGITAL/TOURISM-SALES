<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;

class CheckReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for scheduled reminders and notify agents';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        
        $reminders = Reminder::where('status', 'pending')
            ->where('scheduled_at', '<=', $now)
            ->get();

        $this->info("Found " . $reminders->count() . " pending reminders to process.");

        foreach ($reminders as $reminder) {
            $agents = User::where('tenant_id', $reminder->tenant_id)->get();

            foreach ($agents as $agent) {
                // Set locale for the agent
                $originalLocale = App::getLocale();
                if ($agent->language) {
                    App::setLocale($agent->language);
                } elseif ($agent->tenant && $agent->tenant->language) {
                    App::setLocale($agent->tenant->language);
                }

                $destination = $reminder->trip ? $reminder->trip->destination : 'Unknown';
                
                Notification::make()
                    ->title(__('ui.trip_reminder', ['destination' => $destination]))
                    ->icon('heroicon-o-bell')
                    ->body(__('ui.reminder_message', ['message' => $reminder->message]))
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('view')
                            ->label(__('ui.view_trip'))
                            ->url($reminder->trip ? \App\Filament\Agent\Resources\TripResource::getUrl('edit', ['record' => $reminder->trip_id], panel: 'agent') : '#', shouldOpenInNewTab: true),
                    ])
                    ->sendToDatabase($agent);
                    
                // Restore locale
                App::setLocale($originalLocale);
            }

            // Update reminder status
            $reminder->update(['status' => 'sent']);
            $this->info("Processed reminder ID: {$reminder->id}");
        }
        
        $this->info('Done.');
    }
}
