<?php

namespace App\Services;

use App\Models\DailyTip;

class DailyTipService
{
    public function today(): ?DailyTip
    {
        $tips = DailyTip::published()->orderBy('id')->get();

        if ($tips->isEmpty()) {
            return null;
        }

        $dayNumber = today()->dayOfYear - 1;

        return $tips[$dayNumber % $tips->count()];
    }
}
