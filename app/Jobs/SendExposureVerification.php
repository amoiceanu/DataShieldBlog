<?php

namespace App\Jobs;

use App\Models\ExposureChallenge;
use App\Models\ExposureReport;
use App\Services\Exposure\Settings;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendExposureVerification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public string $challengeId, public string $token) {}

    public function handle(): void
    {
        $c = ExposureChallenge::find($this->challengeId);
        if (! $c || $c->used_at || $c->expires_at->isPast()) {
            return;
        }
        $r = ExposureReport::find($c->report_id);
        if (! $r || $r->expires_at->isPast()) {
            return;
        }
        if (! app(Settings::class)->all()[$r->module] || ! app(Settings::class)->all()['enabled'] || ! app(Settings::class)->mailReady()) {
            $r->update(['state' => 'unavailable']);

            return;
        }
        try {
            Mail::send('mail.exposure', ['link' => route('exposure.verify', ['token' => $this->token])], fn ($m) => $m->to($r->subject['value'])->subject('Confirmă verificarea datelor personale'));
        } catch (\Throwable) {
            $r->update(['state' => 'unavailable']);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $c = ExposureChallenge::find($this->challengeId);
        if ($c) {
            ExposureReport::whereKey($c->report_id)->update(['state' => 'unavailable']);
        }
    }
}
