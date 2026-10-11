<?php

namespace App\Console\Commands;

use App\Models\IndexabilityAudit;
use App\Services\IndexabilityAuditService;
use Illuminate\Console\Command;

class CheckIndexability extends Command
{
    protected $signature = 'seo:check-indexability';

    protected $description = 'Auditează indexabilitatea URL-urilor publice și sitemap-ul live';

    public function handle(IndexabilityAuditService $service): int
    {
        $audit = $service->run(IndexabilityAudit::create(['status' => 'running']));
        $this->table(['Tip', 'Titlu', 'HTTP', 'Canonical', 'Sitemap', 'Descoperit', 'Stare locală', 'Probleme'], $audit->items->map(fn ($item) => [
            $item->type,
            $item->title,
            $item->http_status ?? '—',
            $item->canonical_url ?? '—',
            $item->sitemap_included ? 'Da' : 'Nu',
            $item->discovered ? 'Da' : 'Nu',
            $item->local_status,
            implode(', ', $item->problems ?? []),
        ])->all());
        $this->line('Summary: '.json_encode($audit->summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return ($audit->summary['technical_problems'] ?? 1) === 0 ? self::SUCCESS : self::FAILURE;
    }
}
