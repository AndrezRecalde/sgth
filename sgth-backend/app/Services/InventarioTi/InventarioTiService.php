<?php
namespace App\Services\InventarioTi;
use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\MantenimientoBien;
use App\Models\User;
use App\Enums\EstadoBien;
use App\Exceptions\ReglaNegocioException;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
final class InventarioTiService implements InventarioTiServiceInterface
{
    /**
     * Lo que queda aquí es la custodia, el acta, el historial y la baja: se
     * rehacen en sus fases (1.3, 1.5, 1.6). La ficha del equipo se mudó a
     * `BienInformaticoService` en la 1.1.
     */

    /**
     * Entregar un bien a un servidor, que queda como su custodio.
     *
     * Escribía en `url_acta_pdf` una ruta compuesta a mano —
     * `/storage/actas/acta_entrega_{id}.pdf`— hacia un archivo que nadie
     * generaba nunca, y el controlador respondía «Acta PDF generada». Quien
     * entregaba el equipo se quedaba con la constancia de que el acta existía
     * y con un enlace que no llevaba a ningún sitio; el acta es justamente el
     * documento que respalda la custodia, así que faltaba lo único que este
     * registro tenía que producir.
     *
     * Ahora el acta se arma de verdad, y bajo demanda: `generarActaEntrega()`.
     * Por eso no queda ninguna ruta que guardar.
     */
    public function asignarBien(array $datos): AsignacionBien
    {
        return DB::transaction(function () use ($datos) {
            $bien = BienInformatico::lockForUpdate()->findOrFail($datos['bien_informatico_id']);

            // Un equipo tiene un custodio a la vez, y uno dado de baja,
            // extraviado o del grupo de préstamo no se entrega.
            if (in_array($bien->estado_operativo, [EstadoBien::DADO_DE_BAJA, EstadoBien::EXTRAVIADO], true)) {
                throw new ReglaNegocioException("El equipo está {$bien->estado_operativo->etiqueta()}: no puede entregarse.");
            }
            if ($bien->prestable) {
                throw new ReglaNegocioException('El equipo es del grupo de préstamo: se presta, no se entrega en custodia.');
            }
            if ($bien->asignacionVigente()->exists()) {
                throw new ReglaNegocioException('El equipo ya tiene un custodio: primero debe devolverse.');
            }

            // Quién entrega lo pone el sistema, no el cuerpo de la petición:
            // es el dato que después firma el acta.
            $datos['created_by'] = auth()->id();

            $asignacion = AsignacionBien::create($datos);

            if ($bien->estado_operativo === EstadoBien::DISPONIBLE) {
                $bien->update(['estado_operativo' => EstadoBien::EN_USO]);
            }

            return $asignacion;
        });
    }

    /**
     * La devolución del bien (fase 7.4a): hasta aquí se asignaba y nada lo
     * devolvía, aunque la columna existía. Es un paso de la desvinculación:
     * quien sale entrega lo que tiene a su cargo.
     */
    public function devolverBien(AsignacionBien $asignacion, string $fecha, ?string $observaciones): AsignacionBien
    {
        if ($asignacion->fecha_devolucion !== null || $asignacion->estado !== 'activa') {
            throw new ReglaNegocioException('Ese bien ya se devolvió.');
        }

        if ($asignacion->fecha_asignacion && $fecha < $asignacion->fecha_asignacion->toDateString()) {
            throw new ReglaNegocioException('La devolución no puede ser anterior a la asignación del '
                .$asignacion->fecha_asignacion->format('d/m/Y').'.');
        }

        $asignacion->update([
            'estado'           => 'devuelta',
            'fecha_devolucion' => $fecha,
            'observaciones'    => trim(collect([$asignacion->observaciones, $observaciones ? "Devolución: {$observaciones}" : null])
                ->filter()->implode("\n")) ?: null,
            'updated_by'       => auth()->id(),
        ]);

        // Sin custodio, el equipo vuelve a TIC. Si estaba en mantenimiento o
        // en garantía, sigue así: la devolución no lo arregla.
        $bien = $asignacion->bien;
        if ($bien && $bien->estado_operativo === EstadoBien::EN_USO) {
            $bien->update(['estado_operativo' => EstadoBien::DISPONIBLE]);
        }

        return $asignacion->fresh('bien');
    }

