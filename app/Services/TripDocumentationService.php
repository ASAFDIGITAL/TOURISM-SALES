<?php

namespace App\Services;

use App\Models\Trip;
use Barryvdh\DomPDF\Facade\Pdf;

class TripDocumentationService
{
    public function getItineraryPdfContent(Trip $trip, ?string $lang = null): string
    {
        $customer = $trip->customer;
        $tenant = $trip->tenant;
        $overrideLang = in_array($lang, ['he', 'ar', 'en'], true) ? $lang : null;

        return Pdf::loadView('pdf.itinerary', compact('trip', 'customer', 'tenant', 'overrideLang'))
            ->setPaper('a4')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->output();
    }
}
