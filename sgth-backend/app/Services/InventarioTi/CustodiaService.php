<?php

namespace App\Services\InventarioTi;

use App\Contracts\InventarioTi\CustodiaServiceInterface;
use App\Enums\EstadoBien;
use App\Exceptions\ReglaNegocioException;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\BienInformatico;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * La custodia de los equipos (docs/diseno-subsistema-tic.md, 5.3; fase 1.3).
 *
 * Salió de `InventarioTiService`, que se queda con el historial y la baja.
 *
 * - Entregar: el equipo pasa a un custodio, que firma el acta. Si lo usa otra
 *   persona, se anota (D3).
 * - Traspasar: se cierra la custodia vigente y se abre la siguiente el mismo
 *   día, enlazadas. Es lo que el Excel de TIC llevaba como «USUARIO
 *   ANTERIOR» con su memorando.
 * - Devolver: el equipo vuelve a TIC. La desvinculación de quien sale la usa.
 *
 * El memorando de TIC se hace en Quipux: aquí van su número y su PDF (D4).
 */
final class CustodiaService implements CustodiaServiceInterface
{
    public function __construct(private readonly UbicacionesBienService $ubicaciones) {}

    private const RELACIONES = [
        'bien:id,codigo_institucional,modelo,tipo_bien_id,subtipo_bien_id,marca_id,ubicacion_id,estado_operativo',
        'bien.tipo:id,nombre', 'bien.marca:id,nombre',
        'servidor:id,nombre,apellido,cedula,unidad_administrativa_id',
        'servidor.unidadAdministrativa:id,nombre,acronimo',
        'usuario:id,nombre,apellido,cedula',
        'anterior:id,servidor_id', 'anterior.servidor:id,nombre,apellido',
        'registradaPor:id,usuario_ti,email,servidor_id', 'registradaPor.servidor:id,nombre,apellido',
    ];

    public function listar(array $filtros): LengthAwarePaginator
    {
        return AsignacionBien::with(self::RELACIONES)
            ->when($filtros['bien_informatico_id'] ?? null, fn ($q, $id) => $q->where('bien_informatico_id', $id))
            ->when($filtros['servidor_id'] ?? null, fn ($q, $id) => $q->where('servidor_id', $id))
            ->when(! empty($filtros['vigentes']), fn ($q) => $q->where('estado', AsignacionBien::ESTADO_ACTIVA))
            ->orderByDesc('fecha_asignacion')
            ->orderByDesc('id')
            ->paginate(min(max((int) ($filtros['per_page'] ?? 20), 1), 100));
    }

    public function obtener(int $id): AsignacionBien
    {
        return AsignacionBien::with(self::RELACIONES)->findOrFail($id);
    }

    public function entregar(array $datos): AsignacionBien
    {
        return DB::transaction(function () use ($datos) {
            $bien = BienInformatico::lockForUpdate()->findOrFail($datos['bien_informatico_id']);
            $this->validarEntregable($bien);

            if ($bien->asignacionVigente()->exists()) {
                throw new ReglaNegocioException('El equipo ya tiene un custodio: para cambiarlo, regístrelo como traspaso.');
            }

            $asignacion = $this->crear($bien, $datos, AsignacionBien::MOTIVO_ENTREGA, null);
            $this->ubicar($bien, $datos, 'entrega');

            if ($bien->estado_operativo === EstadoBien::DISPONIBLE) {
                $bien->update(['estado_operativo' => EstadoBien::EN_USO]);
            }

            return $this->obtener($asignacion->id);
        });
    }

