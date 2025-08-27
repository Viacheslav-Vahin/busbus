<?php

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Cache;

if (! function_exists('company')) {
    function company(): ?CompanyProfile
    {
        return Cache::remember('company_profile', 600, fn () => CompanyProfile::first());
    }
}
