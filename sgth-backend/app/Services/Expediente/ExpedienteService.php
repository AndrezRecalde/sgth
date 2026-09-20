<?php

namespace App\Services\Expediente;

use App\Contracts\Expediente\ExpedienteServiceInterface;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\DocumentoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExpedienteService implements ExpedienteServiceInterface
{
    private const MAXIMO_POR_PAGINA = 500;

    /** Dónde se busca el texto del buscador del listado. */
    private const COLUMNAS_BUSQUEDA = [
        'cedula', 'nombre', 'segundo_nombre', 'apellido', 'segundo_apellido',
    ];

    /**
     * La unidad pedida y todo lo que cuelga de ella. El organigrama tiene tres
     * niveles y unas decenas de filas, así que se recorre en memoria con una
     * sola consulta en vez de una recursiva por nivel.
     *
     * @return list<int>
     */
    private function unidadConDescendientes(int $unidadId): array
    {
        $porPadre = UnidadAdministrativa::query()
            ->select('id', 'unidad_padre_id')
            ->get()
            ->groupBy('unidad_padre_id');

        $ids = [];
        $pendientes = [$unidadId];

        while ($pendientes) {
            $actual = array_pop($pendientes);
            if (in_array($actual, $ids, true)) {
                continue;
            }
            $ids[] = $actual;
            foreach ($porPadre->get($actual, collect()) as $hija) {
                $pendientes[] = (int) $hija->id;
            }
        }

        return $ids;
    }

    /**
     * Crea la ficha personal del servidor. Nada más.
     *
     * Antes generaba también un MovimientoPersonal de tipo 'ingreso' fechado
     * el día del registro, sin puesto, unidad ni nombramiento. Se retiró: dar
     * de alta una ficha es un hecho administrativo interno, no una Acción de
     * Personal. Aquel movimiento nacía en estado 'registrada', así que
     * aparecía en la bandeja y permitía descargar el PDF de un acto que nunca
     * ocurrió — con ambas columnas en blanco y sin firmantes.
     *
     * El vínculo laboral nace donde corresponde: de la Acción de Personal de
     * ingreso, o de la carga inicial para quienes ya estaban vinculados antes
     * de que el sistema existiera (ver VinculacionInicialService). El rastro
     * de la creación de la ficha queda en activity_log.
     */
    public function crearServidorBasico(array $datos): Servidor
    {
        return Servidor::create($datos);
    }

    /**
     * puesto_id/unidad_administrativa_id ya no pueden llegar en $datos
     * (UpdateServidorRequest los rechaza con 'prohibited') — el cambio de
     * puesto/unidad se hace exclusivamente registrando un
     * MovimientoPersonal de traslado/ascenso/traspaso/cambio_administrativo,
     * que materializa el vínculo nuevo vía
     * ContratoServidorService::reestructurarDesdeMovimiento() al llegar a
     * REGISTRADA.
     */
    public function actualizarServidor(int $id, array $datos): Servidor
    {
        $servidor = Servidor::findOrFail($id);
        $servidor->update($datos);

        return $servidor;
    }

    public function subirDocumento(int $servidorId, array $datos, UploadedFile $archivo): DocumentoServidor
    {
        $servidor = Servidor::findOrFail($servidorId);

        // Guardado seguro del archivo en storage
        $ruta = $archivo->storeAs(
            "expedientes/{$servidor->cedula}/documentos",
            time() . '_' . $archivo->getClientOriginalName(),
            'local' // Storage local blindado
        );

        if (!$ruta) {
            throw new ReglaNegocioException("Error al almacenar físicamente el archivo en el servidor.");
        }

        return DocumentoServidor::create([
            'servidor_id'       => $servidor->id,
            'tipo_documento'    => $datos['tipo_documento'],
            'nombre_archivo'    => $archivo->getClientOriginalName(),
            'ruta_archivo'      => $ruta,
            'tamanio_bytes'     => $archivo->getSize(),
            'mime_type'         => $archivo->getMimeType(),
            'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
            'descripcion'       => $datos['descripcion'] ?? null,
            'estado'            => true,
            'subido_por'        => auth()->id(),
        ]);
    }

    public function obtenerExpedienteCompleto(int $servidorId): Servidor
    {
        return Servidor::with([
            'usuario',
            'unidadAdministrativa',
            // El cargo y la partida del puesto alimentan el bloque "situación
            // actual" del documento de Acción de Personal. Sin cargarlos, ese
            // bloque sale en blanco aunque el dato exista.
            'puesto.cargo',
            'puesto.partidaPresupuestaria',
            // El accesor `rmu` del puesto lee su grupo ocupacional: sin
            // cargarlo aquí, ServidorResource lo resolvería con una consulta
            // suelta por cada servidor.
            'puesto.grupoOcupacional',
            'contratoVigente.puesto.cargo',
            'contratoVigente.puesto.partidaPresupuestaria',
            'contratoVigente.unidadAdministrativa',
            // Sin 'documentos' ni 'movimientos': nadie los lee de aquí —las
            // pestañas del expediente y la bandeja tienen sus propios
            // endpoints— y este detalle se pide cada vez que se abre la ficha.
        ])->findOrFail($servidorId);
    }

    /**
     * Línea de tiempo del expediente: une el historial de ContratoServidor
     * (vínculos) y MovimientoPersonal (eventos) de un servidor en una sola
     * secuencia cronológica, incluso cuando cambió de régimen jurídico
     * entre vínculos. Pensada para ser la base del reporte a SIITH/SUT:
     * lista plana (no agrupada por vínculo) con 'regimen_juridico' en
     * cada ítem, para poder filtrar directamente por régimen y rango de
     * fechas.
     *
     * Ordenamiento de eventos: se usa fecha_efectiva, la única columna de
     * fecha operativa de movimientos_personal (NOT NULL desde la
     * creación) — no existen fecha_vigencia ni fecha_emision en este
     * schema. Esto aplica igual a eventos aún no registrados (borrador,
     * informe_uath, dictamen_presupuestario, suscrita): fecha_efectiva ya
     * está fijada desde que se crean, así que su posición cronológica es
     * siempre determinable.
     */
    public function lineaDeTiempo(int $servidorId): Collection
    {
        Servidor::findOrFail($servidorId);

        $contratos = ContratoServidor::where('servidor_id', $servidorId)
            ->orderBy('fecha_inicio')
            ->get();

        $movimientos = MovimientoPersonal::where('servidor_id', $servidorId)
            ->orderBy('fecha_efectiva')
            ->get();

        $linea = collect();

        foreach ($contratos as $contrato) {
            $regimen = $this->regimenDeContrato($contrato);

            $linea->push([
                'tipo'             => 'vinculo_iniciado',
                'fecha'            => $contrato->fecha_inicio?->toDateString(),
                'regimen_juridico' => $regimen,
                'descripcion'      => "Inicio de vínculo: {$contrato->tipo_nombramiento?->etiqueta()}",
                'referencia'       => ['modelo' => 'ContratoServidor', 'id' => $contrato->id],
            ]);

            if ($contrato->fecha_fin) {
                $linea->push([
                    'tipo'             => 'vinculo_cerrado',
                    'fecha'            => $contrato->fecha_fin->toDateString(),
                    'regimen_juridico' => $regimen,
                    'descripcion'      => $contrato->motivo_fin
                        ? "Cierre de vínculo: {$contrato->motivo_fin}"
                        : 'Cierre de vínculo',
                    'referencia'       => ['modelo' => 'ContratoServidor', 'id' => $contrato->id],
                ]);
            }
        }

        foreach ($movimientos as $movimiento) {
            $fecha = $movimiento->fecha_efectiva?->toDateString();
            [$regimen, $resueltoPor] = $this->resolverRegimenEnFecha($contratos, $fecha);

            $linea->push([
                'tipo'                 => 'evento',
                'fecha'                => $fecha,
                'regimen_juridico'     => $regimen,
                'regimen_resuelto_por' => $resueltoPor,
                'descripcion'          => $movimiento->descripcion,
                'referencia'           => ['modelo' => 'MovimientoPersonal', 'id' => $movimiento->id],
                'tipo_movimiento'      => $movimiento->tipo_movimiento?->value,
                'estado'               => $movimiento->estado?->value,
                'categoria'            => $movimiento->categoria?->value,
            ]);
        }

        return $linea->sortBy('fecha')->values();
    }

    /**
     * Tres regímenes posibles, no dos: esLosep() es binario (excluye
     * CODIGO_TRABAJO y SERVICIOS_PROFESIONALES por igual), así que no
     * alcanza para distinguir servicios profesionales — que es Código
     * Civil/LOSNCP, un régimen aparte, no Código de Trabajo. Detectarlo
     * explícitamente evita que un vínculo de servicios profesionales se
     * cuele en un reporte pensado para Código de Trabajo (SUT).
     */
    private function regimenDeContrato(ContratoServidor $contrato): ?string
    {
        if (!$contrato->tipo_nombramiento) {
            return null;
        }

        return match (true) {
            $contrato->tipo_nombramiento === TipoNombramiento::SERVICIOS_PROFESIONALES => 'codigo_civil_losncp',
            $contrato->tipo_nombramiento->esLosep() => 'losep',
            default => 'codigo_trabajo',
        };
    }

    /**
     * Resuelve el régimen jurídico vigente en una fecha dada, a partir de
     * los vínculos del servidor (ya ordenados por fecha_inicio).
     *
     * - 'vinculo_exacto': la fecha cae dentro de [fecha_inicio, fecha_fin]
     *   (o sin fecha_fin, si el vínculo sigue vigente) de algún contrato.
     * - 'vinculo_mas_cercano': no hay un vínculo que cubra exactamente esa
     *   fecha (hueco entre contratos), se usa el último iniciado antes.
     * - 'sin_vinculo': no hay ningún vínculo iniciado en o antes de esa
     *   fecha. Se devuelve null, nunca se adivina un régimen.
     *
     * @param  \Illuminate\Support\Collection<int, ContratoServidor>  $contratos
     * @return array{0: ?string, 1: string}
     */
    private function resolverRegimenEnFecha(Collection $contratos, ?string $fecha): array
    {
        if (!$fecha) {
            return [null, 'sin_vinculo'];
        }

        foreach ($contratos as $contrato) {
            $inicio = $contrato->fecha_inicio?->toDateString();
            $fin    = $contrato->fecha_fin?->toDateString();

            if ($inicio && $inicio <= $fecha && (!$fin || $fin >= $fecha)) {
                return [$this->regimenDeContrato($contrato), 'vinculo_exacto'];
            }
        }

        $masCercano = $contratos
            ->filter(fn (ContratoServidor $c) => $c->fecha_inicio && $c->fecha_inicio->toDateString() <= $fecha)
            ->last();

        if ($masCercano) {
            return [$this->regimenDeContrato($masCercano), 'vinculo_mas_cercano'];
        }

        return [null, 'sin_vinculo'];
    }

    public function listarServidores(array $filtros): mixed
    {
        $query = $this->filtrarServidores(
            Servidor::query()->with(['unidadAdministrativa', 'puesto.cargo', 'puesto.grupoOcupacional', 'contratoVigente']),
            $filtros
        );

        // Con tope: sin él, per_page=100000 devolvía la institución entera en
        // una respuesta. 500 porque el selector de subrogaciones todavía pide
        // esa cantidad para listar a todos.
        $perPage = isset($filtros['per_page'])
            ? min(max((int) $filtros['per_page'], 1), self::MAXIMO_POR_PAGINA) : 15;

        return $query->orderBy('apellido')->orderBy('nombre')
                     ->paginate($perPage);
    }

    /**
     * Filtros compartidos entre el listado paginado y la exportación de
     * nómina (Excel/PDF), para que ambos apliquen exactamente las mismas
     * reglas.
     */
    private function filtrarServidores($query, array $filtros)
    {
        // Búsqueda por nombre o cédula. Cada palabra tiene que aparecer en
        // alguna de las columnas: así «Juan Pérez» encuentra a quien se llama
        // Juan y se apellida Pérez. Antes cada columna se comparaba con la
        // frase entera, y buscar por nombre completo no devolvía a nadie.
        if (!empty($filtros['search'])) {
            foreach (preg_split('/\s+/', trim($filtros['search'])) as $palabra) {
                if ($palabra === '') {
                    continue;
                }
                $query->where(function ($q) use ($palabra) {
                    foreach (self::COLUMNAS_BUSQUEDA as $columna) {
                        $q->orWhere($columna, 'ilike', "%{$palabra}%");
                    }
                });
            }
        }

        // Una dirección incluye a sus jefaturas y subprocesos: filtrando por
        // «Gestión Administrativa» deben salir también los servidores de las
        // unidades que cuelgan de ella, no solo los asignados a ella misma.
        if (!empty($filtros['unidad_administrativa_id'])) {
            $query->whereIn(
                'unidad_administrativa_id',
                $this->unidadConDescendientes((int) $filtros['unidad_administrativa_id']),
            );
        }

        // Estado propio del servidor (activo/inactivo), no confundir con el
        // estado del contrato (vigente/terminado/cancelado).
        if (isset($filtros['estado']) && $filtros['estado'] !== '') {
            $query->where('estado', filter_var($filtros['estado'], FILTER_VALIDATE_BOOLEAN));
        }

        // El estado del vínculo ACTUAL, no de cualquiera que haya tenido:
        // «Terminado» devolvía también a quien hoy trabaja, porque en algún
        // momento cerró un contrato anterior.
        if (!empty($filtros['contrato_estado'])) {
            $query->whereHas('contratos', function ($q) use ($filtros) {
                $q->where('estado', $filtros['contrato_estado'])
                  ->whereRaw(
                      'contratos_servidor.id = (
                          select ultimo.id from contratos_servidor ultimo
                          where ultimo.servidor_id = contratos_servidor.servidor_id
                            and ultimo.deleted_at is null
                          order by ultimo.fecha_inicio desc, ultimo.id desc
                          limit 1
                      )'
                  );
            });
        }

        // Servidor "en funciones": activo y con contrato vigente. 'false' pide
        // lo contrario —el filtro «Inactivos»—, y con empty() se leía como
        // verdadero: la pantalla de inactivos listaba a los que trabajan.
        if (isset($filtros['en_funciones']) && $filtros['en_funciones'] !== '') {
            filter_var($filtros['en_funciones'], FILTER_VALIDATE_BOOLEAN)
                ? $query->where('estado', true)->whereHas('contratoVigente')
                : $query->where(fn ($q) => $q->where('estado', false)->orWhereDoesntHave('contratoVigente'));
        }

        // Fichas creadas a las que todavía no se les registró el vínculo.
        // Es un estado legítimo pero transitorio: la persona existe en el
        // sistema y no está contratada. Sin poder listarlas, quedan olvidadas
        // a medio registrar entre cientos de expedientes.
        //
        // Se comprueba con isset y no con empty: 'false' es un valor válido
        // —"solo los que sí tienen vínculo"— y empty() lo confundiría con no
        // haber pedido filtro.
        if (isset($filtros['pendiente_vinculacion']) && $filtros['pendiente_vinculacion'] !== '') {
            $sinVinculo = filter_var($filtros['pendiente_vinculacion'], FILTER_VALIDATE_BOOLEAN);

            $sinVinculo
                ? $query->doesntHave('contratoVigente')
                : $query->has('contratoVigente');
        }

        if (!empty($filtros['tipo_nombramiento'])) {
            $query->where('tipo_nombramiento',
                $filtros['tipo_nombramiento']);
        }

        if (isset($filtros['tiene_discapacidad'])
            && $filtros['tiene_discapacidad']) {
            $query->conDiscapacidad();
        }

        // Año de ingreso a la institución.
        if (!empty($filtros['anio_ingreso'])) {
            $query->whereYear('fecha_ingreso_institucion', $filtros['anio_ingreso']);
        }

        return $query;
    }

    /**
     * Exporta el listado completo de servidores (sin paginar) con las
     * columnas del formato de nómina usado por Talento Humano.
     */
    public function exportarServidores(array $filtros): \Illuminate\Support\Collection
    {
        $query = $this->filtrarServidores(
            Servidor::query()->with([
                'unidadAdministrativa',
                'puesto.cargo',
                'puesto.grupoOcupacional',
                'contratoVigente',
                // Para la fecha de salida de quien ya no está en funciones.
                'contratos',
                'usuario',
                'discapacidades',
                'historialAcademico',
            ]),
            $filtros
        );

        return $query->orderBy('apellido')->orderBy('nombre')->get()
            ->values()
            ->map(function (Servidor $servidor, int $index) {
                $puesto = $servidor->contratoVigente?->puesto ?? $servidor->puesto;
                $discapacidad = $servidor->discapacidades->first();
                $formacion = $servidor->historialAcademico
                    ->sortByDesc('fecha_fin')
                    ->first();

                $enFunciones = $servidor->estado && $servidor->contratoVigente;

                return [
                    'ITEM'                   => $index + 1,
                    'CÉDULA'                 => $servidor->cedula,
                    'NOMBRES Y APELLIDOS'    => trim(collect([
                        $servidor->apellido, $servidor->segundo_apellido,
                        $servidor->nombre, $servidor->segundo_nombre,
                    ])->filter()->join(' ')),
                    'GENERO'                 => $servidor->genero,
                    'ESTADO CIVIL'           => $servidor->estado_civil,
                    'TIPO DE DISCAPACIDAD'   => $discapacidad?->tipo_discapacidad?->etiqueta() ?? 'NO',
                    'PORCENTAJE'             => $discapacidad?->porcentaje ?? 0,
                    'CARGO'                  => $puesto?->cargo?->nombre,
                    'GRUPO OCUPACIONAL'      => $puesto?->grupoOcupacional?->denominacion_generica,
                    'R.M.U'                  => $servidor->contratoVigente?->remuneracion ?? $puesto?->rmu,
                    'R.A.U'                  => $servidor->contratoVigente?->rau,
                    'TIPO DE NOMBRAMIENTO'   => $servidor->tipo_nombramiento?->etiqueta(),
                    'GESTIÓN'                => $servidor->unidadAdministrativa?->nombre,
                    'FORMACIÓN'              => $formacion?->titulo_capacitacion,
                    'FECHA DE INGRESO'       => $servidor->fecha_ingreso_institucion?->format('Y-m-d'),
                    // Quien ya salió no tiene contrato vigente, así que la
                    // fecha se lee del último vínculo cerrado. Leyéndola del
                    // vigente, la columna salía vacía justo para ellos.
                    'FECHA DE SALIDA'        => $enFunciones
                        ? 'EN FUNCIONES'
                        : $servidor->contratos
                            ->filter(fn (ContratoServidor $c) => $c->fecha_fin)
                            ->sortByDesc('fecha_fin')
                            ->first()?->fecha_fin?->format('Y-m-d'),
                    'FECHA DE NACIMIENTO'    => $servidor->fecha_nacimiento?->format('Y-m-d'),
                    'EDAD'                   => $servidor->fecha_nacimiento?->age,
                    'DIRECCIÓN'              => $servidor->direccion_domicilio,
                    'CELULAR'                => $servidor->telefono_celular,
                    'CORREO PERSONAL'        => $servidor->correo_personal,
                    'CORREO INSTITUCIONAL'   => $servidor->usuario?->email,
                ];
            });
    }
}
