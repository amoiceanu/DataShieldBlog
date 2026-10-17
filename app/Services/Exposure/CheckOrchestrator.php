<?php

namespace App\Services\Exposure;

use App\Models\ExposureReport;

class CheckOrchestrator
{
    public function __construct(private BreachSearch $breaches, private PublicSearch $mentions) {}

    public function run(ExposureReport $report, array $settings): ProviderResult
    {
        $result = $report->module === 'breaches'
         ? $this->breaches->search($report->subject['value'])
         : $this->mentions->search($report->subject['value'], $report->subject['location'] ?? '', $settings['max_results']);
        if (count($result->items) > $settings['max_results']) {
            $result->items = array_slice($result->items, 0, $settings['max_results']);
            $result->state = 'incomplete';
        }

        return $result;
    }
}
