<?php

namespace App\Contracts\Helpdesk;

interface SlaServiceInterface
{
    public function verificarTicketsAbiertos(): void;

    public function escalarTicket(int $ticketId): void;
}
