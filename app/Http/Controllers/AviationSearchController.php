<?php

namespace App\Http\Controllers;

use App\Services\AviationDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AviationSearchController extends Controller
{
    public function airports(Request $request, AviationDirectoryService $service): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $results = $service->searchAirports($query, 25);

        return response()->json([
            'data' => array_values($results),
        ]);
    }

    public function airlines(Request $request, AviationDirectoryService $service): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $results = $service->searchAirlines($query, 25);

        return response()->json([
            'data' => array_values($results),
        ]);
    }

    public function destinations(Request $request, AviationDirectoryService $service): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $results = $service->searchDestinations($query, 25);

        return response()->json([
            'data' => array_values($results),
        ]);
    }
}
