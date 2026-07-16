<?php

namespace App\Http\Controllers\Dispensario;

use App\Enums\EstadoPostulante;
use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreSolicitudCertificacionLoteRequest;
use App\Http\Requests\Dispensario\StoreSolicitudSignosVitalesRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Dispensario\SolicitudConstantesVitales;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Seleccion\Onboarding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SolicitudCertificacionController extends Controller
{
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
        $solicitud = SolicitudCertificacionMedica::with([
            'servidor',
            'postulante',
            'convocatoria.puesto.cargo',
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
            'postulante.convocatoria.puesto',
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

        \DB::beginTransaction();
        try {
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
                'puesto_id' => $convocatoria->puesto_id,
                'estado' => true,
            ]);

            Onboarding::create([
                'postulante_id' => $postulante->id,
                'servidor_id' => $servidor->id,
                'created_by' => $request->user()->id,
            ]);

            MovimientoPersonal::create([
                'servidor_id' => $servidor->id,
                'tipo_movimiento' => 'ingreso',
                'descripcion' => "Incorporación tras proceso de selección {$convocatoria->codigo}. Dictamen médico: {$solicitud->dictamen}.",
                'fecha_efectiva' => now()->toDateString(),
                'autorizado_por' => $request->user()->id,
            ]);

            $solicitud->update(['servidor_id' => $servidor->id]);

            $postulante->update([
                'estado' => EstadoPostulante::INCORPORADO,
            ]);

            \DB::commit();

            return ApiResponse::ok(
                ['servidor_id' => $servidor->id],
                'Servidor incorporado correctamente al sistema.'
            );
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }
}
