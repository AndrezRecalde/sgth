<?php

namespace App\Services\Comunicacion;

use App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface;
use App\Enums\EstadoCredencial;
use App\Enums\Permiso;
use App\Models\Comunicacion\Credencial;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * El Inicio de Comunicación Social (docs/diseno-comunicacion-social.md, 8;
 * fase 3.3): cuánto falta. Cada bloque sale solo con su permiso, como en el
 * tablero de Tecnologías: las fotos a quien las toma o consulta las
 * credenciales, las credenciales a quien las consulta y la identidad a la
 * persona designada para los logos. Un bloque sin permiso va en null.
 */
final class TableroComunicacionService
{
    /** Las unidades que se nombran en el avance de fotos; el resto se suma en «Otras». */
    private const UNIDADES_VISIBLES = 8;

    /** Días hacia adelante para avisar que una credencial vence con su contrato. */
    public const DIAS_POR_VENCER = 30;

    /** Cuántas próximas a vencer se nombran; el total va aparte. */
    private const PROXIMAS_VISIBLES = 8;

    public function __construct(private readonly IdentidadInstitucionalServiceInterface $identidad) {}

    /** @return array{fotografias: ?array<string, mixed>, credenciales: ?array<string, mixed>, identidad: ?array<string, mixed>} */
    public function para(User $user): array
    {
        $credenciales = $user->can(Permiso::VER_CREDENCIALES->value);

        return [
            // Solo a quien toma las fotos: con `ver-credenciales` bastaba, y el
            // bloque llevaba a admin-uath y al auditor a una pantalla que no
            // pueden abrir (docs/plan-roles-y-accesos.md, PR 14).
            'fotografias'  => $user->can(Permiso::GESTIONAR_FOTOGRAFIAS->value) ? $this->fotografias() : null,
            'credenciales' => $credenciales ? $this->credenciales() : null,
            'identidad'    => $user->can(Permiso::GESTIONAR_IDENTIDAD_INSTITUCIONAL->value) ? $this->identidad() : null,
        ];
    }

    /** @return array<string, mixed> */
    private function fotografias(): array
    {
        $filas = Servidor::query()
            ->where('servidores.estado', true)
            ->leftJoin('fotografias_servidor as f', fn ($j) => $j->on('f.servidor_id', '=', 'servidores.id')->where('f.vigente', true))
            ->selectRaw('servidores.unidad_administrativa_id as unidad_id, count(*) as activos, count(f.id) as con_foto')
            ->groupBy('servidores.unidad_administrativa_id')
            ->get();

        $nombres = UnidadAdministrativa::whereIn('id', $filas->pluck('unidad_id')->filter())->pluck('nombre', 'id');

        // Primero las que más fotos deben: es por donde conviene empezar.
        $unidades = $filas->map(fn ($f) => [
            'unidad_id' => $f->unidad_id,
            'unidad'    => $f->unidad_id ? ($nombres[$f->unidad_id] ?? 'Unidad') : 'Sin unidad',
            'activos'   => (int) $f->activos,
            'con_foto'  => (int) $f->con_foto,
        ])->sortBy([
            fn ($a, $b) => ($b['activos'] - $b['con_foto']) <=> ($a['activos'] - $a['con_foto']),
            fn ($a, $b) => strcmp($a['unidad'], $b['unidad']),
        ])->values();

        $visibles = $unidades->take(self::UNIDADES_VISIBLES)->all();
        $resto = $unidades->slice(self::UNIDADES_VISIBLES);

        if ($resto->isNotEmpty()) {
            $visibles[] = [
                'unidad_id' => null,
                'unidad'    => "Otras {$resto->count()} unidades",
                'activos'   => $resto->sum('activos'),
                'con_foto'  => $resto->sum('con_foto'),
            ];
        }

        $activos = (int) $filas->sum('activos');
        $conFoto = (int) $filas->sum('con_foto');

        return [
            'activos'    => $activos,
            'con_foto'   => $conFoto,
            'sin_foto'   => $activos - $conFoto,
            'por_unidad' => $visibles,
        ];
    }

