<?php

namespace App\Jobs;

use App\Models\IndexabilityAudit;
use App\Services\IndexabilityAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunIndexabilityAudit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public int $auditId) {}

    public function handle(IndexabilityAuditService $service): void
    {
        $audit = IndexabilityAudit::find($this->auditId);
        if (! $audit) return;

        try {
            $service->run($audit);
        } catch (Throwable $exception) {
            $audit->update(['status' => 'failed', 'completed_at' => now(), 'error' => 'Auditul nu a putut fi finalizat. Verifică logurile aplicației.']);
            report($exception);
        }
    }
}