    public function traspasar(AsignacionBien $actual, array $datos): AsignacionBien
    {
        return DB::transaction(function () use ($actual, $datos) {
            $actual = AsignacionBien::lockForUpdate()->findOrFail($actual->id);
            $bien = BienInformatico::lockForUpdate()->findOrFail($actual->bien_informatico_id);

            if (! $actual->estaVigente()) {
                throw new ReglaNegocioException('Esa custodia ya terminó: solo se traspasa la vigente.');
            }
            if ((int) $datos['servidor_id'] === (int) $actual->servidor_id) {
                throw new ReglaNegocioException('El equipo ya está a cargo de esa persona.');
            }
            $this->validarFecha($actual, $datos['fecha_asignacion'], 'El traspaso');
            $this->validarEntregable($bien);

            $actual->update([
                'estado'           => AsignacionBien::ESTADO_DEVUELTA,
                'fecha_devolucion' => $datos['fecha_asignacion'],
                'updated_by'       => auth()->id(),
            ]);

            $nueva = $this->crear($bien, $datos, AsignacionBien::MOTIVO_TRASPASO, $actual->id);
            $this->ubicar($bien, $datos, 'traspaso');

            return $this->obtener($nueva->id);
        });
    }

    /**
     * La devolución a TIC. Si el equipo estaba en mantenimiento o en garantía
     * sigue así: devolverlo no lo arregla.
     */
    public function devolver(AsignacionBien $asignacion, string $fecha, ?string $observaciones, ?string $soporteAnterior = null): AsignacionBien
    {
        return DB::transaction(function () use ($asignacion, $fecha, $observaciones, $soporteAnterior) {
            $asignacion = AsignacionBien::lockForUpdate()->findOrFail($asignacion->id);

            if (! $asignacion->estaVigente()) {
                throw new ReglaNegocioException('Ese bien ya se devolvió.');
            }
            $this->validarFecha($asignacion, $fecha, 'La devolución');

            $asignacion->update([
                'estado'           => AsignacionBien::ESTADO_DEVUELTA,
                'fecha_devolucion' => $fecha,
                'observaciones'    => trim(collect([$asignacion->observaciones, $observaciones ? "Devolución: {$observaciones}" : null])
                    ->filter()->implode("\n")) ?: null,
                'soporte_anterior' => $asignacion->soporte_anterior ?? $soporteAnterior,
                'updated_by'       => auth()->id(),
            ]);

            $bien = $asignacion->bien;
            if ($bien && $bien->estado_operativo === EstadoBien::EN_USO) {
                $bien->update(['estado_operativo' => EstadoBien::DISPONIBLE]);
            }

            return $this->obtener($asignacion->id);
        });
    }

    /** El PDF del memorando de Quipux. Reemplaza al anterior: hay uno por custodia. */
    public function adjuntarMemorando(AsignacionBien $asignacion, UploadedFile $archivo): AsignacionBien
    {
        $ruta = $archivo->store("inventario/memorandos/{$asignacion->id}", 'local');
        if (! $ruta) {
            throw new ReglaNegocioException('No se pudo guardar el archivo. Inténtelo de nuevo.');
        }

        $anterior = $asignacion->memorando_archivo;
        $asignacion->update([
            'memorando_archivo' => $ruta,
            'memorando_nombre'  => $archivo->getClientOriginalName(),
            'updated_by'        => auth()->id(),
        ]);

        // Después de guardar el nuevo: si algo falla antes, no se queda sin documento.
        if ($anterior && $anterior !== $ruta) {
            Storage::disk('local')->delete($anterior);
        }

        return $this->obtener($asignacion->id);
    }

    public function deServidor(int $servidorId): Collection
    {
        return AsignacionBien::with([
            ...self::RELACIONES,
            'bien.subtipo:id,nombre', 'bien.ubicacion:id,sede,piso',
        ])
            ->where('estado', AsignacionBien::ESTADO_ACTIVA)
            ->where(fn ($q) => $q->where('servidor_id', $servidorId)->orWhere('usuario_servidor_id', $servidorId))
            ->orderByDesc('fecha_asignacion')
            ->orderByDesc('id')
            ->get();
    }

    public function aceptaSoporteAnterior(): bool
    {
        return Carbon::today()->lt(Carbon::parse(config('inventario.corte_soporte_anterior')));
    }

