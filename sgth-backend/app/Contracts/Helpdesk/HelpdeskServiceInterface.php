<?php

namespace App\Contracts\Helpdesk;

use App\Models\Helpdesk\Ticket;

interface HelpdeskServiceInterface
{
    public function crearTicket(array $datos): Ticket;

    public function cerrarTicket(int $id, array $datos): Ticket;

    public function escalarTicket(int $id, int $nivel): Ticket;

    public function vincularBienATicket(int $ticketId, int $bienId): Ticket;

    public function obtenerResultadosEncuestas(array $filtros): array;

    public function obtenerCargaTrabajoYMetricas(int $tecnicoId): array;
}
