<?php

namespace App\Services\InventarioTi;

use App\Contracts\InventarioTi\BajaServiceInterface;
use App\Contracts\InventarioTi\CustodiaServiceInterface;
use App\Enums\EstadoBaja;
use App\Enums\EstadoBien;
use App\Exceptions\ReglaNegocioException;
use App\Models\InventarioTi\BajaBien;
use App\Models\InventarioTi\BienInformatico;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * La baja de los equipos (docs/diseno-subsistema-tic.md, 5.3; fase 1.6).
 *
 * Antes «dar de baja» cambiaba el estado y respondía que el acta quedaba
 * pendiente. Ahora:
 *
 * 1. El técnico la propone con su informe técnico: motivo, diagnóstico y
 *    recomendación. El informe sale en PDF para firmarlo.
 * 2. El jefe de TIC la aprueba con el memorando de Quipux (D4), o la rechaza
 *    con su motivo.
 * 3. Al aprobarla: si alguien tiene el equipo en custodia, se registra la
 *    devolución; el equipo queda dado de baja y deja el grupo de préstamo; los
 *    componentes que se eligen salen con él y los demás se desenlazan y siguen
 *    en servicio.
 * 4. Después se anota la entrega a Activos Fijos y Bodega.
 */
final class BajaService implements BajaServiceInterface
{
    private const RELACIONES = [
        'bien:id,codigo_institucional,codigo_anterior,numero_serie,modelo,tipo_bien_id,marca_id,estado_operativo',
        'bien.tipo:id,nombre', 'bien.marca:id,nombre',
        'informadoPor:id,usuario_ti,email,servidor_id', 'informadoPor.servidor:id,nombre,apellido',
        'aprobadoPor:id,usuario_ti,email,servidor_id', 'aprobadoPor.servidor:id,nombre,apellido',
        'rechazadoPor:id,usuario_ti,email,servidor_id', 'rechazadoPor.servidor:id,nombre,apellido',
        'componentes.bien:id,codigo_institucional,tipo_bien_id', 'componentes.bien.tipo:id,nombre',
        'principal.bien:id,codigo_institucional',
    ];

    public function __construct(private readonly CustodiaServiceInterface $custodia) {}

    public function listar(array $filtros): LengthAwarePaginator
    {
        return BajaBien::with(self::RELACIONES)
            ->when($filtros['estado'] ?? null, fn (Builder $q, $e) => $q->where('estado', $e))
            ->when($filtros['bien_informatico_id'] ?? null, fn (Builder $q, $id) => $q->where('bien_informatico_id', $id))
            ->when(! empty($filtros['pendientes_de_entrega']), fn (Builder $q) => $q
                ->where('estado', EstadoBaja::APROBADA)->whereNull('entregado_activos_en'))
            ->when($filtros['search'] ?? null, fn (Builder $q, $s) => $q->whereHas('bien', fn (Builder $b) => $b
                ->where('codigo_institucional', 'ilike', "%{$s}%")->orWhere('numero_serie', 'ilike', "%{$s}%")))
            ->orderByDesc('fecha_informe')
            ->orderByDesc('id')
            ->paginate(min(max((int) ($filtros['per_page'] ?? 20), 1), 100));
    }

    public function obtener(int $id): BajaBien
    {
        return BajaBien::with(self::RELACIONES)->findOrFail($id);
    }

    public function proponer(array $datos): BajaBien
    {
        return DB::transaction(function () use ($datos) {
            $bien = BienInformatico::lockForUpdate()->findOrFail($datos['bien_informatico_id']);

            if ($bien->estado_operativo === EstadoBien::DADO_DE_BAJA) {
                throw new ReglaNegocioException('El equipo ya está dado de baja.');
            }
            if (BajaBien::where('bien_informatico_id', $bien->id)->where('estado', EstadoBaja::PROPUESTA)->exists()) {
                throw new ReglaNegocioException('El equipo ya tiene una baja propuesta, esperando la aprobación del jefe de TIC.');
            }

            $baja = BajaBien::create([
                'bien_informatico_id' => $bien->id,
                'estado'              => EstadoBaja::PROPUESTA,
                'motivo'              => $datos['motivo'],
                'diagnostico'         => $datos['diagnostico'],
                'recomendacion'       => $datos['recomendacion'] ?? null,
                'fecha_informe'       => $datos['fecha_informe'],
                'soporte_anterior'    => $datos['soporte_anterior'] ?? null,
                'informado_por'       => auth()->id(),
            ]);

            return $this->obtener($baja->id);
        });
    }