    /**
     * El acta de entrega-recepción, generada al pedirla y no archivada: el
     * papel y el registro no pueden contradecirse. El número sale del id.
     */
    public function generarActa(int $id): array
    {
        $asignacion = AsignacionBien::with([
            'bien.tipo', 'bien.marca', 'bien.origen',
            'servidor.unidadAdministrativa', 'servidor.puesto.cargo', 'servidor.puesto.unidadAdministrativa',
            'usuario', 'anterior.servidor',
        ])->findOrFail($id);

        $numero = 'ACT-' . str_pad((string) $asignacion->id, 6, '0', STR_PAD_LEFT);

        $pdf = Pdf::loadView('pdf.inventario.acta-entrega', [
            'asignacion' => $asignacion,
            'numero'     => $numero,
            'entrega'    => $this->firmaDeQuienEntrega($asignacion),
            // El logo vigente el día de la entrega (D3).
            ...app(\App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface::class)->paraDocumento($asignacion->fecha_asignacion),
        ])->setPaper('a4', 'portrait');

        return ['content' => $pdf->output(), 'filename' => 'acta-entrega-' . $numero . '.pdf'];
    }

    /** @param  array<string, mixed>  $datos */
    private function crear(BienInformatico $bien, array $datos, string $motivo, ?int $anterior): AsignacionBien
    {
        $usuario = $datos['usuario_servidor_id'] ?? null;

        return AsignacionBien::create([
            'bien_informatico_id'    => $bien->id,
            'servidor_id'            => $datos['servidor_id'],
            // Si quien usa el equipo es el mismo custodio, no hay nada que anotar.
            'usuario_servidor_id'    => $usuario && (int) $usuario !== (int) $datos['servidor_id'] ? $usuario : null,
            'fecha_asignacion'       => $datos['fecha_asignacion'],
            'observaciones'          => $datos['observaciones'] ?? null,
            'memorando_numero'       => $datos['memorando_numero'] ?? null,
            'soporte_anterior'       => $datos['soporte_anterior'] ?? null,
            'estado'                 => AsignacionBien::ESTADO_ACTIVA,
            'motivo'                 => $motivo,
            'asignacion_anterior_id' => $anterior,
            // Quién entrega lo pone el sistema: es el dato que firma el acta.
            'created_by'             => auth()->id(),
        ]);
    }

    /** Si la entrega o el traspaso dice dónde queda el equipo, se mueve (con sus componentes). */
    private function ubicar(BienInformatico $bien, array $datos, string $motivo): void
    {
        if (array_key_exists('ubicacion_id', $datos)) {
            $this->ubicaciones->mover($bien, $datos['ubicacion_id'], $motivo);
        }
    }

    /** Uno dado de baja, extraviado o del grupo de préstamo no se entrega en custodia. */
    private function validarEntregable(BienInformatico $bien): void
    {
        if (in_array($bien->estado_operativo, [EstadoBien::DADO_DE_BAJA, EstadoBien::EXTRAVIADO], true)) {
            throw new ReglaNegocioException("El equipo está {$bien->estado_operativo->etiqueta()}: no puede entregarse.");
        }
        if ($bien->prestable) {
            throw new ReglaNegocioException('El equipo es del grupo de préstamo: se presta, no se entrega en custodia.');
        }
    }

    private function validarFecha(AsignacionBien $asignacion, string $fecha, string $que): void
    {
        if ($asignacion->fecha_asignacion && $fecha < $asignacion->fecha_asignacion->toDateString()) {
            throw new ReglaNegocioException("{$que} no puede ser anterior a la entrega del "
                . $asignacion->fecha_asignacion->format('d/m/Y') . '.');
        }
    }

    /**
     * Quién entregó, de la columna sellada al registrar y no de quien imprima
     * hoy. Sin ese dato, el rótulo de la dirección responsable.
     *
     * @return array{nombre: ?string, cargo: string}
     */
    private function firmaDeQuienEntrega(AsignacionBien $asignacion): array
    {
        $cargoPorDefecto = 'Dirección de Tecnologías de la Información y Comunicación';

        $servidor = User::with('servidor.puesto.cargo')->find($asignacion->created_by)?->servidor;

        if (! $servidor) {
            return ['nombre' => null, 'cargo' => $cargoPorDefecto];
        }

        return [
            'nombre' => trim($servidor->nombre . ' ' . $servidor->apellido),
            'cargo'  => $servidor->puesto?->cargo?->nombre ?? $cargoPorDefecto,
        ];
    }
}
