<?php

namespace App\Services\InventarioTi;

use App\Enums\EstadoBien;
use App\Exceptions\ReglaNegocioException;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Constatacion;
use App\Models\InventarioTi\ConstatacionItem;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * La constatación física de los equipos (fase 5.2). TIC abre una para una
 * ubicación —y, si quiere, una unidad—, recorre el lugar escaneando las
 * etiquetas y la cierra. Lo esperado son los equipos principales en servicio
 * que el sistema tiene en esa ubicación; lo escaneado queda en su lugar, en
 * otra ubicación (y se puede mover aquí), recién registrado o dado de baja; lo
 * esperado que no apareció queda, al cerrar, como no encontrado. La hace solo
 * TIC (D11).
 */
final class ConstatacionService
{
    private const RELACIONES_BIEN = [
        'tipo:id,nombre', 'marca:id,nombre', 'ubicacion:id,sede,piso',
        'asignacionVigente.servidor:id,nombre,apellido',
    ];

    public function __construct(private readonly UbicacionesBienService $ubicaciones) {}

    /** @return Collection<int, Constatacion> */
    public function listar(): Collection
    {
        return Constatacion::with('ubicacion:id,sede,piso', 'unidad:id,nombre', 'iniciadaPor.servidor:id,nombre,apellido')
            ->withCount([
                'items as escaneados' => fn (Builder $q) => $q->where('resultado', '<>', 'no_encontrado'),
                'items as no_encontrados' => fn (Builder $q) => $q->where('resultado', 'no_encontrado'),
            ])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get();
    }

    public function crear(array $datos, User $user): Constatacion
    {
        return Constatacion::create([...$datos, 'estado' => Constatacion::EN_CURSO, 'iniciada_por' => $user->id]);
    }

    /**
     * Lo que se ve de una constatación: cada equipo con su resultado y el
     * resumen. En curso, lo esperado se calcula ahora y lo no escaneado figura
     * como pendiente; cerrada, todo sale de lo que quedó registrado.
     *
     * @return array{filas: list<array<string, mixed>>, resumen: array<string, int>}
     */
    public function detalle(Constatacion $c): array
    {
        $items = $c->items()->with(['bien' => fn ($q) => $q->with(self::RELACIONES_BIEN), 'ubicacionRegistrada:id,sede,piso', 'escaneadoPor.servidor:id,nombre,apellido'])
            ->get()->keyBy('bien_informatico_id');
        $esperados = $c->enCurso() ? $this->esperados($c)->with(self::RELACIONES_BIEN)->get() : collect();

        $filas = $esperados->map(fn (BienInformatico $b) => $this->fila($b, $items->get($b->id), true))
            ->concat($items->reject(fn (ConstatacionItem $i) => $esperados->contains('id', $i->bien_informatico_id))
                ->map(fn (ConstatacionItem $i) => $this->fila($i->bien, $i, $i->resultado === 'no_encontrado' || $i->resultado === 'en_su_lugar')))
            ->sortBy(fn (array $f) => $f['codigo_institucional'])
            ->values();

        $cuenta = fn (string $r) => $filas->where('resultado', $r)->count();

        return [
            'filas'   => $filas->all(),
            'resumen' => [
                'esperados'      => $filas->where('esperado', true)->count(),
                'pendientes'     => $cuenta('pendiente'),
                'en_su_lugar'    => $cuenta('en_su_lugar'),
                'otra_ubicacion' => $cuenta('otra_ubicacion'),
                'nuevos'         => $cuenta('nuevo'),
                'de_baja'        => $cuenta('de_baja'),
                'no_encontrados' => $cuenta('no_encontrado'),
            ],
        ];
    }

    /**
     * Lo que se leyó —la URL de la etiqueta QR o el código escrito a mano— y
     * el resultado. Si el equipo no está en el inventario, lo dice para darlo
     * de alta ahí mismo; si ya se escaneó, no lo cuenta dos veces.
     *
     * @return array{encontrado: bool, codigo: string, repetido?: bool, fila?: array<string, mixed>}
     */
    public function escanear(Constatacion $c, string $texto, User $user): array
    {
        $this->exigirEnCurso($c);
        $codigo = trim($texto);
        $bien = $this->resolver($codigo);
        if (! $bien) {
            return ['encontrado' => false, 'codigo' => $codigo];
        }

        $existente = $c->items()->where('bien_informatico_id', $bien->id)->first();
        if ($existente) {
            return ['encontrado' => true, 'codigo' => $bien->codigo_institucional, 'repetido' => true, 'fila' => $this->fila($bien->load(self::RELACIONES_BIEN), $existente, true)];
        }

        $item = $c->items()->create([
            'bien_informatico_id'     => $bien->id,
            'resultado'               => $this->resultado($c, $bien),
            'ubicacion_registrada_id' => $bien->ubicacion_id,
            'escaneado_por'           => $user->id,
            'escaneado_en'            => now(),
        ]);

        return ['encontrado' => true, 'codigo' => $bien->codigo_institucional, 'repetido' => false, 'fila' => $this->fila($bien->load(self::RELACIONES_BIEN), $item, true)];
    }

