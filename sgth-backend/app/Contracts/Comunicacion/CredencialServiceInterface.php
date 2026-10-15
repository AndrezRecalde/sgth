<?php

namespace App\Contracts\Comunicacion;

use App\Enums\FormatoImpresionCredencial;
use App\Enums\MotivoRevocacionCredencial;
use App\Models\Comunicacion\Credencial;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/** La credencial de los servidores (docs/diseno-comunicacion-social.md, 6). */
interface CredencialServiceInterface
{
    /**
     * Pone al día la credencial de un servidor con su vínculo y su foto: la
     * crea si tiene vínculo vigente y foto, la vence o la reactiva con la
     * fecha de fin del contrato, y la anula si el vínculo terminó.
     */
    public function sincronizar(int $servidorId): ?Credencial;

    /**
     * Sincroniza a todos los que tienen foto o una credencial viva. Lo corre
     * la tarea de cada noche.
     *
     * @return array{creadas: int, vencidas: int, reactivadas: int, anuladas: int}
     */
    public function conciliar(): array;

    /**
     * Los servidores con vínculo vigente y el estado de su credencial, para la
     * pantalla Credenciales.
     *
     * @param  array{buscar?: string, unidad_administrativa_id?: int, situacion?: string, per_page?: int}  $filtros
     */
    public function listar(array $filtros): LengthAwarePaginator;

    /** Todas sus credenciales con sus impresiones, de la más reciente a la más antigua. */
    public function historial(Servidor $servidor): Collection;

    /**
     * Revoca la credencial y, si el servidor sigue con vínculo y foto, la
     * repone con otro código.
     *
     * @return array{revocada: Credencial, repuesta: ?Credencial}
     */
    public function revocar(Credencial $credencial, MotivoRevocacionCredencial $motivo, ?string $detalle, User $usuario): array;

    /** La tarjeta impresa volvió a Comunicación: el paso de la desvinculación. */
    public function registrarDevolucion(Credencial $credencial, string $fecha, ?string $nota, User $usuario): Credencial;

    /**
     * Las credenciales que se imprimen para una unidad: las vigentes, con foto,
     * de la unidad y de las que cuelgan de ella.
     *
     * @return Collection<int, Credencial>
     */
    public function deUnidad(int $unidadId): Collection;

    /**
     * El PDF para imprimir. Cada credencial queda registrada con lo que se
     * imprimió; si son varias, en un mismo lote.
     *
     * @param  Collection<int, Credencial>  $credenciales
     */
    public function imprimir(Collection $credenciales, FormatoImpresionCredencial $formato, User $usuario): string;

    /** La credencial del propio servidor en PDF, sin registrar una impresión. */
    public function pdfDigital(Credencial $credencial): string;

    /** El QR de verificación, como imagen SVG en data: URL. */
    public function qr(Credencial $credencial): ?string;

    /**
     * Lo que enseña la verificación pública: si no está vigente, nada más.
     * Null si el código no existe.
     *
     * @return array{vigente: bool, nombre?: string, cargo?: ?string, unidad?: ?string, foto?: ?string}|null
     */
    public function verificar(string $codigo): ?array;
}
