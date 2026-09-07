<?php
namespace App\Services\InventarioTi;
use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\MantenimientoBien;
use App\Models\InventarioTi\TipoBien;
use App\Models\InventarioTi\OrigenBien;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
final class InventarioTiService implements InventarioTiServiceInterface
{
    public function registrarBien(array $datos): BienInformatico
    {
        return DB::transaction(function () use ($datos) {
            // Calcular vida útil si tenemos el origen y el tipo
            if (!empty($datos['origen_bien_id']) && !empty($datos['tipo_bien_id'])) {
                $origen = OrigenBien::find($datos['origen_bien_id']);
                $tipo = TipoBien::find($datos['tipo_bien_id']);
                if ($origen && $tipo && $tipo->anios_vida_util > 0) {
                    $datos['fecha_fin_vida_util'] = \Carbon\Carbon::parse($origen->fecha_adquisicion)->addYears($tipo->anios_vida_util);
                }
            }
            // Generación de QR
            $datos['codigo_qr'] = $datos['codigo_institucional'] . '-QR';
            return BienInformatico::create($datos);
        });
    }
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
            // Quién entrega lo pone el sistema, no el cuerpo de la petición:
            // es el dato que después firma el acta.
            $datos['created_by'] = auth()->id();

            return AsignacionBien::create($datos);
        });
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
            $bien->update(['estado_operativo' => 'dado_de_baja']);

            return [
                'bien_informatico_id' => $bien->id,
                'estado_operativo'    => $bien->estado_operativo,
                'motivo'              => $datos['motivo'],
                // El acta en PDF y su archivo en el SGD no están construidos.
                // Antes se devolvía una ruta y una referencia inventadas, que
                // no llevaban a ningún sitio; es preferible decir que faltan.
                'acta_pendiente'      => true,
            ];
        });
    }
}