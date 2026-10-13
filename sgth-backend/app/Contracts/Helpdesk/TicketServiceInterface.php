<?php

namespace App\Contracts\Helpdesk;

use App\Models\Helpdesk\ComentarioTicket;
use App\Models\Helpdesk\Ticket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

/** Abrir tickets, consultarlos y la conversación con sus archivos. */
interface TicketServiceInterface
{
    /** @param  array<string, mixed>  $filtros */
    public function listar(User $user, array $filtros): LengthAwarePaginator;

    public function obtener(int $id): Ticket;

    /**
     * Cuántos hay en cada pestaña de la bandeja de TIC, con lo que el usuario ve.
     *
     * @return array{sin_asignar: int, mios: int, en_curso: int, vencidos: int, resueltos: int}
     */
    public function resumen(User $user): array;

    /**
     * Desde el Portal, a nombre de quien lo abre; desde TIC (`servidor_id`), a
     * nombre del servidor que llamó o vino.
     *
     * @param  array<string, mixed>  $datos
     */
    public function crear(User $user, array $datos, ?UploadedFile $archivo = null): Ticket;

    /** Si responde el solicitante a un ticket que lo esperaba, el ticket vuelve a quien lo atiende. */
    public function comentar(Ticket $ticket, User $user, string $texto, bool $interno, ?UploadedFile $archivo = null): ComentarioTicket;

    /** La ruta del archivo en el disco privado, o null si no tiene o ya no está. */
    public function rutaArchivo(ComentarioTicket $comentario): ?string;

    /**
     * Lo que necesitan los formularios: categorías, prioridades, subunidades
     * de TIC y técnicos.
     *
     * @return array<string, mixed>
     */
    public function catalogos(): array;
}
