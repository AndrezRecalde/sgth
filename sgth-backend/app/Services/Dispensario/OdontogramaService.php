<?php

namespace App\Services\Dispensario;

use App\Enums\CondicionPiezaDental;
use App\Enums\DenticionTipo;
use App\Enums\ProcedimientoOdontologico;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\Odontograma;
use App\Models\Dispensario\OdontogramaPieza;
use App\Models\Dispensario\OdontogramaProcedimiento;
use App\Models\Expediente\CargaFamiliar;
use Illuminate\Support\Facades\DB;

final class OdontogramaService
{
    private const PIEZAS_PERMANENTES = [
        11, 12, 13, 14, 15, 16, 17, 18,
        21, 22, 23, 24, 25, 26, 27, 28,
        31, 32, 33, 34, 35, 36, 37, 38,
        41, 42, 43, 44, 45, 46, 47, 48,
    ];

    private const PIEZAS_TEMPORALES = [
        51, 52, 53, 54, 55,
        61, 62, 63, 64, 65,
        71, 72, 73, 74, 75,
        81, 82, 83, 84, 85,
    ];

    public function obtenerPorHistoriaClinica(int $historiaClinicaId, int $userId): Odontograma
    {
        $historiaClinica = HistoriaClinica::findOrFail($historiaClinicaId);

        $odontograma = Odontograma::where('historia_clinica_id', $historiaClinicaId)->first();

        if ($odontograma) {
            return $odontograma->load([
                'piezas' => fn ($q) => $q->orderBy('numero_pieza'),
                'piezas.procedimientos' => fn ($q) => $q->orderBy('fecha', 'desc'),
                'piezas.procedimientos.realizadoPor:id,usuario_ti,email,servidor_id',
                'piezas.procedimientos.realizadoPor.servidor:id,nombre,apellido',
                'piezas.procedimientos.anuladoPor:id,usuario_ti,email,servidor_id',
                'piezas.procedimientos.anuladoPor.servidor:id,nombre,apellido',
            ]);
        }

        return DB::transaction(function () use ($historiaClinica, $userId) {
            $odontograma = Odontograma::create([
                'historia_clinica_id' => $historiaClinica->id,
                'created_by' => $userId,
            ]);

            $this->sembrarPiezas($odontograma, self::PIEZAS_PERMANENTES, DenticionTipo::PERMANENTE);

            if ($this->esMenorDeEdad($historiaClinica)) {
                $this->sembrarPiezas($odontograma, self::PIEZAS_TEMPORALES, DenticionTipo::TEMPORAL);
            }

            return $odontograma->load(['piezas' => fn ($q) => $q->orderBy('numero_pieza')]);
        });
    }

