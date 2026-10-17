<?php

namespace App\Services\Exposure;

class ProviderResult
{
    public function __construct(public string $state, public array $items = [], public int $retryAfter = 0) {}
}
