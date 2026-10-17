<?php

namespace App\Jobs;

use App\Models\ExposureReport;
use App\Services\Exposure\CheckOrchestrator;
use App\Services\Exposure\Settings;
use App\Services\Exposure\Usage;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class RunExposureCheck implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public string $reportId) {}

    public function handle(Settings $settings, Usage $usage, CheckOrchestrator $orchestrator): void
    {
        $r = ExposureReport::find($this->reportId);
        if (! $r || $r->expires_at->isPast() || ! in_array($r->state, ['queued', 'processing'])) {
            return;
        }
        $s = $settings->all();
        if (! $s['enabled'] || ! $s[$r->module] || ! $settings->configured($r->module)) {
            $r->update(['state' => 'unavailable']);

            return;
        }
        // Shared atomic pacing across workers; retries are bounded by the queue's tries setting.
        if (! Cache::add('exposure-provider-'.$r->module, true, $s['provider_interval'])) {
            if ($this->attempts() < 3) {
                $this->release($s['provider_interval']);

                return;
            }
            $r->update(['state' => 'quota']);

            return;
        }
        if (! $usage->reserve($r->module, $s['daily_limit'])) {
            $r->update(['state' => 'quota']);

            return;
        }
        $r->update(['state' => 'processing']);
        try {
            $result = $orchestrator->run($r, $s);
            if ($result->retryAfter > 0 && $this->attempts() < 3) {
                $r->update(['state' => 'queued']);
                $this->release($result->retryAfter);

                return;
            }
            $r->refresh();
            if ($r->expires_at->isPast()) {
                $r->delete();

                return;
            }
            $r->update(['state' => $result->state, 'results' => $result->items, 'checked_at' => now()]);
            $usage->count($r->module, $result->state === 'complete' ? 'completed' : 'errors');
        } catch (\Throwable) {
            if ($r->exists) {
                $r->update(['state' => 'incomplete']);
            }
            // Never persist upstream exception messages, request URLs or response bodies.
        }
    }

    public function failed(?\Throwable $exception): void
    {
        ExposureReport::whereKey($this->reportId)->update(['state' => 'incomplete']);
    }
}
