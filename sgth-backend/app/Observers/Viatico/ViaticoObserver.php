<?php

namespace App\Observers\Viatico;

use App\Models\Viatico\Viatico;
use Illuminate\Support\Facades\Cache;

class ViaticoObserver
{
    public function updated(Viatico $viatico): void
    {
        if ($viatico->wasChanged('estado')) {
            Cache::forget('sgth:dashboard:kpis');
        }
    }
}
