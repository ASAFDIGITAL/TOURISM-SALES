<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AviationDirectoryService
{
    /**
     * Search airports by IATA code, Hebrew/Arabic/English city, airport name, or country.
     * Combines local multilingual catalog + cached live public Aviation API results.
     *
     * @return array<string, string> [value => displayLabel]
     */
    public function searchAirports(?string $search = '', int $limit = 25): array
    {
        $query = trim((string) $search);
        $locale = app()->getLocale();
        $airports = (array) config('aviation.airports', []);

        $results = [];

        foreach ($airports as $airport) {
            if ($query === '' || $this->matchesAirport($airport, $query)) {
                $label = $this->formatAirportLabel($airport, $locale);
                $results[$label] = $label;
                if (count($results) >= $limit) {
                    break;
                }
            }
        }

        // Enrich with live Airports API when searching and fewer than $limit local matches
        if (mb_strlen($query) >= 2 && count($results) < $limit) {
            foreach ($this->fetchRemoteAirports($query) as $remote) {
                $label = $this->formatAirportLabel($remote, $locale);
                $codePrefix = strtoupper((string) ($remote['code'] ?? '')) . ' —';
                $alreadyExists = false;
                foreach (array_keys($results) as $existing) {
                    if ($codePrefix !== ' —' && str_starts_with($existing, $codePrefix)) {
                        $alreadyExists = true;
                        break;
                    }
                }
                if (! $alreadyExists) {
                    $results[$label] = $label;
                    if (count($results) >= $limit) {
                        break;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Search airlines by IATA code, Hebrew or English name.
     *
     * @return array<string, string> [value => displayLabel]
     */
    public function searchAirlines(?string $search = '', int $limit = 25): array
    {
        $query = trim((string) $search);
        $locale = app()->getLocale();
        $airlines = (array) config('aviation.airlines', []);

        $results = [];

        foreach ($airlines as $airline) {
            if ($query === '' || $this->matchesAirline($airline, $query)) {
                $label = $this->formatAirlineLabel($airline, $locale);
                $results[$label] = $label;
                if (count($results) >= $limit) {
                    break;
                }
            }
        }

        // Optional AirLabs live airlines lookup if key configured
        if (mb_strlen($query) >= 2 && count($results) < $limit && filled(config('aviation.airlabs_api_key'))) {
            foreach ($this->fetchAirLabsAirlines($query) as $remote) {
                $label = $this->formatAirlineLabel($remote, $locale);
                $results[$label] = $label;
                if (count($results) >= $limit) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Search travel destinations (cities/countries) in Hebrew, Arabic, English, or IATA code.
     * Combines curated local catalog + cached live Travelpayouts Cities API.
     *
     * @return array<string, string> [value => displayLabel]
     */
    public function searchDestinations(?string $search = '', int $limit = 25): array
    {
        $query = trim((string) $search);
        $locale = app()->getLocale();
        $airports = (array) config('aviation.airports', []);

        $results = [];

        foreach ($airports as $airport) {
            if ($query === '' || $this->matchesAirport($airport, $query)) {
                $city = match ($locale) {
                    'he' => (string) ($airport['city_he'] ?? $airport['city'] ?? ''),
                    'ar' => (string) ($airport['city_ar'] ?? $airport['city'] ?? ''),
                    default => (string) ($airport['city'] ?? ''),
                };
                // Strip parenthetical airport qualifier like "תל אביב (בן גוריון)" -> "תל אביב"
                $cleanCity = trim((string) preg_replace('/\s*\([^)]*\)/u', '', $city));
                if ($cleanCity === '') {
                    continue;
                }
                $country = (string) ($airport['country'] ?? '');
                $label = $country !== '' ? "{$cleanCity}, {$country}" : $cleanCity;
                $results[$label] = $label;

                if (count($results) >= $limit) {
                    break;
                }
            }
        }

        if (mb_strlen($query) >= 2 && count($results) < $limit) {
            foreach ($this->fetchRemoteCities($query) as $remote) {
                $city = (string) ($remote['city'] ?? '');
                $country = (string) ($remote['country'] ?? '');
                if ($city === '') {
                    continue;
                }
                $label = $country !== '' ? "{$city}, {$country}" : $city;
                $results[$label] = $label;
                if (count($results) >= $limit) {
                    break;
                }
            }
        }

        return $results;
    }

    public function extractAirlineCode(?string $airlineValue): ?string
    {
        if (! $airlineValue) {
            return null;
        }

        if (preg_match('/^([A-Z0-9]{2})\s*[—-]/u', trim($airlineValue), $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $airport
     */
    public function formatAirportLabel(array $airport, string $locale = 'he'): string
    {
        $code = strtoupper((string) ($airport['code'] ?? ''));
        $city = match ($locale) {
            'he' => (string) ($airport['city_he'] ?? $airport['city'] ?? ''),
            'ar' => (string) ($airport['city_ar'] ?? $airport['city'] ?? ''),
            default => (string) ($airport['city'] ?? ''),
        };

        $name = (string) ($airport['name'] ?? '');
        $country = (string) ($airport['country'] ?? '');

        if ($city !== '' && $country !== '') {
            return "{$code} — {$city}, {$country}";
        }

        return trim("{$code} — " . ($city ?: $name));
    }

    /**
     * @param array<string, mixed> $airline
     */
    public function formatAirlineLabel(array $airline, string $locale = 'he'): string
    {
        $code = strtoupper((string) ($airline['code'] ?? ''));
        $name = (string) ($airline['name'] ?? '');
        $nameHe = (string) ($airline['name_he'] ?? '');

        if ($locale === 'he' && $nameHe !== '' && mb_strtolower($nameHe) !== mb_strtolower($name)) {
            return "{$code} — {$nameHe} ({$name})";
        }

        return "{$code} — {$name}";
    }

    /**
     * @param array<string, mixed> $airport
     */
    protected function matchesAirport(array $airport, string $query): bool
    {
        $needle = mb_strtolower($query);
        $haystack = mb_strtolower(implode(' ', [
            $airport['code'] ?? '',
            $airport['name'] ?? '',
            $airport['city'] ?? '',
            $airport['city_he'] ?? '',
            $airport['city_ar'] ?? '',
            $airport['country'] ?? '',
        ]));

        return str_contains($haystack, $needle);
    }

    /**
     * @param array<string, mixed> $airline
     */
    protected function matchesAirline(array $airline, string $query): bool
    {
        $needle = mb_strtolower($query);
        $haystack = mb_strtolower(implode(' ', [
            $airline['code'] ?? '',
            $airline['name'] ?? '',
            $airline['name_he'] ?? '',
        ]));

        return str_contains($haystack, $needle);
    }

    /**
     * Fetch live airports from Travelpayouts Places API (free, no key required) with 24h caching.
     *
     * @return array<int, array<string, string>>
     */
    protected function fetchRemoteAirports(string $query): array
    {
        $apiUrl = (string) config('aviation.airports_api_url', '');
        if ($apiUrl === '') {
            return [];
        }

        $cacheKey = 'aviation.airports.remote.' . md5(mb_strtolower($query));

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($apiUrl, $query) {
            try {
                $response = Http::timeout(3)
                    ->acceptJson()
                    ->get($apiUrl, [
                        'term' => $query,
                        'locale' => 'en',
                        'types' => ['airport'],
                    ]);

                if (! $response->successful()) {
                    return [];
                }

                $items = $response->json();
                if (! is_array($items)) {
                    return [];
                }

                $mapped = [];
                foreach ($items as $item) {
                    if (! is_array($item) || empty($item['code'])) {
                        continue;
                    }
                    $mapped[] = [
                        'code' => strtoupper((string) $item['code']),
                        'name' => (string) ($item['name'] ?? ''),
                        'city' => (string) ($item['city_name'] ?? $item['name'] ?? ''),
                        'country' => (string) ($item['country_name'] ?? ''),
                    ];
                }

                return $mapped;
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /**
     * Fetch live cities/destinations from Travelpayouts Places API with 24h caching.
     *
     * @return array<int, array<string, string>>
     */
    protected function fetchRemoteCities(string $query): array
    {
        $apiUrl = (string) config('aviation.airports_api_url', '');
        if ($apiUrl === '') {
            return [];
        }

        $cacheKey = 'aviation.cities.remote.' . md5(mb_strtolower($query));

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($apiUrl, $query) {
            try {
                $response = Http::timeout(3)
                    ->acceptJson()
                    ->get($apiUrl, [
                        'term' => $query,
                        'locale' => 'en',
                        'types' => ['city'],
                    ]);

                if (! $response->successful()) {
                    return [];
                }

                $items = $response->json();
                if (! is_array($items)) {
                    return [];
                }

                $mapped = [];
                foreach ($items as $item) {
                    if (! is_array($item) || empty($item['name'])) {
                        continue;
                    }
                    $mapped[] = [
                        'city' => (string) $item['name'],
                        'country' => (string) ($item['country_name'] ?? ''),
                    ];
                }

                return $mapped;
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function fetchAirLabsAirlines(string $query): array
    {
        $apiKey = (string) config('aviation.airlabs_api_key', '');
        if ($apiKey === '') {
            return [];
        }

        $cacheKey = 'aviation.airlabs.airlines.' . md5(mb_strtolower($query));

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($apiKey, $query) {
            try {
                $response = Http::timeout(3)
                    ->acceptJson()
                    ->get('https://airlabs.co/api/v9/suggest', [
                        'q' => $query,
                        'api_key' => $apiKey,
                    ]);

                if (! $response->successful()) {
                    return [];
                }

                $airlines = data_get($response->json(), 'response.airlines', []);
                if (! is_array($airlines)) {
                    return [];
                }

                $mapped = [];
                foreach ($airlines as $item) {
                    if (! is_array($item) || empty($item['iata_code']) || empty($item['name'])) {
                        continue;
                    }
                    $mapped[] = [
                        'code' => strtoupper((string) $item['iata_code']),
                        'name' => (string) $item['name'],
                    ];
                }

                return $mapped;
            } catch (\Throwable) {
                return [];
            }
        });
    }
}
