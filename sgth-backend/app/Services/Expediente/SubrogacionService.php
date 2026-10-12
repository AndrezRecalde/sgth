<?php

namespace App\Services\Expediente;

use App\Contracts\Expediente\SubrogacionServiceInterface;
use App\Enums\CategoriaEventoVinculo;
use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoSubrogacion;
use App\Enums\InstrumentoActo;
use App\Enums\MotivoSubrogacion;
use App\Enums\TipoAusenciaTitular;
use App\Enums\TipoEventoVinculo;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoSubrogacion;
use App\Enums\PartidaPorModalidad;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\EventoVinculo;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SubrogacionService implements SubrogacionServiceInterface
{
    public function registrar(array $datos): Subrogacion
    {
        // La subrogación nace con su acción de personal, y esa acción es del
        // subrogante.
        TramiteSobreSiMismo::impedir((int) $datos['servidor_subrogante_id']);

        // La del Viceprefecto (COOTAD Art. 51; fase 4.1b) tiene sus propias
        // reglas: su ausencia es la licencia del Consejo, y el puesto del
        // Prefecto no es del nivel jerárquico superior de la escala.
        $porLey = ! empty($datos['por_ley']);
        $esEncargo = $datos['tipo'] === TipoSubrogacion::ENCARGO->value;
        $datos['fecha_fin'] = filled($datos['fecha_fin'] ?? null) ? $datos['fecha_fin'] : null;

        // 1. Validación condicional de encargo vs subrogación
        if ($esEncargo) {
            $datos['servidor_subrogado_id'] = null;
            $datos['ausencia_tipo'] = null;
            $datos['ausencia_id'] = null;
            $datos['motivo'] ??= MotivoSubrogacion::ENCARGO_VACANTE->value;
        } elseif (empty($datos['servidor_subrogado_id'])) {
            throw new ReglaNegocioException("La subrogación requiere especificar el servidor titular a subrogar.");
        }

        $this->validarTitularDelPuesto($datos);

        if (! $porLey) {
            $this->impedirElPuestoDelPrefecto($datos);

            if (! $esEncargo) {
                $this->exigirNivelJerarquicoSuperior($datos);
            }
        }

        // 2. Validación de fechas. El encargo puede no tener fin: dura «hasta
        // la designación del titular» (LOSEP, Art. 127; fase 5.2).
        if (! $esEncargo && $datos['fecha_fin'] === null) {
            throw new ReglaNegocioException('La subrogación tiene fecha de fin: la de la ausencia del titular.');
        }

        if ($datos['fecha_fin'] !== null && strtotime($datos['fecha_fin']) <= strtotime($datos['fecha_inicio'])) {
            throw new ReglaNegocioException("La fecha de fin debe ser estrictamente mayor a la fecha de inicio.");
        }

        // 3. La ausencia del titular que la motiva, y las fechas dentro de ella.
        if (! $esEncargo && ! $porLey) {
            $datos = $this->conLaAusencia($datos);
        }

        if (blank($datos['motivo'] ?? null)) {
            throw new ReglaNegocioException('Indique el motivo de la subrogación.');
        }

        // 3. Registro transaccional. Los traslapes se comprueban DENTRO de la
        // transacción y bloqueando las filas que se leen, como en
        // PermisoService: hacerlo antes de abrirla dejaba pasar dos peticiones
        // simultáneas, que se validaban cada una contra un estado donde la otra
        // todavía no existía.
        return DB::transaction(function () use ($datos) {
            $this->validarSinTraslape($datos);

            // Nace PENDIENTE, no ACTIVA: hasta que su Acción de Personal se
            // registre, la subrogación no surte efecto. Crearla activa hacía
            // que el subrogante pudiera firmar de inmediato —
            // FirmanteAccionPersonalService lo antepone al titular— sin que
            // nadie hubiera suscrito el acto ni verificado el presupuesto.
            $subrogacion = Subrogacion::create([
                'tipo'                     => $datos['tipo'],
                'servidor_subrogante_id'   => $datos['servidor_subrogante_id'],
                'servidor_subrogado_id'    => $datos['servidor_subrogado_id'] ?? null,
                'unidad_administrativa_id' => $datos['unidad_administrativa_id'],
                'puesto_subrogado_id'      => $datos['puesto_subrogado_id'],
                'fecha_inicio'             => $datos['fecha_inicio'],
                'fecha_fin'                => $datos['fecha_fin'],
                'motivo'                   => $datos['motivo'],
                'ausencia_tipo'            => $datos['ausencia_tipo'] ?? null,
                'ausencia_id'              => $datos['ausencia_id'] ?? null,
                'resolucion_numero'        => $datos['resolucion_numero'] ?? null,
                'observacion'              => $datos['observacion'] ?? null,
                'estado'                   => EstadoSubrogacion::PENDIENTE,
                'registrado_por'           => auth()->id(),
            ]);

            // Registrar en historial inmutable. El Art. 21 del Reglamento
            // LOSEP trata la subrogación/encargo como una acción de
            // personal formal más (a diferencia de servicios
            // profesionales): nace en BORRADOR y debe pasar por el mismo
            // flujo guardado (MovimientoPersonalStateService) — incluido
            // el bloqueo presupuestario antes de suscribirse — que
            // cualquier otro tipo con efecto económico. El resto de esta
            // lógica (elegibilidad, traslape, remuneración) no cambia.
            $movimiento = MovimientoPersonal::create([
                'servidor_id'       => $subrogacion->servidor_subrogante_id,
                'tipo_movimiento'   => 'subrogacion',
                // Si es un encargo solo lo sabe esta fila: desde el tipo, que
                // los dos comparten, la clase saldría siempre «subrogación».
                'clase'             => $subrogacion->tipo->value,
                'categoria'         => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
                'estado'            => EstadoAccionPersonal::BORRADOR,
                'descripcion'       => $this->explicacion($subrogacion),
                'fecha_efectiva'    => $subrogacion->fecha_inicio,
                // La subrogación es temporal por definición: sin el término en
                // la acción, el historial del servidor mostraba cuándo empezó
                // pero nunca cuándo terminaba, y hacía falta un segundo
                // movimiento solo para dejar constancia de lo obvio.
                'fecha_inicio'      => $subrogacion->fecha_inicio,
                'fecha_fin'         => $subrogacion->fecha_fin,
                'unidad_destino_id' => $subrogacion->unidad_administrativa_id,
                'puesto_destino_id' => $subrogacion->puesto_subrogado_id,
                'resolucion_numero' => $subrogacion->resolucion_numero,
                'autorizado_por'    => auth()->id(),
                // La del Viceprefecto es acto de autoridad, sin acción de
                // personal (fase 4.1b): el instrumento lo dice su nombramiento.
                'instrumento'       => InstrumentoActo::para(
                    TipoMovimientoPersonal::SUBROGACION,
                    Servidor::with('contratoVigente')->find($subrogacion->servidor_subrogante_id)?->contratoVigente?->tipo_nombramiento,
                )->value,
                // La ausencia que la origina, si la preparó el sistema (4.1b).
                'movimiento_relacionado_id' => $datos['movimiento_relacionado_id'] ?? null,
                ...$this->congelarSituacion($subrogacion),
            ]);

            // El enlace es lo que permite que la subrogación espere a su
            // acción, y que anular la acción arrastre a la subrogación.
            $subrogacion->movimiento_personal_id = $movimiento->id;
            $subrogacion->save();

            return $subrogacion;
        });
    }

    /**
     * Nadie subroga dos puestos a la vez, y ningún puesto se subroga dos veces
     * a la vez.
     *
     * Lo segundo faltaba: el control miraba solo `servidor_subrogante_id`, así
     * que dos personas podían quedar subrogando el mismo puesto en el mismo
     * período —comprobado— y entonces quién firma lo decidía el
     * `orderByDesc('fecha_inicio')->first()` de
     * FirmanteOrganigramaService::subroganteDe(), es decir, el azar del orden de
     * inserción. Para un puesto de jefatura eso es la facultad de firmar
     * repartida entre dos personas sin que ningún acto lo diga.
     *
     * Cuentan las PENDIENTES además de las activas: dos solicitudes traslapadas
     * pasarían el control y al aprobarse dejarían el conflicto ya consumado.
     *
     * @param  array<string, mixed>  $datos
     */
    private function validarSinTraslape(array $datos): void
    {
        // Dos rangos se cruzan si cada uno empieza antes de que termine el
        // otro. Un encargo sin fin no termina (fase 5.2).
        $enElRango = fn ($query) => $query
            ->whereIn('estado', [
                EstadoSubrogacion::PENDIENTE->value,
                EstadoSubrogacion::ACTIVA->value,
            ])
            ->when($datos['fecha_fin'], fn ($q, $fin) => $q->whereDate('fecha_inicio', '<=', $fin))
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $datos['fecha_inicio']));

        $delSubrogante = Subrogacion::where('servidor_subrogante_id', $datos['servidor_subrogante_id'])
            ->where($enElRango)
            ->lockForUpdate()
            ->exists();

        if ($delSubrogante) {
            throw new ReglaNegocioException(
                'El servidor ya cuenta con una subrogación/encargo activo en el rango de fechas indicado.'
            );
        }

        $delPuesto = Subrogacion::where('puesto_subrogado_id', $datos['puesto_subrogado_id'])
            ->where($enElRango)
            ->lockForUpdate()
            ->first();

        if ($delPuesto) {
            $puesto = Puesto::with('cargo')->find($datos['puesto_subrogado_id']);
            $nombrePuesto = $puesto?->cargo?->nombre ?? "#{$datos['puesto_subrogado_id']}";

            throw new ReglaNegocioException(
                "El puesto de {$nombrePuesto} ya está cubierto desde el "
                .$delPuesto->fecha_inicio->format('d/m/Y')
                .($delPuesto->fecha_fin ? ' hasta el '.$delPuesto->fecha_fin->format('d/m/Y') : ', sin fecha de fin')
                .'. Finalice o cancele esa subrogación/encargo antes de registrar otro.'
            );
        }
    }

    /**
     * El titular debe ser quien realmente ocupa el puesto que se va a subrogar.
     *
     * Sin esta regla se podía nombrar titular a cualquiera: el registro decía
     * "Fulano subroga a Mengano en el puesto X" aunque Mengano nunca hubiera
     * ocupado X. No otorgaba poder de más —la firma y el organigrama se
     * resuelven por el puesto, no por el titular— pero producía un documento
     * firmado que afirma un reemplazo que no ocurrió, y ensuciaba el expediente
     * del supuesto titular.
     *
     * Del mismo error nace su reverso: un puesto vacante no se subroga, se
     * encarga. La distinción entre ambas figuras es exactamente esa.
     */
    private function validarTitularDelPuesto(array $datos): void
    {
        $puesto = Puesto::with('cargo')->find($datos['puesto_subrogado_id']);

        if (! $puesto) {
            return; // la validación de existencia ya la hizo el controlador
        }

        $ocupantes = $puesto->contratosVigentes()->pluck('servidor_id');
        $nombrePuesto = $puesto->cargo?->nombre ?? "#{$puesto->id}";

        if ($datos['tipo'] === TipoSubrogacion::ENCARGO->value) {
            if ($ocupantes->isNotEmpty()) {
                throw new ReglaNegocioException(
                    "El puesto de {$nombrePuesto} tiene titular: corresponde una subrogación, no un encargo."
                );
            }

            return;
        }

        if ($ocupantes->isEmpty()) {
            throw new ReglaNegocioException(
                "El puesto de {$nombrePuesto} está vacante: corresponde un encargo, no una subrogación."
            );
        }

        if (! $ocupantes->contains((int) $datos['servidor_subrogado_id'])) {
            throw new ReglaNegocioException(
                "El servidor indicado como titular no ocupa el puesto de {$nombrePuesto}."
            );
        }
    }

    /**
     * La subrogación con diferencia de remuneración es de un puesto del nivel
     * jerárquico superior (LOSEP, Art. 126; Reglamento, Art. 270; fase 5.2).
     * Hasta aquí se aceptaba cualquiera. El encargo no: vale para cualquier
     * puesto vacante (Art. 127).
     *
     * @param  array<string, mixed>  $datos
     */
    private function exigirNivelJerarquicoSuperior(array $datos): void
    {
        $puesto = Puesto::with(['cargo', 'grupoOcupacional'])->find($datos['puesto_subrogado_id']);

        if ($puesto && ! $puesto->esNivelJerarquicoSuperior()) {
            $nombre = $puesto->cargo?->nombre ?? "#{$puesto->id}";

            throw new ReglaNegocioException(
                "La subrogación es de los puestos del nivel jerárquico superior (LOSEP, Art. 126), y el de {$nombre} "
                    .'no lo es: su grupo ocupacional no es NJS.'
            );
        }
    }

    /**
     * Al Prefecto lo subroga el Viceprefecto por ley (COOTAD Art. 51; fase
     * 4.1b): se registra desde «Autoridades electas», no aquí. Es su puesto el
     * que ocupa la autoridad electa en funciones, no cualquier jefatura de la
     * cima del organigrama.
     *
     * @param  array<string, mixed>  $datos
     */
    private function impedirElPuestoDelPrefecto(array $datos): void
    {
        if (app(AutoridadElectaService::class)->prefecto()?->puesto_id === (int) $datos['puesto_subrogado_id']) {
            throw new ReglaNegocioException(
                'Al Prefecto lo subroga el Viceprefecto por ley (COOTAD, Art. 51): regístrelo desde '
                    .'Acciones de Personal › Autoridades electas, «Subrogación por ley».'
            );
        }
    }

    /**
     * La ausencia del titular que la motiva, de las que hay en el SGTH, y la
     * subrogación dentro de sus fechas; el motivo sale de ella. «Otra» es la
     * que no está en el SGTH, y se respalda con la resolución (fase 5.2).
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function conLaAusencia(array $datos): array
    {
        $tipo = TipoAusenciaTitular::tryFrom((string) ($datos['ausencia_tipo'] ?? ''))
            ?? throw new ReglaNegocioException('Indique la ausencia del titular que motiva la subrogación.');

        if ($tipo === TipoAusenciaTitular::OTRA) {
            if (blank($datos['resolucion_numero'] ?? null)) {
                throw new ReglaNegocioException(
                    'Una ausencia que no está en el SGTH se respalda con la resolución que la dispone: indique su número.'
                );
            }

            return [...$datos, 'ausencia_id' => null, 'motivo' => MotivoSubrogacion::OTRO->value];
        }

        if (empty($datos['ausencia_id'])) {
            throw new ReglaNegocioException('Elija la ausencia del titular que motiva la subrogación.');
        }

        $ausencia = app(AusenciaDelTitularService::class)
            ->resolver($tipo, (int) $datos['ausencia_id'], (int) $datos['servidor_subrogado_id']);

        $desde = Carbon::parse($datos['fecha_inicio'])->toDateString();
        $hasta = Carbon::parse($datos['fecha_fin'])->toDateString();

        if ($desde < $ausencia['desde'] || ($ausencia['hasta'] !== null && $hasta > $ausencia['hasta'])) {
            throw new ReglaNegocioException(
                "La subrogación cabe dentro de la ausencia del titular: {$ausencia['etiqueta']} va del "
                    .Carbon::parse($ausencia['desde'])->format('d/m/Y')
                    .($ausencia['hasta'] ? ' al '.Carbon::parse($ausencia['hasta'])->format('d/m/Y') : ', sin fecha de fin')
                    .'.'
            );
        }

        return [...$datos, 'ausencia_tipo' => $tipo->value, 'motivo' => $ausencia['motivo']->value];
    }

    /**
     * Si la ausencia del titular se anula, la subrogación que motivaba se
     * cancela con ella (fase 5.2).
     *
     * @return int Cuántas se cancelaron.
     */
    public function cancelarPorAusencia(TipoAusenciaTitular $tipo, int $ausenciaId, string $motivo): int
    {
        $afectadas = Subrogacion::where('ausencia_tipo', $tipo->value)
            ->where('ausencia_id', $ausenciaId)
            ->whereIn('estado', [EstadoSubrogacion::PENDIENTE->value, EstadoSubrogacion::ACTIVA->value])
            ->orderBy('id')
            ->get();

        foreach ($afectadas as $subrogacion) {
            $this->cancelar($subrogacion->id, $motivo);
        }

        return $afectadas->count();
    }

    /**
     * El reintegro acorta la ausencia del titular: la subrogación termina la
     * víspera de su regreso (fase 5.2).
     */
    public function acortarPorReintegro(MovimientoPersonal $reintegro): int
    {
        if (! $reintegro->movimiento_relacionado_id || ! $reintegro->fecha_efectiva) {
            return 0;
        }

        $afectadas = Subrogacion::where('ausencia_tipo', TipoAusenciaTitular::ACCION_PERSONAL->value)
            ->where('ausencia_id', $reintegro->movimiento_relacionado_id)
            ->whereIn('estado', [EstadoSubrogacion::PENDIENTE->value, EstadoSubrogacion::ACTIVA->value])
            ->orderBy('id')
            ->get();

        $vispera = $reintegro->fecha_efectiva->copy()->subDay();
        $codigo = $reintegro->codigo_registro ?? "#{$reintegro->id}";

        foreach ($afectadas as $subrogacion) {
            $this->terminarAntes($subrogacion, $vispera, "el titular volvió con el reintegro {$codigo}");
        }

        return $afectadas->count();
    }

    /**
     * El encargo dura hasta la designación del titular (LOSEP, Art. 127): el
     * día en que rige el ingreso, traslado o ascenso de alguien a ese puesto,
     * el encargo termina la víspera (fase 5.2).
     */
    public function cerrarEncargosDelPuesto(MovimientoPersonal $llegada): int
    {
        if (! $llegada->puesto_destino_id || ! $llegada->fecha_efectiva) {
            return 0;
        }

        $desde = $llegada->fecha_efectiva->toDateString();

        $afectadas = Subrogacion::where('tipo', TipoSubrogacion::ENCARGO->value)
            ->where('puesto_subrogado_id', $llegada->puesto_destino_id)
            ->whereIn('estado', [EstadoSubrogacion::PENDIENTE->value, EstadoSubrogacion::ACTIVA->value])
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $desde))
            ->orderBy('id')
            ->get();

        $codigo = $llegada->codigo_registro ?? "#{$llegada->id}";

        foreach ($afectadas as $subrogacion) {
            $this->terminarAntes($subrogacion, $llegada->fecha_efectiva->copy()->subDay(), "llegó el titular del puesto ({$codigo})");
        }

        return $afectadas->count();
    }

    /**
     * Termina la subrogación o el encargo en una fecha anterior a la prevista:
     * si ni siquiera empezó, se cancela; si no, su fin pasa a esa fecha, con su
     * constancia, y si ya pasó queda finalizada.
     */
    private function terminarAntes(Subrogacion $subrogacion, Carbon $fin, string $porQue): void
    {
        if ($subrogacion->fecha_fin && $subrogacion->fecha_fin->lte($fin)) {
            return;
        }

        if ($fin->lt($subrogacion->fecha_inicio)) {
            $this->cancelar($subrogacion->id, ucfirst($porQue).' antes de que empezara.');

            return;
        }

        $subrogacion->update([
            'fecha_fin'   => $fin->toDateString(),
            'estado'      => $subrogacion->estado === EstadoSubrogacion::ACTIVA && $fin->lt(now()->startOfDay())
                ? EstadoSubrogacion::FINALIZADA
                : $subrogacion->estado,
            'observacion' => $this->conNota($subrogacion->observacion, "Termina el {$fin->format('d/m/Y')}: {$porQue}."),
        ]);

        // La acción que todavía es borrador dice el fin nuevo; la emitida no se
        // reescribe, y el fin queda en la bitácora.
        $accion = $subrogacion->movimientoPersonal;
        if ($accion?->estado === EstadoAccionPersonal::BORRADOR) {
            $accion->update(['fecha_fin' => $fin->toDateString(), 'descripcion' => $this->explicacion($subrogacion->fresh())]);
        }

        if ($subrogacion->estado === EstadoSubrogacion::ACTIVA || $subrogacion->estado === EstadoSubrogacion::FINALIZADA) {
            $this->anotarEnLaBitacora(
                $subrogacion,
                TipoEventoVinculo::SUBROGACION_FINALIZADA,
                "Fin de {$subrogacion->tipo->etiqueta()}: termina el {$fin->format('d/m/Y')} porque {$porQue}.",
            );
        }
    }

    /**
     * Texto del bloque "Explicación" del documento impreso.
     *
     * Se arma aquí y no en la plantilla porque queda congelado en la acción:
     * es la redacción que circuló y firmaron. Decía "en la unidad
     * seleccionada" — lenguaje de formulario dentro de un documento oficial,
     * que además omitía todo lo que un lector necesita: qué puesto, dónde, a
     * quién reemplaza y por cuánto tiempo.
     */
    private function explicacion(Subrogacion $subrogacion): string
    {
        $puesto = $subrogacion->puestoSubrogado?->cargo?->nombre ?? 'el puesto asignado';
        $unidad = $subrogacion->unidadAdministrativa?->nombre;

        $texto = "{$subrogacion->tipo->etiqueta()} del puesto de {$puesto}";

        if ($unidad) {
            $texto .= " en {$unidad}";
        }

        // Solo la subrogación reemplaza a alguien; el encargo recae sobre un
        // puesto sin titular.
        if ($titular = $subrogacion->subrogado) {
            $nombre = trim(implode(' ', array_filter([$titular->apellido, $titular->nombre])));
            $texto .= ", en reemplazo de {$nombre}";
        }

        return $texto.($subrogacion->fecha_fin
            ? ', del '.$subrogacion->fecha_inicio->format('d/m/Y').' al '.$subrogacion->fecha_fin->format('d/m/Y').'.'
            : ', desde el '.$subrogacion->fecha_inicio->format('d/m/Y').' hasta la designación del titular del puesto.');
    }

    /**
     * Sella en la Acción de Personal las dos remuneraciones que se comparan:
     * la que el subrogante ya percibe y la del puesto que asume.
     *
     * Lo que se autoriza en una subrogación no es el sueldo del puesto sino la
     * diferencia entre ambos (Art. 21 del Reglamento a la LOSEP), así que esas
     * dos cifras *son* el acto administrativo. Derivarlas al imprimir haría
     * que un documento reimpreso después de una revisión salarial mostrara una
     * diferencia que nadie autorizó — el mismo motivo por el que ya se congela
     * la situación de origen en el resto de acciones y por el que los
     * firmantes se sellan en vez de resolverse cada vez.
     *
     * La diferencia no se guarda: se calcula de dos valores ya congelados, y
     * una tercera copia solo agregaría una forma de quedar en desacuerdo.
     */
    private function congelarSituacion(Subrogacion $subrogacion): array
    {
        $vigente = $subrogacion->subrogante?->contratoVigente;
        $puesto  = $subrogacion->puestoSubrogado;

        return [
            'unidad_origen_id' => $vigente?->unidad_administrativa_id,
            'puesto_origen_id' => $vigente?->puesto_id,
            // Del contrato, no del puesto: en Código del Trabajo el puesto no
            // define R.M.U., y en LOSEP el contrato puede llevar un ajuste. Si
            // el contrato no la trae, el puesto es el único respaldo que hay.
            'remuneracion_origen' => $vigente?->remuneracion ?? $vigente?->puesto?->rmu,
            // La del vínculo que el subrogante ya tiene: es la que hoy le paga.
            'partida_origen_id'   => $vigente?->partida_presupuestaria_id
                ?? $vigente?->puesto?->partida_presupuestaria_id,

            'remuneracion_propuesta'    => $puesto?->rmu,
            // No la del puesto subrogado: la subrogación no paga su
            // remuneración sino la diferencia, y esa tiene partida propia
            // —510512 subrogaciones, 510513 encargos— confirmada por la
            // Dirección Financiera. Imputarla a la del puesto habría cargado
            // el gasto a una plaza que su titular sigue ocupando.
            'partida_presupuestaria_id' => $this->partidaDeLaDiferencia($subrogacion),
        ];
    }

    /**
     * La partida contra la que se paga la diferencia: 510512 para la
     * subrogación, 510513 para el encargo. Null si no están registradas o están
     * inactivas, y entonces el guard del Art. 105 no deja suscribir la acción,
     * que es mejor que imputar el gasto a una partida equivocada.
     *
     * Ese rechazo no era cierto hasta el 2026-09-28: `aplicarSuscrita()` caía a
     * la partida del contrato vigente del subrogante y luego a la del puesto, de
     * modo que la acción se suscribía igual y cargaba la diferencia donde no
     * debía. Ahora la subrogación no hereda partida de nadie.
     */
    private function partidaDeLaDiferencia(Subrogacion $subrogacion): ?int
    {
        $codigo = $subrogacion->tipo === TipoSubrogacion::ENCARGO
            ? PartidaPorModalidad::ENCARGO
            : PartidaPorModalidad::SUBROGACION;

        return PartidaPresupuestaria::where('codigo', $codigo)
            ->where('activo', true)
            ->value('id');
    }

    /**
     * Activa la subrogación cuando su Acción de Personal queda registrada.
     * Recién en ese momento el subrogante asume el puesto y puede firmar.
     */
    public function activarPorMovimiento(MovimientoPersonal $movimiento): void
    {
        // Update por instancia, no mass-update: un mass-update no dispara
        // eventos de modelo, y con ellos se perdía el rastro en activity_log de
        // justo el momento en que alguien adquiere la facultad de firmar. El
        // volumen es siempre 0 o 1 (una subrogación por acción de personal).
        $pendientes = Subrogacion::where('movimiento_personal_id', $movimiento->id)
            ->where('estado', EstadoSubrogacion::PENDIENTE->value)
            ->get();

        foreach ($pendientes as $subrogacion) {
            $subrogacion->update(['estado' => EstadoSubrogacion::ACTIVA->value]);
        }
    }

    /**
     * Anular la acción cancela su subrogación: el acto que la respaldaba dejó
     * de existir, así que no puede seguir surtiendo efecto.
     */
    public function cancelarPorMovimiento(MovimientoPersonal $movimiento): void
    {
        $vigentes = Subrogacion::where('movimiento_personal_id', $movimiento->id)
            ->whereIn('estado', [
                EstadoSubrogacion::PENDIENTE->value,
                EstadoSubrogacion::ACTIVA->value,
            ])
            ->get();

        foreach ($vigentes as $subrogacion) {
            $subrogacion->update([
                'estado' => EstadoSubrogacion::CANCELADA->value,
                'cancelada_en' => now()->toDateString(),
                // Se añade, no se reemplaza: el mass-update anterior borraba de
                // un golpe lo que Talento Humano hubiera anotado al registrarla.
                // `cancelar()` ya concatenaba; esto hacía lo contrario.
                'observacion' => $this->conNota(
                    $subrogacion->observacion,
                    'Cancelada automáticamente: se anuló la Acción de Personal que la respaldaba.'
                ),
            ]);
        }
    }

    /** Añade una nota a la observación sin perder lo que ya decía. */
    private function conNota(?string $observacion, string $nota): string
    {
        return trim(($observacion ? $observacion."\n" : '').$nota);
    }

    /**
     * Corte anticipado: el titular volvió antes, o Talento Humano dio por
     * terminado el encargo. Deja constancia en el historial porque el acto
     * terminó **antes** de su término — el fin en la fecha prevista ya está
     * dicho en la acción original y no necesita un segundo registro.
     */
    public function finalizar(int $subrogacionId): Subrogacion
    {
        return DB::transaction(function () use ($subrogacionId) {
            $subrogacion = Subrogacion::findOrFail($subrogacionId);

            if ($subrogacion->estado !== EstadoSubrogacion::ACTIVA) {
                throw new ReglaNegocioException("Solo se pueden finalizar subrogaciones activas.");
            }

            $previsto = $subrogacion->fecha_fin;

            // El encargo sin fin termina hoy: queda dicho cuándo (fase 5.2).
            $subrogacion->update([
                'estado'    => EstadoSubrogacion::FINALIZADA,
                'fecha_fin' => $previsto ?? now()->toDateString(),
            ]);

            // Bitácora del vínculo, no un acto administrativo nuevo: un hecho
            // consumado que nadie tiene que aprobar. Hasta la fase 1.2 era un
            // MovimientoPersonal de tipo 'subrogacion' sin correlativo, y en el
            // historial no se distinguía de la acción que abrió la subrogación.
            $this->anotarEnLaBitacora(
                $subrogacion,
                TipoEventoVinculo::SUBROGACION_FINALIZADA,
                $previsto
                    ? "Finalización anticipada de {$subrogacion->tipo->etiqueta()}: terminó antes del {$previsto->format('d/m/Y')} previsto."
                    : "Finalización de {$subrogacion->tipo->etiqueta()}: terminó el ".now()->format('d/m/Y').'.',
            );

            return $subrogacion;
        });
    }

    /**
     * Cierra las que ya cumplieron su plazo. Sin esto el estado miente: una
     * subrogación de hace dos años sigue diciendo "activa".
     *
     * No otorga ni quita poder — quien firma y quién ve qué se resuelve
     * siempre acotando por fecha (ver FirmanteAccionPersonalService y
     * User::puedeVerUnidad), así que una vencida nunca tuvo efectos de más.
     * Lo que arregla es el registro: el estado que se consulta, se reporta y
     * se muestra.
     *
     * No genera movimiento en el historial: llegar a la fecha de fin es
     * exactamente lo que la Acción de Personal original ya dice que va a
     * pasar. Solo la finalización *anticipada* es un hecho nuevo.
     *
     * @return array{caducadas: int, fecha: string}
     */
    public function caducarVencidas(?string $hasta = null): array
    {
        $fecha = $hasta ?? now()->toDateString();

        // Una por una y no con un mass-update: el estado de una subrogación es
        // lo que decide quién firma, así que cada cierre deja su línea en
        // activity_log con el estado del que venía. El volumen diario es de unas
        // pocas filas —las que vencieron ayer—, no de un padrón.
        // El encargo sin fin no vence: lo cierra la llegada del titular.
        $vencidas = Subrogacion::where('estado', EstadoSubrogacion::ACTIVA->value)
            ->whereNotNull('fecha_fin')
            ->whereDate('fecha_fin', '<', $fecha)
            ->get();

        foreach ($vencidas as $subrogacion) {
            $subrogacion->update(['estado' => EstadoSubrogacion::FINALIZADA->value]);
        }

        return ['caducadas' => $vencidas->count(), 'fecha' => $fecha];
    }

    public function cancelar(int $subrogacionId, string $motivo): Subrogacion
    {
        return DB::transaction(function () use ($subrogacionId, $motivo) {
            $subrogacion = Subrogacion::with('movimientoPersonal')->findOrFail($subrogacionId);

            // Una pendiente también se cancela: es lo natural cuando Talento
            // Humano se arrepiente antes de que la acción llegue a registrarse.
            if (! in_array($subrogacion->estado, [
                EstadoSubrogacion::PENDIENTE,
                EstadoSubrogacion::ACTIVA,
            ], true)) {
                throw new ReglaNegocioException(
                    'Solo se pueden cancelar subrogaciones pendientes o activas.'
                );
            }

            $subrogacion->update([
                'estado'       => EstadoSubrogacion::CANCELADA,
                // Hasta la víspera surtió efecto: es lo que paga la nómina
                // (fase 7.3a).
                'cancelada_en' => now()->toDateString(),
                'observacion'  => $this->conNota($subrogacion->observacion, "Cancelado: {$motivo}"),
            ]);

            $this->cerrarElActoQueLaRespaldaba($subrogacion, $motivo);

            return $subrogacion;
        });
    }

    /**
     * Cuando el subrogante o el titular cesan, la subrogación se cierra sola
     * (diseño de Acciones de Personal, 9.5; fase 2.1). Hasta aquí seguía activa:
     * el cesado conservaba la firma del puesto, o el subrogante seguía
     * reemplazando a alguien que ya no estaba.
     *
     * La activa termina el día en que rige la cesación, con su constancia en la
     * bitácora; la pendiente se cancela, y con ella su acción si aún no se
     * registró. Lo llama el motor de efectos al cerrar el vínculo.
     *
     * @return int Cuántas se cerraron.
     */
    public function cerrarPorCesacion(MovimientoPersonal $cesacion): int
    {
        $servidorId = $cesacion->servidor_id;

        $afectadas = Subrogacion::whereIn('estado', [
            EstadoSubrogacion::PENDIENTE->value,
            EstadoSubrogacion::ACTIVA->value,
        ])
            ->where(fn ($q) => $q->where('servidor_subrogante_id', $servidorId)
                ->orWhere('servidor_subrogado_id', $servidorId))
            ->orderBy('id')
            ->get();

        foreach ($afectadas as $subrogacion) {
            $papel = $subrogacion->servidor_subrogante_id === $servidorId ? 'del subrogante' : 'del titular';
            $motivo = "Cesación {$papel} ({$cesacion->codigo_registro}).";

            if ($subrogacion->estado === EstadoSubrogacion::PENDIENTE) {
                $this->cancelar($subrogacion->id, $motivo);

                continue;
            }

            $subrogacion->update([
                'estado'      => EstadoSubrogacion::FINALIZADA,
                // Termina el día en que rige la cesación, como dice la
                // bitácora. Hasta aquí conservaba el fin previsto, y la nómina
                // habría pagado la diferencia de días que no subrogó (fase 7.3a).
                'fecha_fin'   => $this->finPorCesacion($subrogacion, $cesacion),
                'observacion' => $this->conNota($subrogacion->observacion, "Finalizada: {$motivo}"),
            ]);

            $this->anotarEnLaBitacora(
                $subrogacion,
                TipoEventoVinculo::SUBROGACION_FINALIZADA,
                "Fin de {$subrogacion->tipo->etiqueta()}: terminó el "
                    .$cesacion->fecha_efectiva->format('d/m/Y')." por la cesación {$papel}.",
            );
        }

        return $afectadas->count();
    }

    /**
     * El fin de la subrogación que cierra una cesación: el día en que rige, si
     * es antes del previsto y no antes de que empezara.
     */
    private function finPorCesacion(Subrogacion $subrogacion, MovimientoPersonal $cesacion): ?string
    {
        $dia = $cesacion->fecha_efectiva;

        if (! $dia || $dia->lt($subrogacion->fecha_inicio)
            || ($subrogacion->fecha_fin && $subrogacion->fecha_fin->lte($dia))
        ) {
            return $subrogacion->fecha_fin?->toDateString();
        }

        return $dia->toDateString();
    }

    /**
     * Cancelar la subrogación también cierra su Acción de Personal.
     *
     * El enlace funcionaba en una sola dirección: anular la acción cancelaba la
     * subrogación, pero cancelar la subrogación no tocaba la acción. El borrador
     * se quedaba en la bandeja de Talento Humano para siempre, sin poder
     * editarse —desde el 2026-09-28 una subrogación no se edita— y sin que nadie
     * fuera a suscribir un acto cuyo objeto ya no existe.
     *
     * Qué se hace depende de hasta dónde llegó la acción, y esa es toda la
     * regla:
     *
     * - BORRADOR o SUSCRITA (la subrogación estaba PENDIENTE): el acto todavía
     *   no produjo efectos, así que se anula y desaparece de la bandeja,
     *   heredando el motivo de la cancelación.
     * - REGISTRADA o NOTIFICADA (la subrogación estaba ACTIVA): un acto
     *   administrativo registrado no se borra, y el grafo de estados tampoco lo
     *   permite. Queda constancia en la bitácora del vínculo, igual que la
     *   finalización anticipada.
     *
     * Hasta la fase 1.2 esa constancia era una fila de `movimientos_personal`
     * con el tipo de la acción, y salía en el historial con el botón del PDF.
     *
     * Las subrogaciones anteriores al enlace (2026-08-04) no tienen acción: ahí
     * no hay nada que cerrar.
     */
    private function cerrarElActoQueLaRespaldaba(Subrogacion $subrogacion, string $motivo): void
    {
        $movimiento = $subrogacion->movimientoPersonal;

        if (! $movimiento) {
            return;
        }

        if (in_array($movimiento->estado, [
            EstadoAccionPersonal::BORRADOR,
            EstadoAccionPersonal::SUSCRITA,
        ], true)) {
            // El servicio de estados se resuelve aquí y no por el constructor:
            // él ya depende de este servicio —llama a activarPorMovimiento() y a
            // cancelarPorMovimiento()—, y pedirlo por inyección cerraría el
            // círculo en el contenedor.
            //
            // Anular dispara aplicarAnulacion(), que a su vez llama a
            // cancelarPorMovimiento(). No hay bucle: para entonces la
            // subrogación ya está CANCELADA y ese método solo mira pendientes y
            // activas, así que queda en no-op. Es deliberado, y hay una prueba
            // que lo fija.
            app(MovimientoPersonalStateService::class)->transicionar(
                $movimiento,
                EstadoAccionPersonal::ANULADA,
                ['motivo_anulacion' => "Se canceló la subrogación que respaldaba. {$motivo}"],
            );

            return;
        }

        $this->anotarEnLaBitacora(
            $subrogacion,
            TipoEventoVinculo::SUBROGACION_CANCELADA,
            "Cancelación de {$subrogacion->tipo->etiqueta()}: dejó de surtir efecto "
                .($subrogacion->fecha_fin ? "antes del {$subrogacion->fecha_fin->format('d/m/Y')} previsto" : 'el '.now()->format('d/m/Y'))
                .". {$motivo}",
        );
    }

    /**
     * Constancia de lo que le pasó a una subrogación después de surtir efecto,
     * en la bitácora del subrogante. Enlaza la subrogación y la acción que la
     * respaldaba, que antes había que adivinar por la fecha.
     */
    private function anotarEnLaBitacora(
        Subrogacion $subrogacion,
        TipoEventoVinculo $tipo,
        string $descripcion
    ): void {
        EventoVinculo::create([
            'servidor_id'            => $subrogacion->servidor_subrogante_id,
            'subrogacion_id'         => $subrogacion->id,
            'movimiento_personal_id' => $subrogacion->movimiento_personal_id,
            'tipo'                   => $tipo,
            'fecha'                  => now()->toDateString(),
            'descripcion'            => $descripcion,
            'registrado_por'         => auth()->id(),
        ]);
    }

    /**
     * Lo que Talento Humano necesita ver en la pantalla de administración:
     * las que surten efecto y las que esperan que su Acción de Personal se
     * registre.
     *
     * Hubo un `listarActivas()` que respondía otra pregunta —¿quién está
     * subrogando ahora mismo?—: filtraba por ventana de fechas y excluía las
     * pendientes, así que una subrogación recién registrada desaparecía de la
     * pantalla y no había forma de seguir su aprobación ni de cancelarla. Se
     * retiró el 2026-09-28 junto con su endpoint: no lo consumía nadie, y a
     * «quién ejerce hoy» responden `Subrogacion::activaEnFecha()` y la relación
     * `UnidadAdministrativa::subrogacionesVigentes()`, que es la que pinta el
     * organigrama.
     *
     * Las vencidas se excluyen por fecha, no solo por estado: caducarVencidas()
     * las cierra a diario, pero si el scheduler está caído la pantalla no puede
     * quedar mostrando como vigente algo que terminó hace meses.
     *
     * Pagina del lado del servidor, con 15 por página como el resto de los
     * listados: devolver la colección entera dejaba crecer la respuesta sin
     * tope —nada limita cuántas subrogaciones vigentes puede haber— y la
     * pantalla no tenía paginador que mostrar.
     */
    public function listarVigentes(array $filtros = []): LengthAwarePaginator
    {
        $hoy = now()->toDateString();

        $query = Subrogacion::with([
            'subrogante', 'subrogado', 'unidadAdministrativa', 'puestoSubrogado.cargo',
            'movimientoPersonal:id,estado,codigo_registro',
            // Quién la registró: se guardaba desde el principio y no salía en
            // ningún listado, así que no había forma de saberlo sin abrir la BD.
            'registradoPorUsuario:id,email,servidor_id',
            'registradoPorUsuario.servidor:id,nombre,apellido',
        ])->whereIn('estado', [
            EstadoSubrogacion::PENDIENTE->value,
            EstadoSubrogacion::ACTIVA->value,
        ])->where(fn ($q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $hoy));

        if (!empty($filtros['unidad_administrativa_id'])) {
            $query->where('unidad_administrativa_id', $filtros['unidad_administrativa_id']);
        }

        if (!empty($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        // Las pendientes primero: son las que exigen una decisión.
        return $query->orderByRaw("CASE WHEN estado = 'pendiente' THEN 0 ELSE 1 END")
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->paginate(min(max((int) ($filtros['per_page'] ?? 15), 1), 100));
    }

    /**
     * Historial del servidor en ambos papeles: las que subrogó y aquellas en
     * las que fue el titular subrogado.
     *
     * Antes solo miraba servidor_subrogante_id, así que en el expediente del
     * titular no quedaba rastro de que otra persona ocupó su puesto — un dato
     * que importa justamente cuando hay que reconstruir quién ejercía el cargo
     * en una fecha dada.
     */
    public function listarPorServidor(int $servidorId): Collection
    {
        return Subrogacion::with([
            'subrogante', 'subrogado', 'unidadAdministrativa', 'puestoSubrogado.cargo',
            'movimientoPersonal:id,estado,codigo_registro',
            // Quién la registró: se guardaba desde el principio y no salía en
            // ningún listado, así que no había forma de saberlo sin abrir la BD.
            'registradoPorUsuario:id,email,servidor_id',
            'registradoPorUsuario.servidor:id,nombre,apellido',
        ])->where(fn ($q) => $q
            ->where('servidor_subrogante_id', $servidorId)
            ->orWhere('servidor_subrogado_id', $servidorId)
        )
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get();
    }
}
