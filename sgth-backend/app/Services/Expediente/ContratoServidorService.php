<?php

namespace App\Services\Expediente;

use App\Enums\CategoriaEventoVinculo;
use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoContrato;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoEventoVinculo;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\EventoVinculo;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Spatie\Activitylog\Models\Activity;

class ContratoServidorService
{
    /**
     * Actividad laboral del servidor: cada contrato con las acciones de
     * personal que ocurrieron sobre él.
     *
     * El contrato es el instrumento legal; las acciones son hechos sobre ese
     * instrumento. Un traspaso o una sanción no crean un vínculo nuevo, así
     * que se muestran anidados bajo el contrato al que pertenecen en vez de
     * como filas sueltas.
     *
     * Ingreso y cesación quedan fuera del anidado: son el inicio y el fin del
     * propio contrato, ya visibles en la fila padre.
     */
    public function actividadLaboral(int $servidorId, ?string $fecha = null): array
    {
        $fecha = $fecha ?? now()->toDateString();

        $contratos = ContratoServidor::where('servidor_id', $servidorId)
            ->with([
                'unidadAdministrativa:id,nombre',
                'puesto.cargo:id,nombre',
                // La partida que paga es la del contrato; la del puesto solo
                // respalda a los vínculos anteriores a esa columna.
                'partidaPresupuestaria:id,codigo',
                'puesto.partidaPresupuestaria:id,codigo',
                'cubreMovimiento:id,servidor_id,tipo_movimiento,subtipo_movimiento,fecha_fin',
                'cubreMovimiento.servidor:id,nombre,apellido',
            ])
            ->orderByDesc('fecha_inicio')
            ->get();

        $acciones = MovimientoPersonal::where('servidor_id', $servidorId)
            ->whereIn('estado', [
                EstadoAccionPersonal::REGISTRADA->value,
                EstadoAccionPersonal::NOTIFICADA->value,
            ])
            ->whereNotIn('tipo_movimiento', [
                TipoMovimientoPersonal::INGRESO->value,
                TipoMovimientoPersonal::CESACION_FUNCIONES->value,
            ])
            ->with(['unidadOrigen:id,nombre', 'unidadDestino:id,nombre', 'puestoOrigen.cargo:id,nombre', 'puestoDestino.cargo:id,nombre', 'reintegro'])
            // Ascendente aquí: es la secuencia de lo que le fue pasando al
            // vínculo. El id desempata las del mismo día para que el relato no
            // cambie de orden entre recargas.
            ->orderBy('fecha_efectiva')
            ->orderBy('id')
            ->get();

        $eventos = EventoVinculo::where('servidor_id', $servidorId)
            ->with('registradoPor:id,email,servidor_id', 'registradoPor.servidor:id,nombre,apellido')
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $cambios = $this->cambiosAuditados($contratos->pluck('id')->all());

        return $contratos->map(function (ContratoServidor $contrato) use ($acciones, $eventos, $cambios, $fecha) {
            $delContrato = $acciones->filter(
                fn (MovimientoPersonal $m) => $this->ocurreDurante($m->fecha_efectiva?->toDateString(), $contrato)
            )->values();

            // El evento que nombra su contrato va con ese; el que no —la
            // constancia de una subrogación, que es del subrogante y no de un
            // contrato— va con el que estaba vigente ese día.
            $novedades = $eventos->filter(
                fn (EventoVinculo $e) => $e->contrato_servidor_id !== null
                    ? $e->contrato_servidor_id === $contrato->id
                    : $this->ocurreDurante($e->fecha?->toDateString(), $contrato)
            )->values();

            $ausencia = $delContrato->first(
                fn (MovimientoPersonal $m) => $m->esAusenciaTemporal() && $this->vigenteEn($m, $fecha)
            );

            return [
                'contrato'  => $contrato,
                'acciones'  => $delContrato->map(fn (MovimientoPersonal $m) => [
                    'id'                 => $m->id,
                    'tipo_movimiento'    => $m->tipo_movimiento?->value,
                    'subtipo_movimiento' => $m->subtipo_movimiento?->value,
                    'clase'              => $m->clase?->value,
                    // La causal cuando la hay, y si no, el nombre de la clase:
                    // el mismo que se ve en la bandeja y en el documento.
                    'etiqueta'           => $m->causal()?->etiqueta() ?? $m->etiqueta(),
                    'codigo_registro'    => $m->codigo_registro,
                    'fecha_efectiva'     => $m->fecha_efectiva?->toDateString(),
                    'fecha_inicio'       => $m->fecha_inicio?->toDateString(),
                    'fecha_fin'          => $m->fecha_fin?->toDateString(),
                    'descripcion'        => $m->descripcion,
                    'unidad_origen'      => $m->unidadOrigen?->nombre,
                    'unidad_destino'     => $m->unidadDestino?->nombre,
                    'puesto_origen'      => $m->puestoOrigen?->cargo?->nombre,
                    'puesto_destino'     => $m->puestoDestino?->cargo?->nombre,
                ])->all(),
                // La bitácora: lo que le pasó al vínculo sin ser un acto. Va
                // aparte de las acciones para que nadie la tome por una.
                'novedades' => $novedades->map(fn (EventoVinculo $e) => [
                    'id'                     => $e->id,
                    'tipo'                   => $e->tipo->value,
                    'etiqueta'               => $e->tipo->etiqueta(),
                    'fecha'                  => $e->fecha?->toDateString(),
                    'descripcion'            => $e->descripcion,
                    'movimiento_personal_id' => $e->movimiento_personal_id,
                    'registrado_por'         => $e->registradoPor ? $this->nombreDelUsuario($e->registradoPor) : null,
                ])->all(),
                // Situación derivada, no almacenada: se calcula de las acciones
                // vigentes hoy. Así nunca queda desincronizada cuando el
                // período vence — no hace falta una tarea que la apague.
                'situacion' => $ausencia ? [
                    'etiqueta' => $ausencia->etiquetaAusencia(),
                    'desde'    => $ausencia->fecha_inicio?->toDateString(),
                    'hasta'    => $ausencia->fecha_fin?->toDateString(),
                ] : null,
                // Por qué existe este contrato, cuando existe para cubrir a
                // alguien: sin esto, el expediente del suplente no explica de
                // dónde salió su vínculo sobre una plaza ya ocupada.
                'reemplaza_a' => $contrato->cubreMovimiento ? [
                    'movimiento_id' => $contrato->cubreMovimiento->id,
                    'servidor'      => trim(
                        ($contrato->cubreMovimiento->servidor?->apellido ?? '').' '
                            .($contrato->cubreMovimiento->servidor?->nombre ?? '')
                    ),
                    'etiqueta'      => $contrato->cubreMovimiento->etiquetaAusencia(),
                    'hasta'         => $contrato->cubreMovimiento->fecha_fin?->toDateString(),
                ] : null,
                'cambios' => $cambios->get($contrato->id, collect())->all(),
            ];
        })->all();
    }

