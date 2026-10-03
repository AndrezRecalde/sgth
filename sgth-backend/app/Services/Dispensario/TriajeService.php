<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\HistoriaClinicaServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\AgendaMedica;
use App\Models\Dispensario\Triaje;
use Illuminate\Support\Facades\DB;

/**
 * Registrar un triaje cambia el turno (pasa a sala) y puede abrir la historia
 * clínica. Vivía en el controlador, sin transacción y sin mirar el estado:
 * se tomaban signos a un turno cancelado o de otro día, y si Recepción lo
 * cancelaba mientras la enfermera medía, el triaje lo devolvía a «en sala».
 */
final class TriajeService
{
    /** Desde dónde tiene sentido medir: el paciente sigue esperando. */
    private const ADMITEN_TRIAJE = ['en_espera', 'en_sala'];

    public function __construct(
        private readonly HistoriaClinicaServiceInterface $historiaService
    ) {}

    public function registrar(int $agendaId, array $datos, int $enfermeraId): Triaje
    {
        return DB::transaction(function () use ($agendaId, $datos, $enfermeraId) {
            $agenda = AgendaMedica::lockForUpdate()->findOrFail($agendaId);

            if (!in_array($agenda->estado, self::ADMITEN_TRIAJE, true)) {
                throw new ReglaNegocioException(
                    'Solo se toma el triaje de un turno que sigue en espera.'
                );
            }

            if (!$agenda->fecha->isToday()) {
                throw new ReglaNegocioException(
                    'El turno es de otro día: no se le puede tomar el triaje hoy.'
                );
            }

            // La valoración se guarda con el triaje: la cola y el historial
            // deben mostrar lo que se valoró con estas cifras, no lo que diría
            // la tabla de umbrales el día que alguien consulte el registro.
            $valoracion = ValoracionSignosVitales::evaluar(
                $datos,
                $this->edadDelPaciente($agenda)
            );

            // La historia se abre aquí si el paciente aún no la tiene: la
            // columna es NOT NULL.
            $historia = $this->historiaService->paraPacienteDeTurno($agenda);

            // Cada toma es una fila nueva: rehacer el triaje no pisa la lectura
            // anterior. La vigente es la última.
            $triaje = Triaje::create([
                ...$datos,
                'agenda_medica_id'    => $agenda->id,
                'historia_clinica_id' => $historia->id,
                'enfermera_id'        => $enfermeraId,
                'imc'                 => self::imc($datos['peso_kg'], $datos['talla_cm']),
                'nivel_alerta'        => $valoracion['nivel'],
                'hallazgos_alerta'    => $valoracion['hallazgos'],
                'registrado_en'       => now(),
            ]);

            if ($agenda->estado === 'en_espera') {
                $agenda->update(['estado' => 'en_sala']);
            }

            return $triaje;
        });
    }

    /** IMC con dos decimales, o null si la talla no sirve para dividir. */
    public static function imc(float|int|string $pesoKg, float|int|string $tallaCm): ?float
    {
        $metros = ((float) $tallaCm) / 100;

        return $metros > 0 ? round(((float) $pesoKg) / ($metros ** 2), 2) : null;
    }

    /**
     * Edad del paciente del turno, sea servidor o carga familiar. Sin fecha de
     * nacimiento devuelve null, y entonces se valora como adulto: es lo que
     * más se parece a la población que atiende el dispensario.
     */
    private function edadDelPaciente(AgendaMedica $agenda): ?int
    {
        $nacimiento = $agenda->servidor_id
            ? $agenda->servidor?->fecha_nacimiento
            : $agenda->cargaFamiliar?->fecha_nacimiento;

        return $nacimiento?->age;
    }
}
