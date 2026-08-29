<?php

namespace App\Http\Controllers\Dispensario;

use App\Enums\EstadoPostulante;
use App\Enums\Permiso;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Enums\TipoProcesoConvocatoria;
use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreSolicitudCertificacionLoteRequest;
use App\Http\Requests\Dispensario\StoreSolicitudSignosVitalesRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Dispensario\SolicitudConstantesVitales;
use App\Models\Expediente\Servidor;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\Onboarding;
use App\Services\Expediente\MovimientoPersonalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SolicitudCertificacionController extends Controller
{
    public function __construct(
        private readonly MovimientoPersonalService $movimientoPersonalService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = SolicitudCertificacionMedica::with([
            'servidor:id,nombre,apellido,cedula,unidad_administrativa_id',
            'servidor.unidadAdministrativa:id,nombre',
            'postulante:id,nombres,apellidos,cedula,correo',
            'convocatoria:id,codigo,titulo,puesto_id',
            'convocatoria.puesto.cargo:id,nombre',
            'convocatoria.puesto.unidadAdministrativa:id,nombre',
            'solicitadoPor:id,usuario_ti,servidor_id',
            'solicitadoPor.servidor:id,nombre,apellido',
            'constantesVitales',
        ])->orderBy('created_at', 'desc');

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('tipo_evento')) {
            $query->where('tipo_evento', $request->input('tipo_evento'));
        }

        if ($request->filled('servidor_id')) {
            $query->where('servidor_id', $request->input('servidor_id'));
        }

        if ($request->filled('origen')) {
            $query->where('origen', $request->input('origen'));
        }

        if ($request->filled('unidad_administrativa_id')) {
            $unidadId = $request->input('unidad_administrativa_id');
            $query->where(function ($q) use ($unidadId) {
                $q->whereHas('servidor', fn ($sq) => $sq->where('unidad_administrativa_id', $unidadId))
                    ->orWhereHas('convocatoria.puesto', fn ($pq) => $pq->where('unidad_administrativa_id', $unidadId));
            });
        }

        if ($request->filled('anio')) {
            $query->whereYear('created_at', $request->integer('anio'));
        }

        return ApiResponse::ok($query->paginate(
            $request->integer('per_page', 20)
        ));
    }

    /**
     * Crea solicitudes de certificación médica en lote para servidores ya
     * activos (periódica, reintegro, retiro), desde el módulo Expediente.
     */
    public function storeLote(StoreSolicitudCertificacionLoteRequest $request): JsonResponse
    {
        if (! $request->user()->can(Permiso::SOLICITAR_CERTIFICACION_MEDICA->value)) {
            return ApiResponse::error(
                'No tiene permiso para solicitar certificaciones médicas.',
                null, 403
            );
        }

        $datos = $request->validated();

        $creadas = [];
        $omitidas = [];

        \DB::transaction(function () use ($datos, $request, &$creadas, &$omitidas) {
            $servidores = Servidor::with('puesto.cargo')
                ->whereIn('id', $datos['servidor_ids'])
                ->get()
                ->keyBy('id');

            foreach ($datos['servidor_ids'] as $servidorId) {
                $servidor = $servidores->get($servidorId);

                if (! $servidor) {
                    $omitidas[] = ['servidor_id' => $servidorId, 'motivo' => 'Servidor no encontrado.'];

                    continue;
                }

                $tieneSolicitudActiva = SolicitudCertificacionMedica::where('servidor_id', $servidorId)
                    ->whereIn('estado', ['pendiente', 'en_proceso'])
                    ->exists();

                if ($tieneSolicitudActiva) {
                    $omitidas[] = ['servidor_id' => $servidorId, 'motivo' => 'Ya tiene una solicitud activa.'];

                    continue;
                }

                $solicitud = SolicitudCertificacionMedica::create([
                    'tipo_evento' => $datos['tipo_evento'],
                    'origen' => 'expediente',
                    'servidor_id' => $servidor->id,
                    'cedula_paciente' => $servidor->cedula,
                    'nombres_paciente' => trim("{$servidor->nombre} {$servidor->apellido}"),
                    'correo_paciente' => $servidor->correo_personal,
                    'puesto_solicitado' => $servidor->puesto?->cargo?->nombre,
                    'solicitado_por' => $request->user()->id,
                    'estado' => 'pendiente',
                    'fecha_limite' => $datos['fecha_limite'] ?? now()->addDays(7),
                    'observaciones' => $datos['observaciones'] ?? null,
                ]);

                $creadas[] = $solicitud;
            }
        });

        return ApiResponse::ok(
            ['creadas' => $creadas, 'omitidas' => $omitidas],
            count($creadas).' solicitud(es) creada(s), '.count($omitidas).' omitida(s).'
        );
    }

    public function show(int $id): JsonResponse
    {
        // El puesto que se evalúa puede venir de tres sitios, y el más
        // específico manda: el propio aspirante (reclutamiento express, donde
        // el contenedor no tiene puesto y cada aspirante trae el suyo), el
        // expediente del servidor (periódicas, reintegros y retiros), o la
        // convocatoria formal. Se cargan los tres para que el formulario no
        // tenga que salir a buscarlos con peticiones adicionales.
        $solicitud = SolicitudCertificacionMedica::with([
            'servidor',
            'servidor.puesto.cargo:id,nombre,codigo_ciuo',
            'servidor.puesto.unidadAdministrativa:id,nombre',
            'postulante',
            'postulante.puesto.cargo:id,nombre,codigo_ciuo',
            'postulante.puesto.unidadAdministrativa:id,nombre',
            'convocatoria.puesto.cargo:id,nombre,codigo_ciuo',
            'convocatoria.puesto.unidadAdministrativa:id,nombre',
            'solicitadoPor.servidor:id,nombre,apellido',
            'constantesVitales',
        ])->findOrFail($id);

        return ApiResponse::ok($solicitud);
    }

    /**
     * Solicitudes con signos vitales pendientes de registrar por enfermería.
     */
    public function pendientesTriaje(): JsonResponse
    {
        $solicitudes = SolicitudCertificacionMedica::with([
            'servidor:id,nombre,apellido,cedula',
            'postulante:id,nombres,apellidos,cedula',
        ])
            ->whereIn('estado', ['pendiente', 'en_proceso'])
            ->whereDoesntHave('constantesVitales')
            ->orderBy('fecha_limite')
            ->get();

        return ApiResponse::ok($solicitudes);
    }

    /**
     * Registra los signos vitales tomados por enfermería para una solicitud,
     * paso previo obligatorio antes de que el médico inicie el FEMO.
     */
    public function registrarSignosVitales(
        StoreSolicitudSignosVitalesRequest $request,
        int $id
    ): JsonResponse {
        $solicitud = SolicitudCertificacionMedica::findOrFail($id);

        $datos = $request->validated();

        $tallaMetros = $datos['talla_cm'] / 100;
        $imc = $tallaMetros > 0
            ? round($datos['peso_kg'] / ($tallaMetros ** 2), 2)
            : null;

        $constantes = SolicitudConstantesVitales::updateOrCreate(
            ['solicitud_id' => $solicitud->id],
            [
                ...$datos,
                'solicitud_id' => $solicitud->id,
                'enfermera_id' => $request->user()->id,
                'imc' => $imc,
                'registrado_en' => now(),
            ]
        );

        return ApiResponse::created(
            $constantes, 'Signos vitales registrados exitosamente.'
        );
    }

    public function iniciarProceso(
        Request $request,
        int $id
    ): JsonResponse {
        $solicitud = SolicitudCertificacionMedica::findOrFail($id);

        if ($solicitud->estado !== 'pendiente') {
            return ApiResponse::error(
                'La solicitud no está en estado pendiente.', null, 422
            );
        }

        if (! $solicitud->constantesVitales()->exists()) {
            return ApiResponse::error(
                'Debe registrarse la atención de enfermería (signos vitales) antes de iniciar el FEMO.',
                null, 422
            );
        }

        $solicitud->update(['estado' => 'en_proceso']);

        return ApiResponse::ok(
            $solicitud, 'Proceso iniciado correctamente.'
        );
    }

    public function completar(
        Request $request,
        int $id
    ): JsonResponse {
        $request->validate([
            'ficha_femo_id' => ['nullable', 'integer',
                'exists:fichas_salud_ocupacional,id'],
            'dictamen' => ['required',
                'in:apto,apto_con_restricciones,no_apto'],
            'observacion_medica' => ['nullable', 'string'],
        ]);

        $solicitud = SolicitudCertificacionMedica::findOrFail($id);

        $solicitud->update([
            'estado' => 'completada',
            'ficha_femo_id' => $request->integer('ficha_femo_id') ?: null,
            'dictamen' => $request->input('dictamen'),
            'observacion_medica' => $request->input('observacion_medica'),
        ]);

        return ApiResponse::ok(
            $solicitud, 'Solicitud completada con dictamen médico.'
        );
    }

    public function confirmarIncorporacion(
        Request $request,
        int $id
    ): JsonResponse {
        if (! $request->user()->can(Permiso::GESTIONAR_ONBOARDING->value)) {
            return ApiResponse::error(
                'No tiene permiso para confirmar incorporaciones. Esta acción corresponde a Talento Humano.',
                null, 403
            );
        }

        $solicitud = SolicitudCertificacionMedica::with([
            'postulante.convocatoria.puesto.grupoOcupacional',
            'postulante.puesto',
        ])->findOrFail($id);

        if (! $solicitud->postulante || $solicitud->servidor_id) {
            return ApiResponse::error(
                'Esta solicitud no corresponde a un proceso de incorporación de candidato.',
                null, 422
            );
        }

        if ($solicitud->dictamen !== 'apto' &&
            $solicitud->dictamen !== 'apto_con_restricciones') {
            return ApiResponse::error(
                'El candidato no tiene dictamen de aptitud médica.',
                null, 422
            );
        }

        if ($solicitud->estado !== 'completada') {
            return ApiResponse::error(
                'La solicitud debe estar completada con dictamen.',
                null, 422
            );
        }

        $postulante = $solicitud->postulante;
        $convocatoria = $postulante->convocatoria;

        if (! $postulante || ! $convocatoria) {
            return ApiResponse::error(
                'No se encontró el postulante o la convocatoria.',
                null, 422
            );
        }

        // En un contenedor express el puesto lo trae el aspirante; en un
        // concurso formal lo fija la convocatoria.
        $puesto = $postulante->puestoEfectivo();

        if (! $puesto) {
            return ApiResponse::error(
                'El aspirante no tiene un puesto asignado y la convocatoria tampoco lo define.',
                null, 422
            );
        }

        \DB::beginTransaction();
        try {
            $esCandidatoInterno = $postulante->servidor_id !== null;

            if ($esCandidatoInterno) {
                // Candidato interno (la cédula ya coincidía con un Servidor
                // al inscribirse — ver PostulanteController::store()): la
                // identidad ya existe, no se crea de nuevo ni se genera
                // Onboarding (no aplica inducción para alguien que ya
                // trabaja en la institución).
                $servidor = Servidor::findOrFail($postulante->servidor_id);
            } else {
                $servidor = Servidor::create([
                    'cedula' => $postulante->cedula,
                    'nombre' => $postulante->nombres,
                    'segundo_nombre' => $postulante->segundo_nombre,
                    'apellido' => $postulante->apellidos,
                    'segundo_apellido' => $postulante->segundo_apellido,
                    'genero' => $postulante->genero,
                    'estado_civil' => $postulante->estado_civil,
                    'fecha_nacimiento' => $postulante->fecha_nacimiento?->toDateString(),
                    'tipo_sangre' => $postulante->tipo_sangre,
                    'correo_personal' => $postulante->correo,
                    'telefono_celular' => $postulante->telefono,
                    'provincia_nacimiento_id' => $postulante->provincia_nacimiento_id,
                    'canton_nacimiento_id' => $postulante->canton_nacimiento_id,
                    'puesto_id' => $puesto->id,
                    'estado' => true,
                ]);

                Onboarding::create([
                    'postulante_id' => $postulante->id,
                    'servidor_id' => $servidor->id,
                    'created_by' => $request->user()->id,
                ]);
            }

            $tipoNombramientoPropuesto = $this->resolverTipoNombramientoPropuesto($convocatoria);

            $movimiento = $this->movimientoPersonalService->registrar($servidor->id, [
                'tipo_movimiento' => TipoMovimientoPersonal::INGRESO->value,
                'descripcion' => "Incorporación tras proceso de selección {$convocatoria->codigo}. Dictamen médico: {$solicitud->dictamen}.",
                'fecha_efectiva' => now()->toDateString(),
                'tipo_nombramiento_propuesto' => $tipoNombramientoPropuesto->value,
                'puesto_destino_id' => $puesto->id,
                'unidad_destino_id' => $puesto->unidad_administrativa_id,
                'remuneracion_propuesta' => $puesto->rmu,
            ]);

            // Se enlaza la solicitud al movimiento para que el guard de
            // dictamen médico de MovimientoPersonalStateService encuentre este
            // dictamen (ya emitido) en vez de abrir una segunda solicitud al
            // suscribirse el ingreso.
            $solicitud->update([
                'servidor_id'            => $servidor->id,
                'movimiento_personal_id' => $movimiento->id,
            ]);

            $postulante->update([
                'estado' => EstadoPostulante::INCORPORADO,
            ]);

            \DB::commit();

            $mensajeIdentidad = $esCandidatoInterno
                ? 'Candidato interno identificado con su expediente existente.'
                : 'Identidad del servidor creada.';

            // Un candidato interno conserva su vínculo vigente, y el ingreso ya
            // no lo cierra por su cuenta: Talento Humano debe registrar antes la
            // Cesación de Funciones del puesto actual (ver
            // MovimientoPersonalStateService::assertSinVinculoVigente()).
            $mensajeCesacion = $esCandidatoInterno && $servidor->contratoVigente()->exists()
                ? ' Como mantiene un vínculo vigente, primero debe registrarse la '
                    .'Cesación de Funciones de su puesto actual; recién entonces podrá '
                    .'registrarse este Ingreso y Vinculación.'
                : '';

            return ApiResponse::ok(
                ['servidor_id' => $servidor->id, 'movimiento_id' => $movimiento->id],
                "{$mensajeIdentidad} El ingreso quedó registrado en borrador (código "
                    .$movimiento->id.') y requiere revisión y aprobación de Talento Humano en el '
                    .'módulo de Expediente / Movimientos antes de quedar vinculado formalmente.'
                    .$mensajeCesacion
            );
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    /**
     * Formal: único resultado legal posible es Nombramiento Permanente
     * (confirmado con Talento Humano — no se infiere nada del Puesto).
     * Express: el tipo lo declaró Talento Humano al abrir el proceso
     * (Convocatoria::tipo_nombramiento_previsto), tampoco se deriva del
     * Puesto. Talento Humano debe revisar este dato propuesto antes de
     * registrar el movimiento — si no corresponde, puede anular este
     * borrador y registrar el ingreso manualmente con el tipo correcto.
     */
    private function resolverTipoNombramientoPropuesto(Convocatoria $convocatoria): TipoNombramiento
    {
        return match ($convocatoria->tipo_proceso) {
            TipoProcesoConvocatoria::FORMAL => TipoNombramiento::PERMANENTE,
            TipoProcesoConvocatoria::EXPRESS => $convocatoria->tipo_nombramiento_previsto
                ?? throw new ReglaNegocioException(
                    'La convocatoria express no tiene un tipo de nombramiento previsto definido.'
                ),
        };
    }
}
