<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Dispensario\HistoriaClinicaServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreTriajeRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\AgendaMedica;
use App\Models\Dispensario\Triaje;
use App\Services\Dispensario\ValoracionSignosVitales;
use Illuminate\Http\JsonResponse;

class TriajeController extends Controller
{
    public function __construct(
        private readonly HistoriaClinicaServiceInterface $historiaService
    ) {}

    public function store(
        StoreTriajeRequest $request,
        int $agendaId
    ): JsonResponse {
        $agenda = AgendaMedica::findOrFail($agendaId);

        $datos = $request->validated();

        // Calcular IMC con peso y talla
        $tallaMetros = $datos['talla_cm'] / 100;
        $imc = $tallaMetros > 0
            ? round($datos['peso_kg'] / ($tallaMetros ** 2), 2)
            : null;

        // La valoración se guarda con el triaje: la cola y el historial deben
        // mostrar lo que se valoró con estas cifras, no lo que diría la tabla
        // de umbrales el día que alguien consulte el registro.
        $valoracion = ValoracionSignosVitales::evaluar(
            $datos,
            $this->edadDelPaciente($agenda)
        );

        // Cada toma es una fila nueva. Antes esto era un `updateOrCreate` sobre
        // la agenda: rehacer el triaje pisaba la lectura anterior y nadie podía
        // saber que había existido. Un turno puede tener varias tomas —una
        // corrección de digitación, o una segunda medición tras la espera— y
        // todas quedan; la vigente es la última.
        // La historia se abre aquí si el paciente aún no la tiene: la columna
        // es NOT NULL y antes se resolvía a null, así que un turno creado sin
        // historia mataba el guardado con un error de base de datos.
        $historia = $this->historiaService->paraPacienteDeTurno($agenda);

        $triaje = Triaje::create([
            ...$datos,
            'agenda_medica_id'    => $agenda->id,
            'historia_clinica_id' => $historia->id,
            'enfermera_id'        => $request->user()->id,
            'imc'                 => $imc,
            'nivel_alerta'        => $valoracion['nivel'],
            'hallazgos_alerta'    => $valoracion['hallazgos'],
            'registrado_en'       => now(),
        ]);

        if ($agenda->estado === 'en_espera') {
            $agenda->update(['estado' => 'en_sala']);
        }

        return ApiResponse::created(
            $triaje, 'Triaje registrado exitosamente.'
        );
    }

    /** La toma vigente del turno, que es la última registrada. */
    public function show(int $agendaId): JsonResponse
    {
        $triaje = Triaje::where('agenda_medica_id', $agendaId)
            ->latest('id')
            ->firstOrFail();

        return ApiResponse::ok($triaje);
    }

    /**
     * Todas las tomas del turno, de la más antigua a la más reciente, con quién
     * las registró. Es lo que permite ver que una lectura se corrigió y con qué
     * cifras estaba antes.
     */
    public function historial(int $agendaId): JsonResponse
    {
        $tomas = Triaje::where('agenda_medica_id', $agendaId)
            // `email` entra en el select aunque no se muestre: el accesor
            // `nombre_completo` de User cae a él cuando el usuario no tiene
            // servidor, y si no se cargó devuelve null contra su propio tipo
            // declarado. El servidor va por lo mismo, para el caso normal.
            ->with([
                'enfermera:id,usuario_ti,email,servidor_id',
                'enfermera.servidor:id,nombre,apellido',
            ])
            ->orderBy('id')
            ->get();

        return ApiResponse::ok($tomas);
    }

    public function ultimoPorAgenda(int $agendaId): JsonResponse
    {
        $agenda = AgendaMedica::findOrFail($agendaId);

        $historiaClinicaId = $this->resolverHistoriaClinicaId($agenda);

        if (!$historiaClinicaId) {
            return ApiResponse::ok(null);
        }

        $ultimoTriaje = Triaje::where(
            'historia_clinica_id', $historiaClinicaId
        )
            ->where('agenda_medica_id', '!=', $agendaId)
            ->orderBy('registrado_en', 'desc')
            ->first();

        return ApiResponse::ok($ultimoTriaje);
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

    /**
     * Solo lectura: devuelve null si el paciente no tiene historia. Registrar
     * un triaje sí la abre, pero consultar el último no debe crear nada.
     */
    private function resolverHistoriaClinicaId(
        AgendaMedica $agenda
    ): ?int {
        if ($agenda->servidor_id) {
            return \App\Models\Dispensario\HistoriaClinica::where(
                'servidor_id', $agenda->servidor_id
            )->value('id');
        }

        if ($agenda->carga_familiar_id) {
            return \App\Models\Dispensario\HistoriaClinica::where(
                'carga_familiar_id', $agenda->carga_familiar_id
            )->value('id');
        }

        return null;
    }

    public function pendientes(): JsonResponse
    {
        $turnos = AgendaMedica::with([
            'medico', 'servidor', 'cargaFamiliar.servidor',
        ])->where('estado', 'en_espera')
          ->where('requiere_triaje', true)
          // Sobre `triajes` y no sobre `triaje`: el segundo es ahora una
          // subconsulta «la última», y preguntarle si no existe no es lo mismo
          // que preguntar si el turno no tiene ninguna toma.
          ->whereDoesntHave('triajes')
          ->orderBy('registrado_en', 'asc')
          ->get();

        return ApiResponse::ok($turnos);
    }
}