    public function registrarProcedimiento(array $datos, int $userId): OdontogramaProcedimiento
    {
        return DB::transaction(function () use ($datos, $userId) {
            $pieza = OdontogramaPieza::findOrFail($datos['odontograma_pieza_id']);

            $this->verificarQueLaConsultaEsDelMismoPaciente(
                $pieza, $datos['consulta_medica_id'] ?? null
            );

            $procedimiento = OdontogramaProcedimiento::create([
                'odontograma_pieza_id' => $pieza->id,
                'consulta_medica_id' => $datos['consulta_medica_id'] ?? null,
                'procedimiento' => $datos['procedimiento'],
                'superficie' => $datos['superficie'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
                'realizado_por' => $userId,
                'fecha' => $datos['fecha'] ?? now()->toDateString(),
                'created_by' => $userId,
            ]);

            // Por el mismo camino que la anulación, y no fijando aquí la
            // condición del procedimiento recién creado: si el odontólogo
            // carga uno atrasado —una resina del mes pasado que faltaba por
            // registrar—, la pieza no debe perder la corona que se le puso
            // esta mañana. Manda el último procedimiento vigente por fecha,
            // no el último que se escribió.
            $this->recalcularCondicionPieza($pieza, $userId);

            return $procedimiento->load([
                'realizadoPor:id,usuario_ti,email,servidor_id',
                'realizadoPor.servidor:id,nombre,apellido',
            ]);
        });
    }

    public function historialPorPieza(int $piezaId)
    {
        $pieza = OdontogramaPieza::findOrFail($piezaId);

        return $pieza->procedimientos()
            ->with([
                'realizadoPor:id,usuario_ti,email,servidor_id',
                'realizadoPor.servidor:id,nombre,apellido',
                'anuladoPor:id,usuario_ti,email,servidor_id',
                'anuladoPor.servidor:id,nombre,apellido',
            ])
            ->get();
    }

    /**
     * Anula un procedimiento ya registrado (corrección de un error del
     * odontólogo). El controller ya validó que quien anula es el mismo
     * profesional que lo registró y que corresponde a la consulta actual.
     */
    public function anularProcedimiento(
        OdontogramaProcedimiento $procedimiento,
        string $motivo,
        int $userId
    ): OdontogramaProcedimiento {
        return DB::transaction(function () use ($procedimiento, $motivo, $userId) {
            $procedimiento->update([
                'anulado_en' => now(),
                'anulado_por' => $userId,
                'motivo_anulacion' => $motivo,
            ]);

            $this->recalcularCondicionPieza($procedimiento->odontogramaPieza, $userId);

            return $procedimiento->load([
                'realizadoPor:id,usuario_ti,email,servidor_id',
                'realizadoPor.servidor:id,nombre,apellido',
                'anuladoPor:id,usuario_ti,email,servidor_id',
                'anuladoPor.servidor:id,nombre,apellido',
            ]);
        });
    }

    /**
     * Recalcula la condición vigente de una pieza a partir del último
     * procedimiento no anulado. Si no queda ninguno, vuelve a "sano".
     */
    private function recalcularCondicionPieza(OdontogramaPieza $pieza, int $userId): void
    {
        $ultimoVigente = $pieza->procedimientos()
            ->whereNull('anulado_en')
            ->orderBy('fecha', 'desc')
            ->orderBy('created_at', 'desc')
            // Dos procedimientos del mismo día pueden compartir `created_at`
            // al segundo; sin este desempate la pieza quedaría dibujada según
            // el orden que devolviera la base de datos.
            ->orderBy('id', 'desc')
            ->first();

        $pieza->update([
            'condicion' => $ultimoVigente
                ? $ultimoVigente->procedimiento->condicionResultante()
                : CondicionPiezaDental::SANO,
            'updated_by' => $userId,
        ]);
    }

    /**
     * La consulta que se anota en el procedimiento tiene que ser del mismo
     * paciente que la pieza.
     *
     * El endpoint recibe el id de la pieza y el de la consulta por separado y
     * no los cruzaba, así que un cliente desincronizado —la pantalla de un
     * paciente abierta mientras se atiende a otro— podía dejar un procedimiento
     * de una boca colgando de la consulta de otra persona. Además de ensuciar
     * la historia, eso rompe la corrección: quien anula compara justamente por
     * consulta.
     */
    private function verificarQueLaConsultaEsDelMismoPaciente(
        OdontogramaPieza $pieza,
        ?int $consultaId,
    ): void {
        if ($consultaId === null) {
            return;
        }

        $historiaDeLaPieza = $pieza->odontograma()->value('historia_clinica_id');
        $historiaDeLaConsulta = ConsultaMedica::whereKey($consultaId)
            ->value('historia_clinica_id');

        if ($historiaDeLaConsulta !== $historiaDeLaPieza) {
            throw new ReglaNegocioException(
                'La consulta indicada no corresponde al paciente de este ' .
                'odontograma.'
            );
        }
    }

    private function sembrarPiezas(Odontograma $odontograma, array $numeros, DenticionTipo $denticion): void
    {
        foreach ($numeros as $numero) {
            OdontogramaPieza::create([
                'odontograma_id' => $odontograma->id,
                'numero_pieza' => $numero,
                'denticion' => $denticion,
                'condicion' => CondicionPiezaDental::SANO,
            ]);
        }
    }

    private function esMenorDeEdad(HistoriaClinica $historiaClinica): bool
    {
        $propietario = $historiaClinica->propietario();

        if (! $propietario instanceof CargaFamiliar || ! $propietario->fecha_nacimiento) {
            return false;
        }

        return $propietario->fecha_nacimiento->age < 18;
    }
}
