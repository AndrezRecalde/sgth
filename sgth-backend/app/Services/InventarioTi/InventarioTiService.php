<?php
namespace App\Services\InventarioTi;
use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\MantenimientoBien;
use App\Models\InventarioTi\TipoBien;
use App\Models\InventarioTi\OrigenBien;
use App\Models\User;
use App\Exceptions\ReglaNegocioException;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
final class InventarioTiService implements InventarioTiServiceInterface
{
    /** Estados que este servicio admite escribir desde una edición normal. */
    public const ESTADOS_OPERATIVOS = [
        'activo', 'en_mantenimiento', 'robado', 'perdido',
    ];

    public const CONDICIONES_FISICAS = ['bueno', 'regular', 'malo'];

    public function registrarBien(array $datos): BienInformatico
    {
        return DB::transaction(function () use ($datos) {
            $datos['fecha_fin_vida_util'] = $this->finDeVidaUtil($datos)
                ?? ($datos['fecha_fin_vida_util'] ?? null);

            $datos['codigo_qr']  = $datos['codigo_institucional'] . '-QR';
            $datos['created_by'] = auth()->id();

            return BienInformatico::create($datos);
        });
    }

    /**
     * El inventario. Devolvía `[]` con el mensaje «Bienes listados».
     *
     * Una lista vacía se lee como «no hay bienes registrados», no como «esto no
     * está construido»: es el listado del que cuelga todo el módulo, y estaba
     * hueco.
     *
     * @param  array<string, mixed>  $filtros
     */
    public function listarBienes(array $filtros): LengthAwarePaginator
    {
        $query = BienInformatico::with(['tipo', 'marca', 'origen'])
            ->latest('id');

        if (! empty($filtros['search'])) {
            $termino = $filtros['search'];

            // Los tres campos por los que se busca un equipo en la mano: la
            // etiqueta pegada, la serie del fabricante y el modelo.
            $query->where(function ($q) use ($termino) {
                $q->where('codigo_institucional', 'ilike', "%{$termino}%")
                  ->orWhere('numero_serie', 'ilike', "%{$termino}%")
                  ->orWhere('modelo', 'ilike', "%{$termino}%");
            });
        }

        foreach (['tipo_bien_id', 'marca_id', 'estado_operativo', 'condicion_fisica'] as $campo) {
            if (! empty($filtros[$campo])) {
                $query->where($campo, $filtros[$campo]);
            }
        }

        // Topado: sin límite, `per_page` permitía traerse el inventario entero
        // en una petición.
        $porPagina = min((int) ($filtros['per_page'] ?? 20), 100);

        return $query->paginate(max($porPagina, 1));
    }

    /** Devolvía `['id' => $id]`: el eco del parámetro, no el bien. */
    public function obtenerBien(int $id): BienInformatico
    {
        return BienInformatico::with(['tipo', 'marca', 'origen'])->findOrFail($id);
    }

    /**
     * Editar un bien. Respondía «Bien actualizado» sin tocar la base.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizarBien(int $id, array $datos): BienInformatico
    {
        return DB::transaction(function () use ($id, $datos) {
            $bien = BienInformatico::lockForUpdate()->findOrFail($id);

            // Dar de baja es un acto con motivo y respaldo documental, y tiene
            // su propio flujo. Colarlo por una edición dejaría el bien fuera de
            // servicio sin constancia de por qué.
            if (($datos['estado_operativo'] ?? null) === 'dado_de_baja') {
                throw new ReglaNegocioException(
                    'La baja de un bien se registra en «bajas», que exige el motivo: '
                    . 'no puede hacerse editando su estado operativo.'
                );
            }

            // El código del QR se deriva del institucional. Si uno cambia y el
            // otro no, la etiqueta pegada al equipo deja de encontrarlo al
            // escanearla.
            if (
                array_key_exists('codigo_institucional', $datos)
                && $datos['codigo_institucional'] !== $bien->codigo_institucional
            ) {
                $datos['codigo_qr'] = $datos['codigo_institucional'] . '-QR';
            }

            // La vida útil sale del tipo y del origen: si cambia cualquiera de
            // los dos, la fecha anterior ya no corresponde.
            $nuevoFin = $this->finDeVidaUtil([
                'tipo_bien_id'   => $datos['tipo_bien_id']   ?? $bien->tipo_bien_id,
                'origen_bien_id' => $datos['origen_bien_id'] ?? $bien->origen_bien_id,
            ]);

            if ($nuevoFin !== null) {
                $datos['fecha_fin_vida_util'] = $nuevoFin;
            }

            $datos['updated_by'] = auth()->id();

            $bien->update($datos);

            return $bien->fresh(['tipo', 'marca', 'origen']);
        });
    }

    /**
     * Retirar del inventario un bien registrado por error.
     *
     * Respondía «Bien dado de baja» sin borrar nada, y además nombraba otra
     * cosa: la baja —el bien que se retira del servicio— se registra en
     * «bajas», con su motivo, y el bien sigue existiendo en el inventario.
     * Esto de aquí borra la ficha.
     *
     * Por eso solo procede mientras la ficha no tenga historia: un bien que ya
     * fue entregado a alguien o pasó por mantenimiento no es un error de
     * digitación, y borrarlo se llevaría por delante el rastro de quién lo tuvo.
     */
    public function retirarBien(int $id): void
    {
        DB::transaction(function () use ($id) {
            $bien = BienInformatico::findOrFail($id);

            $asignaciones   = AsignacionBien::where('bien_informatico_id', $bien->id)->count();
            $mantenimientos = MantenimientoBien::where('bien_informatico_id', $bien->id)->count();

            if ($asignaciones > 0 || $mantenimientos > 0) {
                throw new ReglaNegocioException(
                    "El bien «{$bien->codigo_institucional}» tiene historial "
                    . "({$asignaciones} asignación(es) y {$mantenimientos} mantenimiento(s)): "
                    . 'no puede borrarse del inventario. Si salió de servicio, regístrelo como baja.'
                );
            }

            $bien->delete();
        });
    }

    /**
     * La fecha en que el bien agota su vida útil: la adquisición más los años
     * que su tipo declara. Null cuando falta cualquiera de los dos datos.
     *
     * @param  array<string, mixed>  $datos
     */
    private function finDeVidaUtil(array $datos): ?\Carbon\Carbon
    {
        if (empty($datos['origen_bien_id']) || empty($datos['tipo_bien_id'])) {
            return null;
        }

        $origen = OrigenBien::find($datos['origen_bien_id']);
        $tipo   = TipoBien::find($datos['tipo_bien_id']);

        if (! $origen || ! $tipo || $tipo->anios_vida_util <= 0) {
            return null;
        }

        return \Carbon\Carbon::parse($origen->fecha_adquisicion)
            ->addYears($tipo->anios_vida_util);
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