    public function aprobar(BajaBien $baja, array $datos): BajaBien
    {
        return DB::transaction(function () use ($baja, $datos) {
            $baja = BajaBien::lockForUpdate()->findOrFail($baja->id);
            $this->exigirPropuesta($baja);
            $bien = BienInformatico::with('componentes', 'asignacionVigente')->lockForUpdate()->findOrFail($baja->bien_informatico_id);
            $fecha = $datos['fecha_baja'];

            $aprobacion = [
                'estado'           => EstadoBaja::APROBADA,
                'fecha_baja'       => $fecha,
                'memorando_numero' => $datos['memorando_numero'] ?? null,
                'aprobado_por'     => auth()->id(),
                'aprobado_en'      => now(),
            ];
            $baja->update($aprobacion);
            $this->sacarDelServicio($bien, $fecha, $baja->numero());

            // Los componentes: los elegidos salen con él; los demás quedan sueltos y en servicio.
            $deBaja = array_map('intval', $datos['componentes_baja'] ?? []);
            foreach ($bien->componentes as $componente) {
                if (in_array($componente->id, $deBaja, true)) {
                    BajaBien::create([
                        ...$aprobacion,
                        'bien_informatico_id' => $componente->id,
                        'motivo'              => $baja->motivo,
                        'diagnostico'         => "Sale junto con el equipo {$bien->codigo_institucional} ({$baja->numero()}).",
                        'fecha_informe'       => $baja->fecha_informe,
                        'informado_por'       => $baja->informado_por,
                        'baja_principal_id'   => $baja->id,
                    ]);
                    $this->sacarDelServicio($componente, $fecha, $baja->numero());
                }
                $componente->update(['bien_padre_id' => null]);
            }

            return $this->obtener($baja->id);
        });
    }

    public function rechazar(BajaBien $baja, string $motivo): BajaBien
    {
        $this->exigirPropuesta($baja);

        $baja->update([
            'estado'         => EstadoBaja::RECHAZADA,
            'rechazado_por'  => auth()->id(),
            'rechazado_en'   => now(),
            'motivo_rechazo' => $motivo,
        ]);

        return $this->obtener($baja->id);
    }

    public function adjuntarMemorando(BajaBien $baja, UploadedFile $archivo): BajaBien
    {
        if ($baja->estado !== EstadoBaja::APROBADA) {
            throw new ReglaNegocioException('El memorando se adjunta a una baja aprobada.');
        }

        $ruta = $archivo->store("inventario/bajas/{$baja->id}", 'local')
            ?: throw new ReglaNegocioException('No se pudo guardar el archivo. Inténtelo de nuevo.');
        $anterior = $baja->memorando_archivo;
        $baja->update(['memorando_archivo' => $ruta, 'memorando_nombre' => $archivo->getClientOriginalName()]);

        if ($anterior && $anterior !== $ruta) {
            Storage::disk('local')->delete($anterior);
        }

        return $this->obtener($baja->id);
    }

    public function registrarEntregaActivos(BajaBien $baja, string $fecha): BajaBien
    {
        if ($baja->estado !== EstadoBaja::APROBADA) {
            throw new ReglaNegocioException('Solo un equipo con la baja aprobada se entrega a Activos Fijos.');
        }
        if ($fecha < $baja->fecha_baja->toDateString()) {
            throw new ReglaNegocioException('La entrega no puede ser anterior a la baja del ' . $baja->fecha_baja->format('d/m/Y') . '.');
        }

        $baja->update(['entregado_activos_en' => $fecha]);

        return $this->obtener($baja->id);
    }

    /** El informe técnico de baja, generado al pedirlo: el papel y el registro no se contradicen. */
    public function generarInforme(int $id): array
    {
        $baja = BajaBien::with([
            'bien.tipo', 'bien.marca', 'bien.ubicacion', 'bien.unidadAdministrativa',
            'bien.asignaciones' => fn ($q) => $q->orderByDesc('fecha_asignacion')->orderByDesc('id')->limit(1),
            'bien.asignaciones.servidor', 'informadoPor.servidor.puesto.cargo', 'aprobadoPor.servidor.puesto.cargo',
            'componentes.bien.tipo',
        ])->findOrFail($id);

        $pdf = Pdf::loadView('pdf.inventario.informe-baja', [
            'baja' => $baja,
            'logo' => public_path('images/logo-gadpe.png'),
        ])->setPaper('a4', 'portrait');

        return ['content' => $pdf->output(), 'filename' => 'informe-baja-' . $baja->numero() . '.pdf'];
    }

    private function exigirPropuesta(BajaBien $baja): void
    {
        if ($baja->estado !== EstadoBaja::PROPUESTA) {
            throw new ReglaNegocioException("La baja ya está " . mb_strtolower($baja->estado->etiqueta()) . ": no se puede volver a resolver.");
        }
    }

    /** Devolución de la custodia, si la hay, y el equipo fuera del servicio. */
    private function sacarDelServicio(BienInformatico $bien, string $fecha, string $numero): void
    {
        $vigente = $bien->asignacionVigente()->first();
        if ($vigente) {
            $this->custodia->devolver($vigente, max($fecha, $vigente->fecha_asignacion->toDateString()), "Por la baja del equipo ({$numero}).");
        }

        $bien->update(['estado_operativo' => EstadoBien::DADO_DE_BAJA, 'prestable' => false]);
    }
}