    /**
     * El acta de entrega-recepción del bien, en PDF.
     *
     * Se genera al pedirla y no se archiva: es el mismo criterio del resto de
     * documentos del sistema —el certificado médico, la acción de personal—,
     * y evita que el papel y el registro puedan contradecirse.
     *
     * El número sale del id de la asignación. No es un folio por año como el
     * de permisos o viáticos: esa numeración necesitaría su propia columna y
     * su candado, y aquí basta con que dos actas nunca compartan número.
     *
     * @return array{content: string, filename: string}
     */
    public function generarActaEntrega(int $id): array
    {
        $asignacion = AsignacionBien::with([
            'bien.tipo', 'bien.marca', 'bien.origen',
            'servidor.unidadAdministrativa',
            'servidor.puesto.cargo', 'servidor.puesto.unidadAdministrativa',
        ])->findOrFail($id);

        $numero = 'ACT-' . str_pad((string) $asignacion->id, 6, '0', STR_PAD_LEFT);

        $pdf = Pdf::loadView('pdf.inventario.acta-entrega', [
            'asignacion' => $asignacion,
            'numero'     => $numero,
            'entrega'    => $this->firmaDeQuienEntrega($asignacion),
            'logo'       => public_path('images/logo-gadpe.png'),
        ])->setPaper('a4', 'portrait');

        return [
            'content'  => $pdf->output(),
            'filename' => 'acta-entrega-' . $numero . '.pdf',
        ];
    }

    /**
     * Quién entregó el bien, tomado de la columna sellada al registrar la
     * asignación y no de quien imprima hoy: una reimpresión no puede
     * atribuirle el acto a otra persona.
     *
     * Sin ese dato —las asignaciones anteriores a este cambio no lo tienen— se
     * imprime el rótulo de la dirección responsable, que es cierto, en vez de
     * un nombre inventado.
     *
     * @return array{nombre: ?string, cargo: string}
     */
    private function firmaDeQuienEntrega(AsignacionBien $asignacion): array
    {
        $cargoPorDefecto = 'Dirección de Tecnologías de la Información y Comunicación';

        $servidor = User::with('servidor.puesto.cargo')
            ->find($asignacion->created_by)?->servidor;

        if (! $servidor) {
            return ['nombre' => null, 'cargo' => $cargoPorDefecto];
        }

        return [
            'nombre' => trim($servidor->nombre . ' ' . $servidor->apellido),
            'cargo'  => $servidor->puesto?->cargo?->nombre ?? $cargoPorDefecto,
        ];
    }

    /**
     * La vida del bien: quién lo ha tenido y qué se le ha hecho.
     *
     * Devolvía un molde vacío —`bien` a null y las dos listas sin nada— y el
     * controlador ni siquiera tenía el método que la ruta declaraba, así que
     * `bienes/{id}/historial` respondía con un 500. Los modelos y sus tablas
     * estaban completos desde el principio.
     *
     * @return array{bien: BienInformatico, asignaciones: mixed, mantenimientos: mixed}
     */
    public function obtenerFichaTecnicaCompleta(array $filtros): array
    {
        $bien = BienInformatico::with(['tipo', 'marca', 'origen'])
            ->findOrFail($filtros['bien_informatico_id']);

        return [
            'bien' => $bien,
            // De la más reciente a la más antigua: lo que interesa de un
            // historial es dónde está el bien ahora y de dónde viene.
            'asignaciones' => AsignacionBien::with('servidor:id,nombre,apellido,cedula')
                ->where('bien_informatico_id', $bien->id)
                ->orderByDesc('fecha_asignacion')
                ->get(),
            'mantenimientos' => MantenimientoBien::with('tecnico:id,usuario_ti,servidor_id')
                ->where('bien_informatico_id', $bien->id)
                ->orderByDesc('fecha_mantenimiento')
                ->get(),
        ];
    }

    public function registrarAuditoriaFisica(array $datos)
    {
        // Retorna mock de registro de auditoria
        return ['estado' => 'Auditoría registrada'];
    }

    public function procesarBaja(array $datos)
    {
        return DB::transaction(function () use ($datos) {
            $bien = BienInformatico::findOrFail($datos['bien_informatico_id']);

            // La columna es `estado_operativo`. Escribía `estado`, que no
            // existe en la tabla ni en el `fillable`, así que Eloquent lo
            // descartaba en silencio: la baja respondía que sí y el bien se
            // quedaba activo.
            $bien->update(['estado_operativo' => EstadoBien::DADO_DE_BAJA]);

            return [
                'bien_informatico_id' => $bien->id,
                'estado_operativo'    => $bien->estado_operativo->value,
                'motivo'              => $datos['motivo'],
                // El acta en PDF y su archivo en el SGD no están construidos.
                // Antes se devolvía una ruta y una referencia inventadas, que
                // no llevaban a ningún sitio; es preferible decir que faltan.
                'acta_pendiente'      => true,
            ];
        });
    }
}