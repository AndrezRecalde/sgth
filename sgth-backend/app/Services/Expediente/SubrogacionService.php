<?php

namespace App\Services\Expediente;

use App\Contracts\Expediente\SubrogacionServiceInterface;
use App\Enums\CategoriaEventoVinculo;
use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoSubrogacion;
use App\Enums\TipoSubrogacion;
use App\Enums\PartidaPorModalidad;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Subrogacion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SubrogacionService implements SubrogacionServiceInterface
{
    public function registrar(array $datos): Subrogacion
    {
        // 1. Validación condicional de encargo vs subrogación
        if ($datos['tipo'] === TipoSubrogacion::ENCARGO->value) {
            $datos['servidor_subrogado_id'] = null;
        } elseif (empty($datos['servidor_subrogado_id'])) {
            throw new ReglaNegocioException("La subrogación requiere especificar el servidor titular a subrogar.");
        }

        $this->validarTitularDelPuesto($datos);

        // 2. Validación de fechas
        if (strtotime($datos['fecha_fin']) <= strtotime($datos['fecha_inicio'])) {
            throw new ReglaNegocioException("La fecha de fin debe ser estrictamente mayor a la fecha de inicio.");
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
        $enElRango = fn ($query) => $query
            ->whereIn('estado', [
                EstadoSubrogacion::PENDIENTE->value,
                EstadoSubrogacion::ACTIVA->value,
            ])
            ->where(function ($q) use ($datos) {
                $q->whereBetween('fecha_inicio', [$datos['fecha_inicio'], $datos['fecha_fin']])
                  ->orWhereBetween('fecha_fin', [$datos['fecha_inicio'], $datos['fecha_fin']])
                  ->orWhere(function ($interna) use ($datos) {
                      $interna->where('fecha_inicio', '<=', $datos['fecha_inicio'])
                              ->where('fecha_fin', '>=', $datos['fecha_fin']);
                  });
            });

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
                "El puesto de {$nombrePuesto} ya está cubierto del "
                .$delPuesto->fecha_inicio->format('d/m/Y').' al '
                .$delPuesto->fecha_fin->format('d/m/Y')
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

        return $texto.', del '.$subrogacion->fecha_inicio->format('d/m/Y')
            .' al '.$subrogacion->fecha_fin->format('d/m/Y').'.';
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

            $subrogacion->update([
                'estado' => EstadoSubrogacion::FINALIZADA
            ]);

            // Bitácora del expediente, no un acto administrativo nuevo: va sin
            // categoría a propósito —eso es lo que lo distingue de la acción
            // que abrió la subrogación— y en REGISTRADA, que es un hecho
            // consumado y no algo por aprobar. El estado se declara en vez de
            // heredar el default de BD para que se lea aquí y no en una
            // migración de hace meses.
            MovimientoPersonal::create([
                'servidor_id'     => $subrogacion->servidor_subrogante_id,
                'tipo_movimiento' => 'subrogacion',
                'clase'           => $subrogacion->tipo->value,
                'estado'          => EstadoAccionPersonal::REGISTRADA,
                'descripcion'     => "Finalización anticipada de {$subrogacion->tipo->etiqueta()}: "
                    ."terminó antes del {$subrogacion->fecha_fin->format('d/m/Y')} previsto.",
                'fecha_efectiva'  => now()->toDateString(),
                'autorizado_por'  => auth()->id(),
            ]);

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
        $vencidas = Subrogacion::where('estado', EstadoSubrogacion::ACTIVA->value)
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
                'estado'      => EstadoSubrogacion::CANCELADA,
                'observacion' => $this->conNota($subrogacion->observacion, "Cancelado: {$motivo}"),
            ]);

            $this->cerrarElActoQueLaRespaldaba($subrogacion, $motivo);

            return $subrogacion;
        });
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
     *   permite. Queda constancia en el historial con el mismo criterio que la
     *   finalización anticipada: sin categoría y ya en REGISTRADA, que es un
     *   hecho consumado y no algo por aprobar.
     *
     * Aviso sobre esa constancia, para que nadie se apoye en más de lo que hay:
     * `categoria` en null la distingue en los datos, pero HOY no la distingue
     * en la interfaz. Ni `TipoMovimientoPersonal::tieneDocumentoImprimible()`
     * —que decide por tipo, y subrogación imprime— ni `puedeDescargarPdf()` en
     * el frontend miran la categoría, así que la fila sale en el historial con
     * el botón de descargar PDF activo y genera un documento de Acción de
     * Personal con los firmantes en blanco, porque nunca se suscribió. Ya pasa
     * con la bitácora de `finalizar()`: esto no lo introduce, lo repite. El
     * arreglo va en esa puerta, no aquí.
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

        MovimientoPersonal::create([
            'servidor_id'     => $subrogacion->servidor_subrogante_id,
            'tipo_movimiento' => 'subrogacion',
            'clase'           => $subrogacion->tipo->value,
            'estado'          => EstadoAccionPersonal::REGISTRADA,
            'descripcion'     => "Cancelación de {$subrogacion->tipo->etiqueta()}: dejó de surtir "
                ."efecto antes del {$subrogacion->fecha_fin->format('d/m/Y')} previsto. {$motivo}",
            'fecha_efectiva'  => now()->toDateString(),
            'autorizado_por'  => auth()->id(),
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
        ])->whereDate('fecha_fin', '>=', $hoy);

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
