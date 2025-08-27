<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class Ga4
{
    public static function send(string $clientId, string $eventName, array $params = []): void
    {
        if (!config('services.ga4.enabled')) return;

        $mid = config('services.ga4.measurement_id');
        $sec = config('services.ga4.api_secret');
        if (!$mid || !$sec) return;

        Http::timeout(5)->post(
            "https://www.google-analytics.com/mp/collect?measurement_id={$mid}&api_secret={$sec}",
            [
                'client_id' => $clientId ?: (string) request()->ip(), // можна підмінити
                'events'    => [[ 'name' => $eventName, 'params' => $params ]],
            ]
        )->throwIfServerError();
    }
}
