<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();
            $lang = $user->language;

            // Fallback to tenant language if user language is not set
            if (!$lang && $user->tenant) {
                $lang = $user->tenant->language;
            }

            if ($lang) {
                app()->setLocale($lang);
                
                if ($lang === 'ar') {
                    // Force Western Arabic numerals (0-9)
                    setlocale(LC_TIME, 'en_US.UTF-8');
                    setlocale(LC_NUMERIC, 'en_US.UTF-8');
                    setlocale(LC_MONETARY, 'en_US.UTF-8');
                }
            }
        }

        return $next($request);
    }
}