    /** Lo encontrado aquí que el sistema tenía en otra parte pasa a esta ubicación, con sus componentes. */
    public function mover(Constatacion $c, ConstatacionItem $item): void
    {
        $this->exigirEnCurso($c);
        if ($item->constatacion_id !== $c->id || $item->resultado !== 'otra_ubicacion' || $item->movido) {
            throw new ReglaNegocioException('Solo se mueve un equipo encontrado aquí que estaba registrado en otra ubicación.');
        }

        DB::transaction(function () use ($c, $item) {
            $this->ubicaciones->mover($item->bien, $c->ubicacion_id, 'constatacion', "Constatación «{$c->nombre}»: se encontró aquí.");
            $item->update(['movido' => true]);
        });
    }

    /** Lo esperado que no se escaneó queda como no encontrado, y la constatación ya no cambia. */
    public function cerrar(Constatacion $c, User $user, ?string $observaciones): Constatacion
    {
        $this->exigirEnCurso($c);

        return DB::transaction(function () use ($c, $user, $observaciones) {
            $escaneados = $c->items()->pluck('bien_informatico_id');
            $this->esperados($c)->whereNotIn('id', $escaneados)->get(['id', 'ubicacion_id'])
                ->each(fn (BienInformatico $b) => $c->items()->create([
                    'bien_informatico_id' => $b->id, 'resultado' => 'no_encontrado', 'ubicacion_registrada_id' => $b->ubicacion_id,
                ]));

            $c->update([
                'estado' => Constatacion::CERRADA, 'cerrada_por' => $user->id, 'cerrada_en' => now(),
                'observaciones' => $observaciones ?? $c->observaciones,
            ]);

            return $c->fresh();
        });
    }

    /** @return array{content: string, filename: string} */
    public function acta(Constatacion $c): array
    {
        $c->load('ubicacion', 'unidad', 'iniciadaPor.servidor', 'cerradaPor.servidor');
        $detalle = $this->detalle($c);

        $pdf = Pdf::loadView('pdf.inventario.acta-constatacion', [
            'constatacion' => $c,
            'filas'        => collect($detalle['filas'])->groupBy('resultado'),
            'resumen'      => $detalle['resumen'],
            'resultados'   => ConstatacionItem::RESULTADOS + ['pendiente' => 'Pendiente'],
            // El de cuando se cerró, o el de cuando empezó si sigue abierta (D3).
            ...app(\App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface::class)->paraDocumento($c->cerrada_en ?? $c->created_at),
        ])->setPaper('a4', 'portrait');

        return ['content' => $pdf->output(), 'filename' => "constatacion-{$c->id}.pdf"];
    }

    /** Lo que se espera encontrar: los equipos principales en servicio del lugar (y la unidad, si la acota). */
    private function esperados(Constatacion $c): Builder
    {
        return $this->ubicaciones->principalesEn($c->ubicacion_id, $c->unidad_administrativa_id);
    }

    private function resultado(Constatacion $c, BienInformatico $bien): string
    {
        $aqui = (int) $bien->ubicacion_id === (int) $c->ubicacion_id;

        return match (true) {
            $bien->estado_operativo === EstadoBien::DADO_DE_BAJA => 'de_baja',
            $aqui && $bien->created_at?->gt($c->created_at) => 'nuevo',
            $aqui => 'en_su_lugar',
            default => 'otra_ubicacion',
        };
    }

    /** La URL de la etiqueta trae el id; a mano se escribe el código institucional. */
    private function resolver(string $codigo): ?BienInformatico
    {
        if ($codigo === '') {
            return null;
        }
        if (preg_match('#/tic/inventario/equipos/(\d+)#', $codigo, $m)) {
            return BienInformatico::find((int) $m[1]);
        }

        return BienInformatico::where('codigo_institucional', $codigo)->orWhere('codigo_qr', $codigo)->first();
    }

    /** @return array<string, mixed> */
    private function fila(BienInformatico $b, ?ConstatacionItem $item, bool $esperado): array
    {
        return [
            'bien_id'              => $b->id,
            'item_id'              => $item?->id,
            'codigo_institucional' => $b->codigo_institucional,
            'equipo'               => trim(implode(' ', array_filter([$b->tipo?->nombre, $b->marca?->nombre, $b->modelo]))),
            'custodio'             => $b->asignacionVigente?->servidor?->nombre_completo,
            'ubicacion_actual'     => $b->ubicacion?->nombre,
            'esperado'             => $esperado,
            'resultado'            => $item?->resultado ?? 'pendiente',
            'ubicacion_registrada' => $item?->ubicacionRegistrada?->nombre,
            'movido'               => (bool) $item?->movido,
            'escaneado_en'         => $item?->escaneado_en?->toIso8601String(),
            'escaneado_por'        => $item?->escaneadoPor?->nombre_completo,
        ];
    }

    private function exigirEnCurso(Constatacion $c): void
    {
        if (! $c->enCurso()) {
            throw new ReglaNegocioException('La constatación ya está cerrada: no se puede modificar.');
        }
    }
}
