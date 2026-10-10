<?php

declare(strict_types=1);

$reportPath = $argv[1] ?? null;
$minimum = isset($argv[2]) ? (float) $argv[2] : 70.0;

if ($reportPath === null || ! is_file($reportPath)) {
    fwrite(STDERR, "Usage: php scripts/check-coverage.php <clover-report.xml> [minimum-percent]\n");
    exit(2);
}

$report = simplexml_load_file($reportPath);
$metrics = $report === false ? null : ($report->project->metrics ?? null);

if ($metrics === null) {
    fwrite(STDERR, "Could not read aggregate metrics from the Clover coverage report.\n");
    exit(2);
}

$statements = (int) $metrics['statements'];
$coveredStatements = (int) $metrics['coveredstatements'];
$coverage = $statements === 0 ? 0.0 : ($coveredStatements / $statements) * 100;

printf("Line coverage: %.2f%% (%d/%d statements); minimum: %.2f%%\n", $coverage, $coveredStatements, $statements, $minimum);

exit($coverage < $minimum ? 1 : 0);
