<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        Plan::ensureDefaultPlansExist();

        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $locale = app()->getLocale();
        $isRtl = in_array($locale, ['he', 'ar'], true);

        return view('landing', [
            'plans' => $plans,
            'locale' => $locale,
            'isRtl' => $isRtl,
            'contactPhoneDisplay' => '050-231-1554',
            'contactPhoneIntl' => '972502311554',
        ]);
    }
}
