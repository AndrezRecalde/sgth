<?php

namespace App\Services\Disciplinario;

use App\Contracts\Disciplinario\DisciplinarioServiceInterface;
use App\Enums\EstadoSumario;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Enums\TipoSancion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Disciplinario\SancionDisciplinaria;
use App\Models\Disciplinario\Sumario;
use App\Models\Expediente\Servidor;
use App\Helpers\DiasHabilesHelper;
use App\Services\Expediente\MovimientoPersonalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DisciplinarioService implements DisciplinarioServiceInterface
{
    use DiasHabilesHelper;

    public function __construct(
        private readonly MovimientoPersonalService $movimientoPersonalService,
    ) {
    }

    /**
     * Secuencia procesal del sumario. No es "cualquier estado hacia adelante":
     * se avanza hito por hito, y RESUELTO se alcanza solo vía
     * resolverSumario(), que es donde se aplica la sanción.
     */
    private const TRANSICIONES_SUMARIO = [
        'abierto'        => ['en_instruccion', 'cerrado'],
        'en_instruccion' => ['en_prueba', 'cerrado'],
        'en_prueba'      => ['con_informe', 'cerrado'],
        'con_informe'    => ['cerrado'],
        'resuelto'       => ['apelado', 'cerrado'],
        'apelado'        => ['cerrado'],
        'cerrado'        => [],
    ];

    public function abrirSumario(int $servidorId, array $datos, int $userId): Sumario
    {
        $servidor = Servidor::with('contratoVigente')->findOrFail($servidorId);
        $nombramiento = $servidor->contratoVigente?->tipo_nombramiento;

        if ($nombramiento === TipoNombramiento::CODIGO_TRABAJO) {
            throw new ReglaNegocioException(
                'El sumario administrativo es el procedimiento de la LOSEP. Para un obrero '
                    .'bajo Código del Trabajo, tramite un visto bueno ante el Inspector del Trabajo.'
            );
        }

        $tieneAbierto = Sumario::where('servidor_id', $servidorId)
            ->whereNotIn('estado', [EstadoSumario::RESUELTO->value, EstadoSumario::CERRADO->value])
            ->exists();

        if ($tieneAbierto) {
            throw new ReglaNegocioException('El servidor ya tiene un sumario administrativo en curso.');
        }

        return Sumario::create([
            ...$datos,
            'servidor_id'    => $servidorId,
            'estado'         => EstadoSumario::ABIERTO,
            'fecha_apertura' => $datos['fecha_apertura'] ?? now()->toDateString(),
            'notificado_sn'  => false,
            'created_by'     => $userId,
            'updated_by'     => $userId,
        ]);
    }

    public function avanzarSumario(Sumario $sumario, string $estadoDestino, array $datos, int $userId): Sumario
    {
        $permitidas = self::TRANSICIONES_SUMARIO[$sumario->estado->value] ?? [];

        if (!in_array($estadoDestino, $permitidas, true)) {
            throw new ReglaNegocioException(
                "No se puede pasar de '{$sumario->estado->value}' a '{$estadoDestino}'."
            );
        }

        $destino = EstadoSumario::from($estadoDestino);

        // Cada hito procesal deja su fecha: son las que alimentan el control
        // de plazos legales de controlarPlazosLegales().
        match ($destino) {
            EstadoSumario::EN_INSTRUCCION => $this->marcarNotificacion($sumario, $datos),
            EstadoSumario::EN_PRUEBA      => $sumario->fecha_termino_prueba = $datos['fecha_termino_prueba'] ?? null,
            EstadoSumario::CON_INFORME    => $sumario->fecha_informe = $datos['fecha_informe'] ?? now()->toDateString(),
            default                       => null,
        };

        $sumario->estado     = $destino;
        $sumario->updated_by = $userId;
        $sumario->save();

        return $sumario->fresh(['servidor', 'sancion']);
    }

    private function marcarNotificacion(Sumario $sumario, array $datos): void
    {
        $sumario->notificado_sn      = true;
        $sumario->fecha_notificacion = $datos['fecha_notificacion'] ?? now()->toDateString();
    }

    public function resolverSumario(int $sumarioId, array $datosSancion, int $userId): Sumario
    {
        $sumario = Sumario::findOrFail($sumarioId);

        if ($sumario->estado === EstadoSumario::RESUELTO || $sumario->estado === EstadoSumario::CERRADO) {
            throw new ReglaNegocioException('El sumario ya se encuentra resuelto o cerrado.');
        }

        $this->assertSancionAplicableAlRegimen($sumario, $datosSancion['tipo_sancion']);

        DB::beginTransaction();
        try {
            $sumario->estado = EstadoSumario::RESUELTO;
            $sumario->fecha_resolucion = now()->toDateString();
            $sumario->updated_by = $userId;
            $sumario->save();

            SancionDisciplinaria::create([
                'sumario_id'       => $sumario->id,
                'tipo_falta'       => $datosSancion['tipo_falta'],
                'tipo_sancion'     => $datosSancion['tipo_sancion'],
                'porcentaje_multa' => $datosSancion['porcentaje_multa'] ?? null,
                'dias_suspension'  => $datosSancion['dias_suspension'] ?? null,
                'fecha_efectiva'   => $datosSancion['fecha_efectiva'] ?? now()->toDateString(),
                'observaciones'    => $datosSancion['observaciones'] ?? null,
                'created_by'       => $userId,
            ]);

            // Regla de Negocio: Si la sanción es Destitución, registrar el egreso.
            if ($datosSancion['tipo_sancion'] === TipoSancion::DESTITUCION->value) {
                $servidor = Servidor::findOrFail($sumario->servidor_id);
                $servidor->estado = false;
                $servidor->save();

                // La destitución es una cesación de funciones cuyo subtipo es
                // 'destitucion' — el sumario es su causa, no su tipo. Se
                // registra así desde la taxonomía de dos niveles; el tipo
                // plano 'destitucion' queda solo para el histórico anterior.
                $this->movimientoPersonalService->registrar($servidor->id, [
                    'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
                    'subtipo_movimiento' => SubtipoMovimientoPersonal::DESTITUCION->value,
                    'descripcion'        => 'Destitución por sanción disciplinaria en Sumario Administrativo #' . $sumario->id,
                    'fecha_efectiva'     => $datosSancion['fecha_efectiva'] ?? now()->toDateString(),
                ]);
            }

            DB::commit();

            return $sumario;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * El sumario administrativo es el procedimiento de la LOSEP. A un obrero
     * bajo Código del Trabajo no se le destituye por esta vía: la terminación
     * con justa causa se tramita como visto bueno ante el Inspector del
     * Trabajo (Art. 172). Se valida al entrar, antes de abrir la transacción,
     * para no dejar el sumario a medio resolver ni devolver un mensaje que
     * hable de "tipo de nombramiento" sin decir cuál es la vía correcta.
     */
    private function assertSancionAplicableAlRegimen(Sumario $sumario, string $tipoSancion): void
    {
        if ($tipoSancion !== TipoSancion::DESTITUCION->value) {
            return;
        }

        $servidor = Servidor::with('contratoVigente')->findOrFail($sumario->servidor_id);
        $nombramiento = $servidor->contratoVigente?->tipo_nombramiento;

        if ($nombramiento === TipoNombramiento::CODIGO_TRABAJO) {
            throw new ReglaNegocioException(
                'Los obreros bajo Código del Trabajo no se destituyen por sumario administrativo. '
                    .'Tramite un visto bueno ante el Inspector del Trabajo desde el módulo Disciplinario.'
            );
        }
    }

    public function controlarPlazosLegales(): void
    {
        $hoy = Carbon::today();

        // 1. Control de Notificación: 3 días hábiles desde apertura
        $sumariosSinNotificar = Sumario::where('estado', EstadoSumario::ABIERTO)
            ->where('notificado_sn', false)
            ->get();

        foreach ($sumariosSinNotificar as $sumario) {
            $fechaLimiteNotificacion = $this->calcularDiasHabiles(Carbon::parse($sumario->fecha_apertura), 3);
            if ($hoy->gt($fechaLimiteNotificacion)) {
                Log::warning("Sumario #{$sumario->id} ha excedido el plazo legal de notificación de 3 días hábiles. Fecha límite era: {$fechaLimiteNotificacion->toDateString()}");
            }
        }

        // 2. Control de Resolución: 10 días hábiles desde el informe
        $sumariosConInforme = Sumario::where('estado', EstadoSumario::CON_INFORME)
            ->whereNotNull('fecha_informe')
            ->get();

        foreach ($sumariosConInforme as $sumario) {
            $fechaLimiteResolucion = $this->calcularDiasHabiles(Carbon::parse($sumario->fecha_informe), 10);
            if ($hoy->gt($fechaLimiteResolucion)) {
                Log::error("ALERTA LEGAL: Sumario #{$sumario->id} ha excedido el plazo de resolución de 10 días hábiles desde el informe. Fecha límite era: {$fechaLimiteResolucion->toDateString()}. Riesgo de caducidad.");
            }
        }
    }
}
