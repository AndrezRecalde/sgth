<?php

namespace App\Services\Estructura;

use App\Contracts\Estructura\EstructuraServiceInterface;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Estructura\Puesto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use App\Exceptions\ReglaNegocioException;

final class EstructuraService implements EstructuraServiceInterface
{
    // ── GESTIÓN DE UNIDADES ADMINISTRATIVAS ──────────────────────────────────

    public function listarUnidades(array $filtros): LengthAwarePaginator
    {
        return UnidadAdministrativa::query()
            ->with(['padre'])
            ->when(isset($filtros['nivel']), fn($q) => $q->where('nivel', $filtros['nivel']))
            ->when(isset($filtros['estado']), fn($q) => $q->where('estado', $filtros['estado']))
            ->orderBy('nivel')
            ->orderBy('nombre')
            ->paginate($filtros['por_pagina'] ?? 15);
    }

    public function listarUnidadesTodas(array $filtros): Collection
    {
        return UnidadAdministrativa::query()
            ->when(
                isset($filtros['nivel']),
                fn($q) => $q->where('nivel', $filtros['nivel'])
            )
            ->when(
                isset($filtros['estado']),
                fn($q) => $q->where('estado', $filtros['estado'])
            )
            ->where('estado', true)
            ->orderBy('nivel')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'nivel', 'acronimo',
                   'codigo', 'unidad_padre_id']);
    }

    public function crearUnidad(array $datos): UnidadAdministrativa
    {
        $this->validarRaizUnica($datos['unidad_padre_id'] ?? null, null);

        $datos['nivel'] = $this->nivelSegunPadre($datos['unidad_padre_id'] ?? null);

        return DB::transaction(function () use ($datos) {
            // Antes del insert, no después: el índice único parcial salta en
            // el propio INSERT si la marca sigue puesta en otra unidad.
            $this->liberarAnclajesDeFirma(null, $datos);

            return UnidadAdministrativa::create($datos);
        });
    }

    public function obtenerUnidad(int $id): UnidadAdministrativa
    {
        // `tipoUnidad` no es decorativo aquí: el detalle alimenta el formulario
        // de edición, y sin él el tipo de proceso llegaba vacío y se guardaba
        // en blanco al actualizar cualquier otro campo.
        return UnidadAdministrativa::with(['padre', 'hijos', 'puestos', 'tipoUnidad'])
            ->findOrFail($id);
    }

    public function actualizarUnidad(int $id, array $datos): UnidadAdministrativa
    {
        $unidad = $this->obtenerUnidad($id);

        if (array_key_exists('unidad_padre_id', $datos)) {
            $this->validarRaizUnica($datos['unidad_padre_id'], $id);
            $this->validarNuevoPadre($unidad, $datos['unidad_padre_id']);
            $datos['nivel'] = $this->nivelSegunPadre($datos['unidad_padre_id']);
        }

        return DB::transaction(function () use ($unidad, $id, $datos) {
            $this->liberarAnclajesDeFirma($id, $datos);

            $unidad->update($datos);

            // Mover una unidad arrastra su rama entera: si la rama se queda con
            // el nivel del padre anterior, el organigrama la dibuja en la fila
            // equivocada y `PROFUNDIDAD_MAXIMA` deja de proteger nada.
            if (array_key_exists('nivel', $datos)) {
                $this->recalcularNivelDescendientes($unidad);
            }

            return $unidad;
        });
    }

    /**
     * Profundidad máxima del orgánico: institución → unidad → subproceso.
     *
     * El organigrama gráfico y el PDF reservan una fila por nivel; un cuarto
     * nivel no tendría dónde dibujarse. El tope se aplica al crear y al mover,
     * porque mover una rama es la otra forma de ganar profundidad.
     */
    public const PROFUNDIDAD_MAXIMA = 3;

    /**
     * El orgánico tiene una sola raíz: la institución.
     *
     * No es una preferencia estética. El organigrama de nodos dibuja la
     * primera raíz y el PDF arma su portada con ella, así que una segunda
     * unidad sin padre no aparecía en ninguna de las dos vistas: se guardaba
     * bien y era invisible. O se dibujan todas, o no se deja crear una
     * segunda; esto último es lo que corresponde a un orgánico institucional.
     */
    private function validarRaizUnica(?int $unidadPadreId, ?int $idEnEdicion): void
    {
        if ($unidadPadreId !== null) {
            return;
        }

        $raiz = UnidadAdministrativa::whereNull('unidad_padre_id')
            ->when($idEnEdicion !== null, fn ($q) => $q->where('id', '!=', $idEnEdicion))
            ->first();

        if ($raiz !== null) {
            throw new ReglaNegocioException(
                'Ya existe la unidad raíz «'.$raiz->nombre.'»: el organigrama admite '
                .'una sola institución. Indique de qué unidad depende esta.'
            );
        }
    }

    /**
     * El nivel no se pide: se deriva del padre. Es la única fuente que no puede
     * contradecir al árbol, y quien registra una unidad no tiene por qué saber
     * qué número le toca.
     */
    private function nivelSegunPadre(?int $unidadPadreId): int
    {
        if ($unidadPadreId === null) {
            return 1;
        }

        $padre = UnidadAdministrativa::findOrFail($unidadPadreId);

        if ($padre->nivel >= self::PROFUNDIDAD_MAXIMA) {
            throw new ReglaNegocioException(
                'La estructura admite hasta '.self::PROFUNDIDAD_MAXIMA.' niveles: '
                .'institución, unidad administrativa y subproceso. '
                .'«'.$padre->nombre.'» ya es un subproceso y no puede tener unidades debajo.'
            );
        }

        return $padre->nivel + 1;
    }

    /**
     * Un padre inválido rompe el árbol de forma irreparable: colgar una unidad
     * de su propia descendencia crea un ciclo que deja colgado a todo el que
     * recorra el organigrama.
     */
    private function validarNuevoPadre(UnidadAdministrativa $unidad, ?int $nuevoPadreId): void
    {
        if ($nuevoPadreId === null) {
            return;
        }

        if ($nuevoPadreId === $unidad->id) {
            throw new ReglaNegocioException('Una unidad administrativa no puede ser hija de sí misma.');
        }

        if ($this->idsDescendientes($unidad)->contains($nuevoPadreId)) {
            throw new ReglaNegocioException(
                'No se puede colgar la unidad de una de sus propias subunidades.'
            );
        }

        $profundidadRama = $this->profundidadRama($unidad);
        $nivelDestino    = $this->nivelSegunPadre($nuevoPadreId);

        if ($nivelDestino + $profundidadRama - 1 > self::PROFUNDIDAD_MAXIMA) {
            throw new ReglaNegocioException(
                'No se puede mover «'.$unidad->nombre.'» ahí: sus subprocesos quedarían '
                .'por debajo del nivel '.self::PROFUNDIDAD_MAXIMA.'.'
            );
        }
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function idsDescendientes(UnidadAdministrativa $unidad): \Illuminate\Support\Collection
    {
        return $unidad->hijos->flatMap(
            fn (UnidadAdministrativa $hijo) => $this->idsDescendientes($hijo)->push($hijo->id)
        );
    }

    /** Alto de la rama contando la propia unidad: 1 si no tiene hijos. */
    private function profundidadRama(UnidadAdministrativa $unidad): int
    {
        return 1 + ($unidad->hijos
            ->map(fn (UnidadAdministrativa $hijo) => $this->profundidadRama($hijo))
            ->max() ?? 0);
    }

    private function recalcularNivelDescendientes(UnidadAdministrativa $unidad): void
    {
        $unidad->load('hijos');

        foreach ($unidad->hijos as $hijo) {
            $hijo->update(['nivel' => $unidad->nivel + 1]);
            $this->recalcularNivelDescendientes($hijo);
        }
    }

    /**
     * Solo puede haber una unidad de Talento Humano y una máxima autoridad
     * (índices únicos parciales). Al marcar una nueva se desmarca la anterior,
     * que es lo que espera quien reorganiza el orgánico: mover el anclaje, no
     * toparse con un error de base de datos.
     *
     * `$id` es nulo cuando la unidad que va a llevar la marca todavía no
     * existe: al crearla no hay ninguna fila que excluir.
     */
    private function liberarAnclajesDeFirma(?int $id, array $datos): void
    {
        foreach (['es_unidad_talento_humano', 'es_maxima_autoridad'] as $bandera) {
            if (empty($datos[$bandera])) {
                continue;
            }

            UnidadAdministrativa::where($bandera, true)
                ->when($id !== null, fn ($q) => $q->where('id', '!=', $id))
                ->update([$bandera => false]);
        }
    }

    /**
     * Siguiente código libre bajo un padre: `GADPE` → `GADPE-01` → `GADPE-01-03`.
     *
     * El código dice dónde está la unidad en el árbol, que es lo único que un
     * código puede aportar y que un sufijo aleatorio no aporta. El secuencial
     * se busca probando huecos contra la tabla en vez de leyendo el mayor de
     * los hermanos: así no depende de que los códigos vecinos sigan el
     * formato, y una unidad borrada no reabre su número —los `deleted_at`
     * cuentan, porque el índice único también los cuenta.
     *
     * Sin padre no hay prefijo del cual colgar, así que devuelve cadena vacía
     * y el código se escribe a mano. Solo ocurre con la primera unidad de
     * todas, la institución.
     */
    public function sugerirCodigo(?int $unidadPadreId): string
    {
        if ($unidadPadreId === null) {
            return '';
        }

        $padre = UnidadAdministrativa::withTrashed()->findOrFail($unidadPadreId);
        $prefijo = trim((string) $padre->codigo);

        if ($prefijo === '') {
            return '';
        }

        for ($n = 1; $n <= 99; $n++) {
            $candidato = sprintf('%s-%02d', $prefijo, $n);

            $ocupado = UnidadAdministrativa::withTrashed()
                ->where('codigo', $candidato)
                ->exists();

            if (! $ocupado) {
                return $candidato;
            }
        }

        // 99 hermanos bajo una misma unidad no es un caso real; si llegara a
        // darse, es mejor devolver vacío y que se escriba a mano que entregar
        // un código que la base va a rechazar por duplicado.
        return '';
    }

    public function eliminarUnidad(int $id): void
    {
        $unidad = $this->obtenerUnidad($id);
        
        if ($unidad->hijos()->exists()) {
            throw new ReglaNegocioException('No se puede eliminar la unidad porque tiene subunidades o procesos dependientes.');
        }

        if ($unidad->puestos()->exists()) {
            throw new ReglaNegocioException('No se puede eliminar la unidad porque tiene puestos orgánicos asignados.');
        }

        $unidad->delete();
    }

    // ── ORGANIGRAMA ──────────────────────────────────────────────────────────

    public function obtenerOrganigrama(): Collection
    {
        return UnidadAdministrativa::whereNull('unidad_padre_id')
            ->where('estado', true)
            ->with(['tipoUnidad'])
            ->orderBy('nivel')
            ->orderBy('nombre')
            ->get()
            ->map(fn($u) => $this->cargarHijosRecursivo($u));
    }

    /**
     * El mismo árbol, sin nada que identifique a una persona.
     *
     * Lo consume la página abierta a internet y el PDF que se descarga desde
     * ella. Se carga aparte en vez de filtrar el detallado al serializar
     * porque lo que no se consulta no se puede publicar por descuido: aquí no
     * hay subrogantes ni ocupantes que se puedan escapar en una respuesta.
     */
    public function obtenerOrganigramaPublico(): Collection
    {
        return UnidadAdministrativa::whereNull('unidad_padre_id')
            ->where('estado', true)
            ->orderBy('nivel')
            ->orderBy('nombre')
            ->get()
            ->map(fn ($u) => $this->cargarHijosPublicoRecursivo($u));
    }

    private function cargarHijosPublicoRecursivo(
        UnidadAdministrativa $unidad
    ): UnidadAdministrativa {
        $unidad->load([
            'tipoUnidad',
            'hijos' => fn ($q) => $q->where('estado', true)->orderBy('nombre'),
        ]);

        $unidad->hijos->each(
            fn ($hijo) => $this->cargarHijosPublicoRecursivo($hijo)
        );

        return $unidad;
    }

    private function cargarHijosRecursivo(
        UnidadAdministrativa $unidad
    ): UnidadAdministrativa {
        $unidad->load([
            'tipoUnidad',
            'hijos' => fn($q) => $q->where('estado', true)
                                    ->orderBy('nombre'),
            'puestos' => fn($q) => $q->where('puestos.activo', true)
                                      ->leftJoin('cargos', 'puestos.cargo_id', '=', 'cargos.id')
                                      ->orderBy('cargos.nombre', 'asc')
                                      ->select('puestos.*'),
            // Quién está ejerciendo por subrogación o encargo hoy. Es la única
            // forma de leer el organigrama y saber que el nombre del titular no
            // es quien realmente despacha en esa unidad.
            'subrogacionesVigentes.subrogante',
            'subrogacionesVigentes.puestoSubrogado.cargo',
        ]);

        $unidad->hijos->each(
            fn($hijo) => $this->cargarHijosRecursivo($hijo)
        );

        return $unidad;
    }

    // ── GESTIÓN DE PUESTOS ───────────────────────────────────────────────────

    public function listarPuestos(array $filtros): LengthAwarePaginator
    {
        return Puesto::query()
            ->with([
                'cargo', 'unidadAdministrativa', 'grupoOcupacional', 'partidaPresupuestaria',
                // El ocupante vigente: lo necesita el formulario de subrogación
                // para derivar al titular en vez de pedirlo suelto.
                'contratosVigentes.servidor:id,cedula,nombre,segundo_nombre,apellido,segundo_apellido',
            ])
            ->leftJoin('cargos', 'cargos.id', '=', 'puestos.cargo_id')
            ->select('puestos.*')
            ->when(
                isset($filtros['unidad_administrativa_id']),
                fn($q) => $q->where(
                    'puestos.unidad_administrativa_id',
                    $filtros['unidad_administrativa_id']
                )
            )
            ->when(
                isset($filtros['regimen_laboral']),
                fn($q) => $q->where('puestos.regimen_laboral', $filtros['regimen_laboral'])
            )
            ->when(
                isset($filtros['es_jefe']),
                fn($q) => $q->where('puestos.es_jefe', $filtros['es_jefe'])
            )
            ->when(
                isset($filtros['activo']),
                fn($q) => $q->where('puestos.activo', $filtros['activo'])
            )
            ->orderBy('cargos.nombre', 'asc')
            ->paginate($filtros['per_page'] ?? 15);
    }

    public function crearPuesto(array $datos): Puesto
    {
        return DB::transaction(function () use ($datos) {
            // Regla de Negocio: Solo puede haber un Jefe por Unidad Administrativa
            if (isset($datos['es_jefe']) && $datos['es_jefe']) {
                $existeJefe = Puesto::where('unidad_administrativa_id', $datos['unidad_administrativa_id'])
                    ->where('es_jefe', true)
                    ->exists();

                if ($existeJefe) {
                    throw new ReglaNegocioException('La unidad administrativa seleccionada ya posee un puesto de conducción o jefatura activo.');
                }
            }

            return Puesto::create($datos)->load('cargo');
        });
    }

    public function obtenerPuesto(int $id): Puesto
    {
        return Puesto::with([
            'cargo',
            'unidadAdministrativa',
            'grupoOcupacional',
        ])->findOrFail($id);
    }

    public function actualizarPuesto(int $id, array $datos): Puesto
    {
        return DB::transaction(function () use ($id, $datos) {
            $puesto = $this->obtenerPuesto($id);

            if (isset($datos['es_jefe']) && $datos['es_jefe'] && !$puesto->es_jefe) {
                $unidadId = $datos['unidad_administrativa_id'] ?? $puesto->unidad_administrativa_id;
                
                $existeJefe = Puesto::where('unidad_administrativa_id', $unidadId)
                    ->where('es_jefe', true)
                    ->where('id', '!=', $puesto->id)
                    ->exists();

                if ($existeJefe) {
                    throw new ReglaNegocioException('La unidad administrativa ya posee un puesto de conducción asignado.');
                }
            }

            $puesto->update($datos);
            return $puesto;
        });
    }

    public function eliminarPuesto(int $id): void
    {
        $puesto = $this->obtenerPuesto($id);
        $puesto->delete();
    }
}
