<?php

namespace App\Console\Commands;

use App\Models\Trip;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;

class CheckUpcomingTrips extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-upcoming-trips';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for trips starting in 3 days and notify agents';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Define the target date: 3 days from now
        $targetDate = Carbon::now()->addDays(3)->format('Y-m-d');
        
        $trips = Trip::whereDate('start_date', $targetDate)
            ->whereIn('status', ['confirmed', 'active'])
            ->get();

        $this->info("Found " . $trips->count() . " trips starting on {$targetDate}");

        foreach ($trips as $trip) {
            $agents = User::where('tenant_id', $trip->tenant_id)->get();

            foreach ($agents as $agent) {
                // Set locale for the agent
                $originalLocale = App::getLocale();
                if ($agent->language) {
                    App::setLocale($agent->language);
                } elseif ($agent->tenant && $agent->tenant->language) {
                    App::setLocale($agent->tenant->language);
                }

                $customerName = $trip->customer ? $trip->customer->name : 'Unknown Customer';
                
                Notification::make()
                    ->title(__('ui.upcoming_trip', ['destination' => $trip->destination]))
                    ->icon('heroicon-o-calendar')
                    ->body(__('ui.trip_starts_in_days', [
                        'customer' => $customerName, 
                        'days' => 3, 
                        'date' => $trip->start_date->format('d/m/Y')
                    ]))
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('view')
                            ->label(__('ui.view_trip'))
                            ->url(\App\Filament\Agent\Resources\TripResource::getUrl('edit', ['record' => $trip->id], panel: 'agent'), shouldOpenInNewTab: true),
                    ])
                    ->sendToDatabase($agent);
                    
                $this->info("Notification sent to agent: {$agent->email} for trip ID: {$trip->id}");
                
                // Restore locale
                App::setLocale($originalLocale);
            }
        }
        
        // Also check for trips starting tomorrow
        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');
        $tripsTomorrow = Trip::whereDate('start_date', $tomorrow)
            ->whereIn('status', ['confirmed', 'active'])
            ->get();
            
        foreach ($tripsTomorrow as $trip) {
             $agents = User::where('tenant_id', $trip->tenant_id)->get();

            foreach ($agents as $agent) {
                // Set locale for the agent
                $originalLocale = App::getLocale();
                if ($agent->language) {
                    App::setLocale($agent->language);
                } elseif ($agent->tenant && $agent->tenant->language) {
                    App::setLocale($agent->tenant->language);
                }

                $customerName = $trip->customer ? $trip->customer->name : 'Unknown Customer';
                
                Notification::make()
                    ->title(__('ui.upcoming_trip', ['destination' => $trip->destination]))
                    ->warning() 
                    ->body(__('ui.trip_starts_tomorrow', ['customer' => $customerName]))
                    ->actions([
                         \Filament\Notifications\Actions\Action::make('view')
                            ->label(__('ui.view_trip'))
                            ->url(\App\Filament\Agent\Resources\TripResource::getUrl('edit', ['record' => $trip->id], panel: 'agent'), shouldOpenInNewTab: true),
                    ])
                    ->sendToDatabase($agent);

                // Restore locale
                App::setLocale($originalLocale);
            }
        }
        
        $this->info('Done.');
    }
}
