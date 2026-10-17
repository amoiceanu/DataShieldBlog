<?php

namespace App\Services\Exposure;

use App\Jobs\SendExposureVerification;
use App\Models\ExposureChallenge;
use App\Models\ExposureReport;

class EmailVerification
{
    public function send(ExposureReport $report): void
    {
        $token = bin2hex(random_bytes(32));
        $challenge = ExposureChallenge::create(['report_id' => $report->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(15)]);
        SendExposureVerification::dispatch($challenge->id, $token)->onConnection('database')->onQueue(config('exposure.queue'));
    }
}