    /**
     * Lo que le pasó al contrato fuera de las acciones de personal.
     *
     * Reprogramar el plazo exige un motivo escrito, se guarda con la fecha
     * anterior, la nueva y quién lo hizo — y hasta ahora nadie podía leerlo:
     * no hay pantalla ni endpoint que exponga el registro de auditoría. El
     * motivo obligatorio era una formalidad que se cobraba y no se usaba.
     *
     * Va acotado a ContratoServidor a propósito. Las acciones de personal ya
     * se ven en su propio drawer, y la sincronización de datos laborales del
     * servidor es un efecto colateral interno: incluirlos convertiría esto en
     * ruido que entierra lo único que trae una explicación humana.
     *
     * @param  list<int>  $contratoIds
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, array>>
     */
    private function cambiosAuditados(array $contratoIds): Collection
    {
        if ($contratoIds === []) {
            return collect();
        }

        return Activity::where('subject_type', ContratoServidor::class)
            ->whereIn('subject_id', $contratoIds)
            ->with('causer:id,email,servidor_id', 'causer.servidor:id,nombre,apellido')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Activity $a) => [
                'id'          => $a->id,
                'descripcion' => $a->description,
                'fecha'       => $a->created_at?->toDateTimeString(),
                'por'         => $this->nombreDelCausante($a),
                'contrato_id' => $a->subject_id,
                // Solo las reprogramaciones traen estos tres; el resto los deja
                // en null y la UI muestra únicamente la línea de la acción.
                'fecha_fin_anterior' => $a->properties['fecha_fin_anterior'] ?? null,
                'fecha_fin_nueva'    => $a->properties['fecha_fin_nueva'] ?? null,
                'motivo'             => $a->properties['motivo'] ?? null,
            ])
            ->groupBy('contrato_id');
    }

    /** El nombre del servidor detrás del usuario; su correo si no lo tiene. */
    private function nombreDelCausante(Activity $actividad): ?string
    {
        $usuario = $actividad->causer;

        return $usuario instanceof User ? $this->nombreDelUsuario($usuario) : null;
    }

    private function nombreDelUsuario(User $usuario): string
    {
        $servidor = $usuario->servidor ?? null;

        if ($servidor) {
            return trim(($servidor->apellido ?? '').' '.($servidor->nombre ?? '')) ?: $usuario->email;
        }

        return $usuario->email;
    }

    /** Una acción pertenece al contrato cuyo período contiene su fecha efectiva. */
    private function ocurreDurante(?string $fecha, ContratoServidor $contrato): bool
    {
        if (! $fecha || ! $contrato->fecha_inicio) {
            return false;
        }

        if ($fecha < $contrato->fecha_inicio->toDateString()) {
            return false;
        }

        return ! $contrato->fecha_fin || $fecha <= $contrato->fecha_fin->toDateString();
    }

    private function vigenteEn(MovimientoPersonal $movimiento, string $fecha): bool
    {
        $inicio = $movimiento->fecha_inicio?->toDateString();

        if (! $inicio || $inicio > $fecha) {
            return false;
        }

        // La que cerró un reintegro termina el día anterior al regreso.
        $fin = $movimiento->finEfectivo()?->toDateString();

        return ! $fin || $fin >= $fecha;
    }

    /**
     * @param  ?MovimientoPersonal  $movimientoOrigen  Cuando este contrato
     *   materializa un MovimientoPersonal ya formal (ingreso vía
     *   creaVinculo(), traslado/ascenso/etc. vía modificaVinculo()), se
     *   pasa aquí para que sincronizarRegimenServidor() no anote nada más —
     *   ese movimiento ya es la constancia legal de mayor jerarquía. Si es
     *   null (alta de contrato "suelta", sin acto formal previo), se anota
     *   en la bitácora del vínculo que el contrato nació sin acción.
     */
    public function crear(int $servidorId, array $data, ?MovimientoPersonal $movimientoOrigen = null)
    {
        $data['servidor_id'] = $servidorId;

        $estadoNuevo = $data['estado'] ?? 'vigente';
        $estadoNuevoVal = $estadoNuevo instanceof \App\Enums\EstadoContrato ? $estadoNuevo->value : (string)$estadoNuevo;
        if ($estadoNuevoVal === 'vigente' && !empty($data['puesto_id']) && !empty($data['tipo_nombramiento'])) {
            $this->validarVacante(
                (int) $data['puesto_id'],
                $this->valorTipoNombramiento($data['tipo_nombramiento']),
                null,
                isset($data['cubre_movimiento_id']) ? (int) $data['cubre_movimiento_id'] : null,
                // El ingreso que se está materializando ya contaba como plaza
                // reservada mientras esperaba su fecha: no compite consigo.
                $movimientoOrigen?->id,
            );
        }

        $data['fecha_fin'] = $this->resolverFechaFin($data);
        $this->validarPlazo($data);
        $data['puede_marcar'] = $this->resolverPuedeMarcar($data);

        // De qué acción nació este vínculo. Sin esto, anular el ingreso que lo
        // creó no tendría forma de saber qué contrato deshacer.
        if ($movimientoOrigen !== null) {
            $data['movimiento_origen_id'] ??= $movimientoOrigen->id;
        }

        if (isset($data['archivo_contrato'])
            && $data['archivo_contrato'] instanceof UploadedFile) {
            $data['documento_ruta'] = $data['archivo_contrato']
                ->store('expediente/contratos', 'local');
            unset($data['archivo_contrato']);
        }

        $contrato = ContratoServidor::create($data);

        // Sincronizar régimen laboral en el servidor si es vigente
        $estado = $data['estado'] ?? $contrato->estado;
        $estadoVal = $estado instanceof \App\Enums\EstadoContrato ? $estado->value : (string)$estado;
        if ($estadoVal === 'vigente') {
            $this->sincronizarRegimenServidor($servidorId, $data, $movimientoOrigen, $contrato);
        }

        return $contrato->load(['puesto.cargo', 'unidadAdministrativa']);
    }

    /**
     * Cierra un contrato vigente. Un vínculo nunca se edita para cambiar de
     * modalidad: se cierra este (fecha_fin + motivo_fin obligatorio) y se
     * crea uno nuevo con crear(). No permite reabrir ni volver a cerrar un
     * contrato que ya no está vigente.
     */
    public function cerrar(ContratoServidor $contrato, array $data): ContratoServidor
    {
        $estadoActual = $contrato->estado instanceof EstadoContrato
            ? $contrato->estado->value
            : (string) $contrato->estado;

        if ($estadoActual !== EstadoContrato::VIGENTE->value) {
            throw new ReglaNegocioException('Solo se puede cerrar un contrato vigente.');
        }

        $fechaFin = $data['fecha_fin'] ?? now()->toDateString();

        if (strtotime($fechaFin) < strtotime((string) $contrato->fecha_inicio)) {
            throw new ReglaNegocioException(
                'La fecha de fin no puede ser anterior a la fecha de inicio del contrato.'
            );
        }

        $contrato->update([
            'estado'     => EstadoContrato::TERMINADO,
            'fecha_fin'  => $fechaFin,
            'motivo_fin' => $data['motivo_fin'],
            // Qué acción lo cerró, para poder reabrirlo si se anula. Va aquí y
            // no solo dentro del texto de `motivo_fin` —«Renuncia — Acción de
            // Personal #47.»—, que no es algo contra lo que consultar.
            'movimiento_cierre_id' => $data['movimiento_cierre_id'] ?? null,
        ]);

        // Sin esto el cesado conservaba puesto, unidad y `estado = true`: el
        // contrato decía una cosa y la ficha otra.
        $this->sincronizarPuestoDesdeVinculo($contrato->servidor_id);

        return $contrato->fresh(['puesto.cargo', 'unidadAdministrativa']);
    }

    /**
     * Deshace un cierre: el contrato vuelve a estar vigente, sin fecha de fin
     * ni motivo.
     *
     * La única razón legítima para esto es que se anule la acción de personal
     * que lo cerró — el acto que la respaldaba dejó de existir, así que su
     * efecto tampoco puede quedar en pie. No es «reabrir un vínculo»: el
     * vínculo nunca se interrumpió, se había anotado un cierre que no debía
     * estar. Por eso no es una operación que la UI ofrezca por su cuenta y
     * solo la llama MovimientoPersonalStateService al anular.
     */
    /**
     * El plazo que tenía el contrato justo antes de que lo cerraran.
     *
     * `cerrar()` sobreescribe `fecha_fin` con la fecha de la cesación, así que
     * el plazo ya no está en la fila. Hasta el 2026-10-03 se recuperaba de la
     * acción de ingreso, y eso perdía cualquier prórroga posterior: un
     * contrato prorrogado al 30/06/2027 volvía al 31/12/2026 al anular su
     * cesación, sin que nadie lo notara.
     *
     * Por orden:
     * 1. lo que el cierre dejó anotado en la bitácora (`fecha_fin_previa`,
     *    desde el 2026-10-03; `null` es un plazo indefinido, no un vacío);
     * 2. para cierres anteriores, la última reprogramación registrada;
     * 3. el plazo pactado en el ingreso;
     * 4. si es de carga inicial y no tiene acción, se deriva como al darlo de
     *    alta: null para los indefinidos, fin de año para Servicios
     *    Profesionales.
     */
    private function plazoAntesDelCierre(ContratoServidor $contrato): ?string
    {
        $bitacora = Activity::where('subject_type', ContratoServidor::class)
            ->where('subject_id', $contrato->id)
            ->orderByDesc('id');

        $cierre = (clone $bitacora)->where('description', 'Contrato cerrado')->first();
        if ($cierre && $cierre->properties->has('fecha_fin_previa')) {
            return $cierre->properties->get('fecha_fin_previa');
        }

        $reprogramacion = (clone $bitacora)
            ->where('description', 'Plazo del contrato reprogramado')
            ->first();
        if ($reprogramacion) {
            return $reprogramacion->properties->get('fecha_fin_nueva');
        }

        return $contrato->movimientoOrigen?->fecha_fin_propuesta?->toDateString()
            ?? $this->resolverFechaFin([
                'tipo_nombramiento' => $contrato->tipo_nombramiento,
                'fecha_inicio'      => $contrato->fecha_inicio?->toDateString(),
            ]);
    }

    public function reabrirPorAnulacion(ContratoServidor $contrato): ContratoServidor
    {
        $contrato->update([
            'estado'               => EstadoContrato::VIGENTE,
            'fecha_fin'            => $this->plazoAntesDelCierre($contrato),
            'motivo_fin'           => null,
            'movimiento_cierre_id' => null,
        ]);

        $this->sincronizarPuestoDesdeVinculo($contrato->servidor_id);

        return $contrato->fresh(['puesto.cargo', 'unidadAdministrativa']);
    }

    /**
     * Los contratos de Servicios Profesionales duran un año calendario, o lo
     * que reste de él si se contrata a mitad de año: contratado en julio,
     * termina igualmente el 31 de diciembre (regla de Talento Humano). Se
     * deriva aquí para que el vencimiento exista desde el alta y la tarea que
     * detecta contratos vencidos tenga contra qué comparar.
     *
     * Una fecha_fin explícita gana: si Talento Humano la fija, se respeta.
     */
    private function resolverFechaFin(array $data): ?string
    {
        if (!empty($data['fecha_fin'])) {
            return $data['fecha_fin'] instanceof \DateTimeInterface
                ? $data['fecha_fin']->format('Y-m-d')
                : (string) $data['fecha_fin'];
        }

        $tipo = isset($data['tipo_nombramiento'])
            ? $this->valorTipoNombramiento($data['tipo_nombramiento'])
            : null;

        if ($tipo !== TipoNombramiento::SERVICIOS_PROFESIONALES->value || empty($data['fecha_inicio'])) {
            return $data['fecha_fin'] ?? null;
        }

        $inicio = $data['fecha_inicio'] instanceof \DateTimeInterface
            ? $data['fecha_inicio']
            : new \DateTimeImmutable((string) $data['fecha_inicio']);

        return $inicio->format('Y').'-12-31';
    }

    /**
     * Reprograma el plazo de un vínculo vigente: una prórroga, o la corrección
     * de una fecha mal digitada al darlo de alta.
     *
     * Es lo único editable de un contrato ya creado. No cambia la modalidad ni
     * el puesto —eso exige cerrar el vínculo y crear otro bajo una acción de
     * personal— y no toca contratos terminados, cuya fecha de fin ya es un
     * hecho histórico.
     */
    public function reprogramarPlazo(ContratoServidor $contrato, array $datos): ContratoServidor
    {
        $estado = $contrato->estado instanceof EstadoContrato
            ? $contrato->estado->value
            : (string) $contrato->estado;

        if ($estado !== EstadoContrato::VIGENTE->value) {
            throw new ReglaNegocioException(
                'Solo se puede reprogramar el plazo de un contrato vigente.'
            );
        }

        $nuevaFechaFin = $datos['fecha_fin'] ?? null;

        $this->validarPlazo([
            'tipo_nombramiento'   => $contrato->tipo_nombramiento,
            'fecha_inicio'        => $contrato->fecha_inicio?->toDateString(),
            'fecha_fin'           => $nuevaFechaFin,
            'cubre_movimiento_id' => $contrato->cubre_movimiento_id,
        ]);

        $this->assertSinCesacionPendiente($contrato);

        $anterior = optional($contrato->fecha_fin)->toDateString();

        $contrato->update(['fecha_fin' => $nuevaFechaFin]);

        activity()
            ->performedOn($contrato)
            ->withProperties([
                'fecha_fin_anterior' => $anterior,
                'fecha_fin_nueva'    => $nuevaFechaFin,
                'motivo'             => $datos['motivo'],
            ])
            ->event('updated')
            ->log('Plazo del contrato reprogramado');

        return $contrato->fresh(['puesto.cargo', 'unidadAdministrativa']);
    }

    /**
     * Las reglas del plazo de un vínculo, en un solo sitio: al crearlo y al
     * reprogramarlo. Hasta el 2026-10-03 cada camino tenía las suyas y por
     * los huecos pasaba:
     *
     * - un término anterior al inicio, al aprobar el ingreso;
     * - un ocasional sin término, al ingresar o al reprogramar;
     * - un reemplazo prorrogado más allá de la ausencia que cubre —o dejado
     *   sin plazo—, con el titular ya de vuelta sobre la misma plaza.
     *
     * @param  array<string, mixed>  $datos  tipo_nombramiento, fecha_inicio,
     *   fecha_fin y, si cubre a alguien, cubre_movimiento_id.
     */
    private function validarPlazo(array $datos): void
    {
        $fin    = $datos['fecha_fin'] ?? null;
        $inicio = $datos['fecha_inicio'] ?? null;

        if ($fin && $inicio && $fin < $inicio) {
            throw new ReglaNegocioException(
                'La fecha de fin no puede ser anterior a la fecha de inicio del contrato.'
            );
        }

        $nombramiento = TipoNombramiento::tryFrom($this->valorTipoNombramiento($datos['tipo_nombramiento'] ?? ''));
        if (! $fin && $nombramiento?->exigePlazo()) {
            throw new ReglaNegocioException(
                "Un vínculo «{$nombramiento->etiqueta()}» no puede quedarse sin fecha de vencimiento."
            );
        }

        $ausencia = ! empty($datos['cubre_movimiento_id'])
            ? MovimientoPersonal::find($datos['cubre_movimiento_id'])
            : null;
        $finAusencia = $ausencia?->finEfectivo()?->toDateString();
        if ($finAusencia && (! $fin || $fin > $finAusencia)) {
            throw new ReglaNegocioException(
                "El reemplazo no puede extenderse más allá del {$finAusencia}, "
                    .'que es cuando termina la ausencia que cubre.'
            );
        }
    }

    /**
     * Si el vencimiento anterior ya generó una cesación que sigue en pie,
     * moverlo la dejaría apuntando a una fecha que ya no existe. Talento
     * Humano debe decidir primero qué hacer con esa acción.
     */
    private function assertSinCesacionPendiente(ContratoServidor $contrato): void
    {
        if (!$contrato->fecha_fin) {
            return;
        }

        $pendiente = MovimientoPersonal::where('servidor_id', $contrato->servidor_id)
            ->where('tipo_movimiento', TipoMovimientoPersonal::CESACION_FUNCIONES->value)
            ->where('subtipo_movimiento', SubtipoMovimientoPersonal::CONTRATO_FINALIZADO->value)
            ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
            ->whereDate('fecha_efectiva', $contrato->fecha_fin->toDateString())
            ->exists();

        if ($pendiente) {
            throw new ReglaNegocioException(
                'Ya existe una Cesación de Funciones generada para el vencimiento actual. '
                    .'Anúlela antes de reprogramar el plazo.'
            );
        }
    }

    /**
     * Decide si el contrato habilita la marcación biométrica.
     *
     * Esta es la segunda puerta al mismo dato: la acción de personal ya lo
     * resuelve en `MovimientoPersonalService::resolverPuedeMarcar()`, pero
     * `POST servidores/{id}/contratos` llega aquí sin pasar por ahí. Como
     * `contratos_servidor.puede_marcar` tiene `DEFAULT true`, omitir el campo
     * activaba la marcación de un contrato de servicios profesionales — que no
     * tiene jornada — y el observer la copiaba al servidor.
     *
     * Para las modalidades que no marcan nunca se fuerza a falso aunque el
     * cliente mande lo contrario: es una restricción, no un valor sugerido.
     * Los obreros del Código del Trabajo siguen siendo editables, que entre
     * ellos unos marcan y otros no.
     */
    private function resolverPuedeMarcar(array $data): bool
    {
        $nombramiento = TipoNombramiento::tryFrom(
            $this->valorTipoNombramiento($data['tipo_nombramiento'] ?? '')
        );

        if ($nombramiento !== null && ! $nombramiento->admiteMarcacion()) {
            return false;
        }

        if (array_key_exists('puede_marcar', $data) && $data['puede_marcar'] !== null) {
            return (bool) $data['puede_marcar'];
        }

        // Sin valor explícito manda la modalidad, no el DEFAULT de la columna.
        return $nombramiento?->puedeMarcarPorDefecto() ?? true;
    }

    private function valorTipoNombramiento(mixed $tipoNombramiento): string
    {
        return $tipoNombramiento instanceof \UnitEnum
            ? $tipoNombramiento->value
            : (string) $tipoNombramiento;
    }

    /**
     * Impide asignar un servidor a un puesto sin plazas disponibles.
     *
     * Qué modalidades consumen plaza lo decide `TipoNombramiento::ocupaPlaza()`:
     * servicios profesionales y ocasionales quedan fuera tanto de esta
     * validación como del conteo de ocupadas. Se asignan al puesto igual —para
     * funciones, EPP y ficha médica—, pero el puesto sigue vacante.
     *
     * Los reemplazos tampoco consumen plaza, en ninguno de los dos sentidos:
     * ni el que se está creando ($cubreMovimientoId) ni los ya existentes,
     * que se descuentan del conteo. La plaza sigue siendo del titular en
     * comisión o licencia — su contrato continúa vigente y es el que la
     * ocupa. Contarlas dos veces dejaría el puesto bloqueado justo cuando
     * Talento Humano necesita cubrir el hueco.
     */
    public function validarVacante(
        int $puestoId,
        string $tipoNombramiento,
        ?int $exceptoContratoId = null,
        ?int $cubreMovimientoId = null,
        ?int $exceptoMovimientoId = null
    ): void {
        if (! TipoNombramiento::from($tipoNombramiento)->ocupaPlaza()) {
            return;
        }

        if ($cubreMovimientoId !== null) {
            return;
        }

        $puesto = Puesto::findOrFail($puestoId);

        $ocupadas = $puesto->contratos()
            ->queOcupanPlaza()
            ->when($exceptoContratoId, fn ($q) => $q->where('id', '!=', $exceptoContratoId))
            ->count();

        // Un ingreso registrado que rige más tarde todavía no tiene contrato,
        // pero la plaza ya es suya (fase 1.6): sin contarlo, otro ingreso podía
        // ocuparla mientras tanto, y el primero fallaba el día en que regía.
        $ocupadas += MovimientoPersonal::pendientesDeVigencia()
            ->where('puesto_destino_id', $puestoId)
            ->whereNull('cubre_movimiento_id')
            ->when($exceptoMovimientoId, fn ($q) => $q->where('id', '!=', $exceptoMovimientoId))
            ->get(['id', 'tipo_movimiento', 'tipo_nombramiento_propuesto'])
            ->filter(fn (MovimientoPersonal $m) => $m->tipo_movimiento->creaVinculo()
                && (bool) $m->tipo_nombramiento_propuesto?->ocupaPlaza())
            ->count();

        if ($ocupadas >= $puesto->plazas) {
            throw new ReglaNegocioException(
                'El puesto seleccionado no tiene plazas disponibles.'
            );
        }
    }

    /**
     * Sincroniza los campos derivados de la carrera del servidor a partir
     * del contrato vigente (tipo_nombramiento, regimen_laboral, fechas de
     * ingreso/nombramiento). puesto_id/unidad_administrativa_id ya NO se
     * escriben aquí — esa es responsabilidad exclusiva de
     * sincronizarPuestoDesdeVinculo().
     *
     * Si $movimientoOrigen es null (alta de contrato sin acto formal
     * previo), lo anota en la bitácora del vínculo, para que el expediente
     * diga de dónde salió ese contrato. Si no es null, ese movimiento (ya
     * registrado o en camino a registrarse) es la constancia — no se duplica.
     *
     * Hasta la fase 1.2 del rediseño esa anotación era un MovimientoPersonal
     * de tipo 'novedad_contrato', que salía en el historial de acciones como
     * si fuera un acto.
     */
    private function sincronizarRegimenServidor(
        int $servidorId,
        array $data,
        ?MovimientoPersonal $movimientoOrigen = null,
        ?ContratoServidor $contrato = null
    ): void {
        $tipoNombramiento = $data['tipo_nombramiento'] ?? null;
        if (!$tipoNombramiento) return;

        $tipoNombramientoEnum = $tipoNombramiento instanceof TipoNombramiento
            ? $tipoNombramiento
            : TipoNombramiento::from((string) $tipoNombramiento);

        $regimen = match ($tipoNombramientoEnum) {
            // Hasta el 2026-08-29 los servicios profesionales caían aquí, en el
            // cajón de «lo que no es LOSEP». Ahora tienen su propio régimen:
            // es un contrato civil, no una relación laboral.
            TipoNombramiento::SERVICIOS_PROFESIONALES => 'servicios_profesionales',
            TipoNombramiento::CODIGO_TRABAJO => 'codigo_trabajo',
            default => 'losep',
        };

        $update = [
            'regimen_laboral'   => $regimen,
            'tipo_nombramiento' => $tipoNombramientoEnum->value,
        ];

        // Fecha de nombramiento oficial solo aplica a Nombramiento Permanente.
        $update['fecha_nombramiento'] =
            $tipoNombramientoEnum === TipoNombramiento::PERMANENTE
                ? ($data['fecha_inicio'] ?? null)
                : null;

        DB::transaction(function () use ($servidorId, $data, $update, $tipoNombramientoEnum, $movimientoOrigen, $contrato) {
            if ($movimientoOrigen === null) {
                $servidorActual = Servidor::findOrFail($servidorId);

                // Bitácora de un hecho ya consumado —el contrato existe—, no un
                // acto que alguien deba aprobar.
                EventoVinculo::create([
                    'servidor_id'          => $servidorId,
                    'contrato_servidor_id' => $contrato?->id,
                    'tipo'                 => TipoEventoVinculo::CONTRATO_REGISTRADO,
                    'fecha'                => $data['fecha_inicio'] ?? now()->toDateString(),
                    'descripcion'          => "Contrato registrado sin acción de personal: {$tipoNombramientoEnum->etiqueta()}.",
                    'datos'                => [
                        'categoria'         => CategoriaEventoVinculo::paraTipoNombramiento($tipoNombramientoEnum)->value,
                        'unidad_origen_id'  => $servidorActual->unidad_administrativa_id,
                        'unidad_destino_id' => $data['unidad_administrativa_id'] ?? $servidorActual->unidad_administrativa_id,
                        'puesto_origen_id'  => $servidorActual->puesto_id,
                        'puesto_destino_id' => $data['puesto_id'] ?? $servidorActual->puesto_id,
                    ],
                    'registrado_por'       => auth()->id(),
                ]);
            }

            // Update de instancia, no mass-update: para que dispare los
            // eventos de modelo (ServidorObserver) de los que depende la
            // auditoría de este método — el hallazgo crítico original.
            $servidor = Servidor::findOrFail($servidorId);

            // La antigüedad sale de la historia de vínculos, con este ya dentro
            // (diseño, 8.3). Hasta la fase 1.5 cada contrato nuevo la pisaba con
            // su fecha de inicio, y una cesación seguida de un ingreso la
            // reiniciaba.
            $update['fecha_ingreso_institucion'] = $servidor->inicioDelServicioContinuo()
                ?? ($data['fecha_inicio'] ?? null);

            $servidor->update($update);

            // También devuelve `estado = true` a quien reingresa: lo deriva del
            // vínculo, como el puesto. Antes era un caso aparte aquí.

            $this->sincronizarPuestoDesdeVinculo($servidorId);
        });
    }

    /**
     * Única vía permitida para escribir Servidor.puesto_id,
     * Servidor.unidad_administrativa_id y Servidor.estado. Deriva los tres
     * siempre del ContratoServidor vigente — nunca de un payload suelto. Si no
     * hay vínculo vigente, deja puesto y unidad en null y al servidor inactivo.
     *
     * `estado` entró aquí en la fase 1.5 (diseño, 8.3): significa «tiene un
     * vínculo vigente». Hasta entonces nadie lo ponía en false, y un cesado
     * seguía en la nómina y en la generación de vacaciones, que lo consultan.
     */
    public function sincronizarPuestoDesdeVinculo(int $servidorId): void
    {
        $servidor = Servidor::with('contratoVigente')->findOrFail($servidorId);
        $vigente = $servidor->contratoVigente;

        $servidor->update([
            'puesto_id'                => $vigente?->puesto_id,
            'unidad_administrativa_id' => $vigente?->unidad_administrativa_id,
            'estado'                   => $vigente !== null,
        ]);
    }

    /**
     * Reubica al servidor dentro del mismo vínculo: un traspaso o una
     * prestación de servicios cambian puesto y unidad, pero NO terminan la
     * relación laboral ni generan un instrumento nuevo.
     *
     * Por eso se actualiza el contrato en vez de cerrarlo y crear otro: el
     * número de contrato, la resolución y la fecha de inicio son los del
     * documento que el servidor firmó al ingresar, y siguen siendo válidos.
     * La versión anterior cerraba y creaba, lo que fabricaba un contrato sin
     * número —porque no existe tal documento— y partía en dos una relación
     * laboral que nunca se interrumpió.
     *
     * La remuneración tampoco cambia: confirmado con Talento Humano que el
     * traspaso se hace dentro del mismo grupo ocupacional, sin incremento ni
     * decremento. La partida presupuestaria sí acompaña al puesto, porque
     * cuelga de él y no del contrato.
     *
     * En la prestación de servicios tampoco, y ya no es un supuesto: se quedó
     * pendiente al implementarla —TH la había descrito como «prácticamente
     * como un traspaso» sin pronunciarse sobre la remuneración— y el
     * 2026-09-29 confirmaron que no cambia.
     */
    public function reestructurarDesdeMovimiento(MovimientoPersonal $movimiento): void
    {
        $servidor = Servidor::with('contratoVigente')->findOrFail($movimiento->servidor_id);
        $vigente = $servidor->contratoVigente;

        if (!$vigente) {
            throw new ReglaNegocioException(
                'El servidor no tiene un vínculo vigente para reubicar.'
            );
        }

        $puestoDestino = (int) $movimiento->puesto_destino_id;

        // El puesto destino debe tener plaza libre; se excluye el contrato
        // que se está moviendo para no competir consigo mismo cuando el
        // traslado es dentro del mismo puesto.
        $this->validarVacante(
            $puestoDestino,
            $this->valorTipoNombramiento($vigente->tipo_nombramiento),
            $vigente->id
        );

        $vigente->update([
            'puesto_id' => $puestoDestino,
            'unidad_administrativa_id' => $movimiento->unidad_destino_id
                ?? $vigente->unidad_administrativa_id,
        ]);

        $this->sincronizarPuestoDesdeVinculo($movimiento->servidor_id);
    }
}
