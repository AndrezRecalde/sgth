<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Seleccion\SeleccionServiceInterface;
use App\Enums\AptitudMedica;
use App\Enums\EstadoPostulante;
use App\Enums\Permiso;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Enums\TipoProcesoConvocatoria;
use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\CancelarSolicitudCertificacionRequest;
use App\Http\Requests\Dispensario\StoreSolicitudCertificacionLoteRequest;
use App\Http\Requests\Dispensario\StoreSolicitudSignosVitalesRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Dispensario\SolicitudConstantesVitales;
use App\Models\Expediente\Servidor;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\Onboarding;
use App\Services\Dispensario\TriajeService;
use App\Services\Expediente\MovimientoPersonalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SolicitudCertificacionController extends Controller
{
    public function __construct(
        private readonly MovimientoPersonalService $movimientoPersonalService,
        private readonly SeleccionServiceInterface $seleccionService,
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
            'solicitadoPor:id,usuario_ti,email,servidor_id',
            'solicitadoPor.servidor:id,nombre,apellido',
            $this->constantesVisiblesPara($request),
            // La aptitud y sus restricciones son lo que el Expediente necesita
            // para ubicar a alguien en su puesto, y hasta ahora no viajaban.
            // Se enumeran las columnas a propósito: `observaciones` es el texto
            // clínico que escribe quien evalúa, y la UATH acordó el 2026-09-26
            // que no forma parte del expediente administrativo.
            'fichaSaludOcupacional:id,aptitud,restricciones,fecha_evaluacion',
        ])
            // created_at es timestamp(0): sin desempate por id, dos páginas
            // del mismo resultado pueden solaparse.
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        // «activas» es la bandeja de quien evalúa: lo pendiente y lo que ya
        // empezó. Arrancar en «pendiente» escondía justo las evaluaciones en
        // curso, que son las que hay que continuar.
        if ($request->input('estado') === 'activas') {
            $query->whereIn('estado', ['pendiente', 'en_proceso']);
        } elseif ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('buscar')) {
            $termino = '%'.mb_strtolower(trim((string) $request->input('buscar'))).'%';
            $query->where(fn ($q) => $q
                ->where('cedula_paciente', 'like', $termino)
                ->orWhereRaw('LOWER(nombres_paciente) LIKE ?', [$termino]));
        }

        // La bandeja se ordena por lo que vence primero; los demás listados,
        // por lo más reciente.
        if ($request->input('orden') === 'fecha_limite') {
            $query->reorder()
                ->orderByRaw('fecha_limite IS NULL')
                ->orderBy('fecha_limite')
                ->orderBy('id');
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
            // Un rango y no `whereYear`, que no aprovecha el índice.
            $anio = $request->integer('anio');
            $query->whereBetween('created_at', ["{$anio}-01-01 00:00:00", "{$anio}-12-31 23:59:59"]);
        }

        return ApiResponse::ok($query->paginate(
            min(max($request->integer('per_page', 20), 1), 100)
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

            // Una sola consulta para todo el lote; antes era una por servidor.
            $conSolicitudActiva = SolicitudCertificacionMedica::whereIn('servidor_id', $datos['servidor_ids'])
                ->whereIn('estado', ['pendiente', 'en_proceso'])
                ->pluck('servidor_id')
                ->flip();

            foreach ($datos['servidor_ids'] as $servidorId) {
                $servidor = $servidores->get($servidorId);

                if (! $servidor) {
                    $omitidas[] = ['servidor_id' => $servidorId, 'motivo' => 'Servidor no encontrado.'];

                    continue;
                }

                if (! $servidor->estado) {
                    $omitidas[] = ['servidor_id' => $servidorId, 'motivo' => 'El servidor no está activo.'];

                    continue;
                }

                if ($conSolicitudActiva->has($servidorId)) {
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

    /**
     * Retira una solicitud pedida por error, sin borrarla.
     *
     * Solo una `pendiente`: en cuanto quien evalúa la inicia hay un FEMO en
     * curso, y tirarlo desde Talento Humano lo dejaría huérfano. Es la misma
     * frontera que `anular-permiso-pendiente` en Asistencia.
     */
    public function cancelar(
        CancelarSolicitudCertificacionRequest $request,
        int $id
    ): JsonResponse {
        if (! $request->user()->can(Permiso::SOLICITAR_CERTIFICACION_MEDICA->value)) {
            return ApiResponse::error(
                'No tiene permiso para cancelar solicitudes de certificación médica.',
                null, 403
            );
        }

        // Bloqueada: el médico puede estar iniciándola en el mismo instante.
        return DB::transaction(function () use ($request, $id) {
            $solicitud = SolicitudCertificacionMedica::lockForUpdate()->findOrFail($id);

            if ($solicitud->estado !== 'pendiente') {
                return ApiResponse::error(
                    $solicitud->estado === 'en_proceso'
                        ? 'La evaluación ya está en curso: solo quien la atiende puede cerrarla con un dictamen.'
                        : 'Solo se pueden cancelar solicitudes pendientes.',
                    null, 422
                );
            }

            $solicitud->update([
                'estado' => 'cancelada',
                'cancelada_en' => now(),
                'cancelada_por' => $request->user()->id,
                'motivo_cancelacion' => $request->validated()['motivo'],
            ]);

            // Un candidato de Reclutamiento se quedaba «en evaluación médica»
            // sin que nadie lo fuera a evaluar (2026-10-05).
            if ($solicitud->postulante_id && ! $solicitud->servidor_id) {
                $this->seleccionService->devolverPorCancelacion($solicitud->postulante_id);

                return ApiResponse::ok(
                    $solicitud, 'Solicitud cancelada. El candidato vuelve a Reclutamiento, de donde se le puede enviar otra vez.'
                );
            }

            return ApiResponse::ok(
                $solicitud, 'Solicitud cancelada. El servidor vuelve a quedar disponible para una nueva.'
            );
        });
    }

    /**
     * Qué ve cada quien del triaje de enfermería.
     *
     * Al Dispensario, todo. A Talento Humano, solo que se tomaron y cuándo: la
     * presión, la glucosa y las observaciones de la enfermera son datos
     * clínicos, y la UATH acordó el 2026-09-26 que no entran al expediente
     * administrativo. La bandeja solo necesita saber si ya están.
     */
    private function constantesVisiblesPara(Request $request): string
    {
        $clinico = $request->user()?->hasAnyRole(['medico', 'enfermera', 'admin-dispensario']);

        return $clinico
            ? 'constantesVitales'
            : 'constantesVitales:id,solicitud_id,registrado_en';
    }

    public function show(Request $request, int $id): JsonResponse
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
            // La discapacidad del Expediente, para que el FEMO nazca con ella
            // en vez de pedírsela otra vez al médico. Solo el porcentaje.
            'servidor.discapacidades:id,servidor_id,porcentaje',
            'postulante',
            'postulante.puesto.cargo:id,nombre,codigo_ciuo',
            'postulante.puesto.unidadAdministrativa:id,nombre',
            'convocatoria.puesto.cargo:id,nombre,codigo_ciuo',
            'convocatoria.puesto.unidadAdministrativa:id,nombre',
            'solicitadoPor.servidor:id,nombre,apellido',
            $this->constantesVisiblesPara($request),
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
            // Solo las pendientes: a una `en_proceso` el registro la rechaza
            // con 422, así que ofrecerla aquí era mandar a la enfermera a
            // llenar un formulario que no se iba a guardar.
            ->where('estado', 'pendiente')
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
        $datos = $request->validated();

        // Con la solicitud bloqueada, igual que `iniciarProceso`: sin el
        // bloqueo, el médico podía iniciar el FEMO entre la comprobación del
        // estado y la escritura, y la ficha copiaba unos signos que acto
        // seguido se reescribían.
        [$constantes, $esNueva] = DB::transaction(function () use ($id, $datos, $request) {
            $solicitud = SolicitudCertificacionMedica::lockForUpdate()->findOrFail($id);

            // Los signos se corrigen mientras la solicitud espera al médico.
            // Una vez iniciada, el FEMO ya los copió.
            if ($solicitud->estado !== 'pendiente') {
                throw new ReglaNegocioException(
                    'Los signos vitales solo se registran o corrigen antes de que el médico inicie la evaluación.'
                );
            }

            $constantes = SolicitudConstantesVitales::updateOrCreate(
                ['solicitud_id' => $solicitud->id],
                [
                    ...$datos,
                    'solicitud_id' => $solicitud->id,
                    'enfermera_id' => $request->user()->id,
                    'imc' => TriajeService::imc($datos['peso_kg'], $datos['talla_cm']),
                    'registrado_en' => now(),
                ]
            );

            return [$constantes, $constantes->wasRecentlyCreated];
        });

        // Corregir no es crear: 200 con su propio mensaje.
        return $esNueva
            ? ApiResponse::created($constantes, 'Signos vitales registrados exitosamente.')
            : ApiResponse::ok($constantes, 'Signos vitales corregidos.');
    }

    public function iniciarProceso(
        Request $request,
        int $id
    ): JsonResponse {
        // Con la fila bloqueada: Talento Humano puede estar cancelándola en
        // el mismo instante, y sin el bloqueo las dos guardas pasaban.
        return DB::transaction(function () use ($id) {
            $solicitud = SolicitudCertificacionMedica::lockForUpdate()->findOrFail($id);

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
        });
    }

    /**
     * Emite el dictamen y cierra la solicitud.
     *
     * El dictamen no lo elige nadie aquí: es la aptitud que el médico marcó en
     * la sección L de la ficha FEMO de esta misma solicitud, y la observación
     * son sus restricciones. Antes se pedían por separado, con tres opciones
     * contra las cuatro de la ficha, y podían contradecirse: Talento Humano
     * incorporaba con un «apto» y el certificado imprimía «no apto».
     *
     * Después del dictamen la ficha queda cerrada (ver `FemoService::actualizar`).
     */
    public function completar(
        Request $request,
        int $id
    ): JsonResponse {
        return DB::transaction(function () use ($id, $request) {
            $solicitud = SolicitudCertificacionMedica::lockForUpdate()->findOrFail($id);

            if ($solicitud->estado !== 'en_proceso') {
                return ApiResponse::error(
                    $solicitud->estado === 'completada'
                        ? 'Esta solicitud ya tiene su dictamen emitido.'
                        : 'Solo se emite el dictamen de una evaluación en curso.',
                    null, 422
                );
            }

            $ficha = $solicitud->ficha_femo_id
                ? FichaSaludOcupacional::find($solicitud->ficha_femo_id)
                : null;

            if (! $ficha) {
                return ApiResponse::error(
                    'Guarde la ficha FEMO antes de emitir el dictamen.', null, 422
                );
            }

            if (! $ficha->aptitud) {
                return ApiResponse::error(
                    'Elija la aptitud médica (sección L) antes de emitir el dictamen.',
                    ['aptitud' => ['Elija la aptitud médica.']], 422
                );
            }

            $exigeDetalle = in_array(
                $ficha->aptitud,
                [AptitudMedica::APTO_CON_RESTRICCIONES, AptitudMedica::NO_APTO],
                true,
            );
            if ($exigeDetalle && blank($ficha->restricciones)) {
                return ApiResponse::error(
                    'Describa las restricciones o el motivo en la sección L antes de emitir el dictamen.',
                    ['restricciones' => ['Requerido para esta aptitud.']], 422
                );
            }

            $solicitud->update([
                'estado' => 'completada',
                'dictamen' => $ficha->aptitud->value,
                'observacion_medica' => $ficha->restricciones,
            ]);

            // Un candidato no apto queda descalificado (decisión de TH,
            // 2026-10-04): antes seguía «en evaluación médica» para siempre y
            // el concurso formal no podía cerrarse ni cubrir su vacante.
            if ($ficha->aptitud === AptitudMedica::NO_APTO && $solicitud->postulante_id && ! $solicitud->servidor_id) {
                $this->seleccionService->descalificarPorNoApto(
                    $solicitud->postulante_id, $request->user()->id
                );
            }

            return ApiResponse::ok(
                $solicitud, 'Dictamen emitido: '.$ficha->aptitud->etiqueta().'.'
            );
        });
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

        if (! AptitudMedica::tryFrom((string) $solicitud->dictamen)?->habilitaIncorporacion()) {
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
            // Dos clics seguidos pasaban los dos la guarda de `servidor_id`, que
            // se leyó sin bloquear: con un candidato interno nacían dos
            // ingresos en borrador. Se relee la fila bloqueada.
            $bloqueada = SolicitudCertificacionMedica::lockForUpdate()->findOrFail($solicitud->id);
            if ($bloqueada->servidor_id) {
                \DB::rollBack();

                return ApiResponse::error(
                    'La incorporación de este candidato ya fue confirmada.', null, 422
                );
            }

            // La cédula se busca otra vez aquí, no solo al inscribir: si la
            // persona pasó a ser servidor después —la incorporó otro proceso,
            // o un contenedor express mientras seguía en este concurso—,
            // Servidor::create chocaba con la unique de la cédula y daba 500.
            if ($postulante->servidor_id === null) {
                $existente = Servidor::where('cedula', $postulante->cedula)->value('id');
                if ($existente) {
                    $postulante->servidor_id = $existente;
                    $postulante->save();
                }
            }

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

            // La ficha de ingreso se hizo al postulante; ahora que la persona
            // tiene expediente, la ficha aparece también en su historial.
            if ($solicitud->ficha_femo_id) {
                FichaSaludOcupacional::whereKey($solicitud->ficha_femo_id)
                    ->update(['servidor_id' => $servidor->id]);
            }

            $postulante->update([
                'estado' => EstadoPostulante::INCORPORADO,
            ]);

            // En un concurso formal, incorporar al último ganador lo cierra
            // (2026-10-04): ya no hay «Declarar ganador oficial».
            $finalizada = $this->seleccionService->cerrarConcursoSiCorresponde(
                $convocatoria->id, $request->user()->id
            ) !== null;

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
                    .($finalizada ? ' Era el último ganador: la convocatoria quedó finalizada.' : '')
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
