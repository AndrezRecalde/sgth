<?php

namespace App\Observers\Helpdesk;

use App\Models\Helpdesk\Ticket;
use Illuminate\Support\Facades\Cache;

class TicketObserver
{
    public function updated(Ticket $ticket): void
    {
        if ($ticket->wasChanged('estado')) {
            Cache::forget('sgth:dashboard:kpis');
        }
    }
}