    /** @return array<string, mixed> */
    private function credenciales(): array
    {
        $hoy = Carbon::today()->toDateString();
        $limite = Carbon::today()->addDays(self::DIAS_POR_VENCER)->toDateString();

        // Las vivas de quienes trabajan hoy. La fecha manda sobre el estado:
        // si la tarea de la noche no corrió, una vencida no cuenta como vigente.
        $vivas = fn () => Credencial::query()->vivas()->whereHas('servidor', fn ($q) => $q->where('estado', true));
        $vigentes = fn () => $vivas()->where('estado', EstadoCredencial::VIGENTE->value)
            ->where(fn ($q) => $q->whereNull('valida_hasta')->orWhere('valida_hasta', '>=', $hoy));

        $porVencer = fn () => $vigentes()->whereNotNull('valida_hasta')->where('valida_hasta', '<=', $limite);

        $proximas = $porVencer()
            ->with('servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,unidad_administrativa_id', 'servidor.unidadAdministrativa:id,nombre')
            ->orderBy('valida_hasta')->orderBy('id')
            ->limit(self::PROXIMAS_VISIBLES)
            ->get()
            ->map(fn (Credencial $c) => [
                'servidor_id'  => $c->servidor_id,
                'nombre'       => trim("{$c->servidor->apellido} {$c->servidor->segundo_apellido} {$c->servidor->nombre}"),
                'unidad'       => $c->servidor->unidadAdministrativa?->nombre,
                'valida_hasta' => $c->valida_hasta->toDateString(),
            ])->values()->all();

        return [
            'vigentes'       => $vigentes()->count(),
            'sin_imprimir'   => $vigentes()->whereDoesntHave('impresiones')->count(),
            'desactualizadas' => $this->desactualizadas(),
            'vencidas'       => $vivas()->count() - $vigentes()->count(),
            'por_vencer'     => $porVencer()->count(),
            'dias_por_vencer' => self::DIAS_POR_VENCER,
            'proximas'       => $proximas,
            'por_recibir'    => Credencial::query()->tarjetaPorRecibir()->count(),
        ];
    }

    /**
     * Las impresas que ya no dicen lo que dice el expediente. Comparar lo
     * impreso con lo de hoy no cabe en SQL: se arma con DatosCredencial, igual
     * que en el listado, para que las dos cifras coincidan.
     */
    private function desactualizadas(): int
    {
        return Servidor::query()
            ->where('estado', true)
            ->whereHas('credencialViva', fn ($q) => $q->whereHas('impresiones'))
            ->with([...DatosCredencial::RELACIONES_SERVIDOR, 'credencialViva.ultimaImpresion'])
            ->get()
            ->filter(fn (Servidor $s) => DatosCredencial::cambios(
                $s->credencialViva->ultimaImpresion,
                DatosCredencial::de($s, $s->credencialViva),
            ) !== [])
            ->count();
    }

    /**
     * Los logos: las variantes obligatorias sin versión propia, las que se ven
     * con su respaldo y los cambios ya programados.
     *
     * @return array<string, mixed>
     */
    private function identidad(): array
    {
        $estado = $this->identidad->estado();

        return [
            'faltan_obligatorias' => $estado->filter(fn ($e) => $e['variante']->obligatoria() && ! $e['vigente'])
                ->map(fn ($e) => $e['variante']->etiqueta())->values()->all(),
            'con_respaldo'        => $estado->filter(fn ($e) => ! $e['vigente'] && $e['en_uso'] !== null)
                ->map(fn ($e) => $e['variante']->etiqueta())->values()->all(),
            'programadas'         => $estado->flatMap(fn ($e) => $e['programadas']->map(fn ($p) => [
                'variante'      => $e['variante']->etiqueta(),
                'vigente_desde' => $p->vigente_desde->toDateString(),
            ]))->sortBy('vigente_desde')->values()->all(),
        ];
    }
}
