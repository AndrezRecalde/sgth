<?php

namespace App\Services\InventarioTi;

use App\Contracts\InventarioTi\MovimientoConsumibleServiceInterface;
use App\Enums\EstadoBien;
use App\Enums\TipoMovimientoConsumible;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Consumible;
use App\Models\InventarioTi\ConsumibleModelo;
use App\Models\InventarioTi\MovimientoConsumible;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Los movimientos de los consumibles (fase 5.4a): apertura, ingresos, entregas
 * y ajustes. Su lista, en orden, es el kárdex.
 *
 * - El saldo es la suma de los movimientos vivos de lo que guarda TIC [P2, P4].
 *   Nunca queda negativo, ni hoy ni en ningún punto del kárdex: una entrega con
 *   fecha atrasada o la anulación de un ingreso se rechazan si dejan el saldo
 *   en negativo algún día. Cada uno bloquea la fila del consumible, así que dos
 *   entregas a la vez se atienden una después de la otra.
 * - Lo que despacha Bodega solo registra entregas, sin saldo: ni apertura, ni
 *   ingresos, ni ajustes.
 * - Los de impresión se entregan a un equipo de un modelo compatible; el
 *   repuesto, a un equipo o a nadie en particular. La entrega guarda la unidad
 *   de ese momento: la del custodio del equipo o, si no tiene, la de quien retira.
 * - Nada se borra: se anula con su motivo y sigue a la vista [P10].
 */
final class MovimientoConsumibleService implements MovimientoConsumibleServiceInterface
{
    private const RELACIONES = [
        'bien:id,codigo_institucional,codigo_anterior,tipo_bien_id,marca_id,modelo', 'bien.tipo:id,nombre', 'bien.marca:id,nombre',
        'retira:id,nombre,segundo_nombre,apellido,segundo_apellido', 'unidad:id,nombre',
        'ticket:id,codigo_ticket',
        'registradoPor:id,usuario_ti,email,servidor_id', 'registradoPor.servidor:id,nombre,apellido',
        'anuladoPor:id,usuario_ti,email,servidor_id', 'anuladoPor.servidor:id,nombre,apellido',
    ];

    public function kardex(Consumible $consumible, ?string $desde = null, ?string $hasta = null): array
    {
        $movimientos = $consumible->movimientos()->with(self::RELACIONES)
            ->when($desde, fn ($q) => $q->where('fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('fecha', '<=', $hasta))
            ->enOrdenDeKardex()
            ->get();

        $saldo = $consumible->guarda_tic
            ? ($desde ? $this->saldoAl($consumible, Carbon::parse($desde)->subDay()->toDateString()) : 0)
            : null;
        $inicial = $saldo;

        $filas = $movimientos->map(function (MovimientoConsumible $m) use (&$saldo) {
            $cuenta = $saldo !== null && $m->afecta_saldo && $m->anulado_en === null;
            if ($cuenta) {
                $saldo += $m->cantidad;
            }

            return [...$this->presentar($m), 'saldo' => $cuenta ? $saldo : null];
        });

        return ['saldo_inicial' => $inicial, 'filas' => $filas->all(), 'saldo_final' => $saldo];
    }

    public function registrarApertura(Consumible $consumible, array $datos, User $user): MovimientoConsumible
    {
        return DB::transaction(function () use ($consumible, $datos, $user) {
            $c = $this->bloquear([$consumible->id])->sole();
            $this->exigirActivo($c);
            $this->exigirQueLoGuardeTic($c, 'apertura');

            $apertura = $c->movimientos()->vivos()->where('tipo', TipoMovimientoConsumible::APERTURA)->first();
            if ($apertura) {
                throw new ReglaNegocioException("Ya tiene su apertura ({$apertura->fecha->format('d/m/Y')}, {$apertura->cantidad}). Para corregirla, anúlela primero.");
            }
            $primero = $c->movimientos()->vivos()->where('afecta_saldo', true)->min('fecha');
            if ($primero !== null && Carbon::parse($primero)->lt(Carbon::parse($datos['fecha']))) {
                throw new ReglaNegocioException('La apertura va antes que todo el kárdex: hay movimientos desde el ' . Carbon::parse($primero)->format('d/m/Y') . '.');
            }

            return $this->crear($c, TipoMovimientoConsumible::APERTURA, (int) $datos['cantidad'], [
                'fecha' => $datos['fecha'], 'observacion' => $datos['observacion'] ?? null,
            ], $user);
        });
    }

    public function registrarIngreso(array $datos, User $user): Collection
    {
        $items = $this->items($datos['items']);

        return DB::transaction(function () use ($items, $datos, $user) {
            $consumibles = $this->bloquear($items->keys()->all());

            return $items->map(function (int $cantidad, int $id) use ($consumibles, $datos, $user) {
                $c = $consumibles[$id];
                $this->exigirActivo($c);
                $this->exigirQueLoGuardeTic($c, 'ingresos');

                return $this->crear($c, TipoMovimientoConsumible::INGRESO, $cantidad, [
                    'fecha'            => $datos['fecha'],
                    'tipo_documento'   => $datos['tipo_documento'],
                    'numero_documento' => trim($datos['numero_documento']),
                    'fecha_compra'     => $datos['fecha_compra'] ?? null,
                    'observacion'      => $datos['observacion'] ?? null,
                ], $user);
            })->values();
        });
    }

    public function registrarEntrega(array $datos, User $user): Collection
    {
        $items = $this->items($datos['items']);

        return DB::transaction(function () use ($items, $datos, $user) {
            $bien = empty($datos['bien_informatico_id']) ? null
                : BienInformatico::with('marca:id,nombre', 'asignacionVigente.servidor:id,unidad_administrativa_id')->findOrFail($datos['bien_informatico_id']);
            if ($bien?->estado_operativo === EstadoBien::DADO_DE_BAJA) {
                throw new ReglaNegocioException("El equipo {$bien->codigo_institucional} está dado de baja: ya no se le entregan consumibles.");
            }

            $custodio = $bien?->asignacionVigente?->servidor;
            $retira = empty($datos['servidor_retira_id']) ? $custodio : Servidor::findOrFail($datos['servidor_retira_id']);
            if ($retira === null) {
                throw ValidationException::withMessages([
                    'servidor_retira_id' => $bien ? 'El equipo no tiene custodio: diga quién retira.' : 'Diga quién retira.',
                ]);
            }
            $unidad = $custodio?->unidad_administrativa_id ?? ($custodio ? null : $bien?->unidad_administrativa_id) ?? $retira->unidad_administrativa_id;

            $consumibles = $this->bloquear($items->keys()->all());

            return $items->map(function (int $cantidad, int $id) use ($consumibles, $bien, $retira, $unidad, $datos, $user) {
                $c = $consumibles[$id];
                $this->exigirActivo($c);
                if ($c->tipo->deImpresion()) {
                    $this->exigirCompatible($c, $bien);
                }
                if ($c->guarda_tic) {
                    $this->exigirQueAlcance($c, $datos['fecha'], $cantidad, false);
                }

                return $this->crear($c, TipoMovimientoConsumible::ENTREGA, -$cantidad, [
                    'fecha'                    => $datos['fecha'],
                    'bien_informatico_id'      => $bien?->id,
                    'servidor_retira_id'       => $retira->id,
                    'unidad_administrativa_id' => $unidad,
                    'ticket_id'                => $datos['ticket_id'] ?? null,
                    'observacion'              => $datos['observacion'] ?? null,
                ], $user);
            })->values();
        });
    }

    public function registrarAjuste(Consumible $consumible, array $datos, User $user): MovimientoConsumible
    {
        return DB::transaction(function () use ($consumible, $datos, $user) {
            $c = $this->bloquear([$consumible->id])->sole();
            $this->exigirActivo($c);
            $this->exigirQueLoGuardeTic($c, 'ajustes');
            $cantidad = (int) $datos['cantidad'];
            if ($cantidad < 0) {
                $this->exigirQueAlcance($c, $datos['fecha'], -$cantidad, false);
            }

            return $this->crear($c, TipoMovimientoConsumible::AJUSTE, $cantidad, [
                'fecha' => $datos['fecha'], 'motivo_ajuste' => $datos['motivo_ajuste'], 'observacion' => trim($datos['observacion']),
            ], $user);
        });
    }

    public function anular(MovimientoConsumible $movimiento, string $motivo, User $user): MovimientoConsumible
    {
        return DB::transaction(function () use ($movimiento, $motivo, $user) {
            $c = $this->bloquear([$movimiento->consumible_id])->sole();
            $m = MovimientoConsumible::lockForUpdate()->findOrFail($movimiento->id);
            if ($m->anulado_en !== null) {
                throw new ReglaNegocioException('Ese movimiento ya está anulado.');
            }
            // Quitar lo que entró deja menos saldo desde su fecha: no puede dejarlo en negativo.
            if ($m->afecta_saldo && $m->cantidad > 0) {
                $this->exigirQueAlcance($c, $m->fecha->toDateString(), $m->cantidad, true);
            }

            $m->update(['anulado_en' => now(), 'anulado_por' => $user->id, 'motivo_anulacion' => trim($motivo)]);

            return $m->load(self::RELACIONES);
        });
    }

    public function saldoAl(Consumible $consumible, string $fecha): int
    {
        return (int) $consumible->movimientos()->vivos()->where('afecta_saldo', true)->where('fecha', '<=', $fecha)->sum('cantidad');
    }

    public function presentar(MovimientoConsumible $m): array
    {
        $b = $m->bien;

        return [
            'id'               => $m->id,
            'consumible_id'    => $m->consumible_id,
            'tipo'             => $m->tipo->value,
            'cantidad'         => $m->cantidad,
            'afecta_saldo'     => $m->afecta_saldo,
            'fecha'            => $m->fecha->toDateString(),
            'equipo'           => $b ? [
                'id'                   => $b->id,
                'codigo_institucional' => $b->codigo_institucional,
                'descripcion'          => trim(implode(' ', array_filter([$b->tipo?->nombre, $b->marca?->nombre, $b->modelo]))),
            ] : null,
            'retira_id'        => $m->servidor_retira_id,
            'retira'           => $m->retira?->nombre_completo,
            'unidad'           => $m->unidad?->nombre,
            'ticket_id'        => $m->ticket_id,
            'ticket'           => $m->ticket?->codigo_ticket,
            'tipo_documento'   => $m->tipo_documento?->value,
            'numero_documento' => $m->numero_documento,
            'fecha_compra'     => $m->fecha_compra?->toDateString(),
            'motivo_ajuste'    => $m->motivo_ajuste?->value,
            'observacion'      => $m->observacion,
            'registrado_por'   => $m->registradoPor?->nombre_completo,
            'registrado_en'    => $m->created_at?->toIso8601String(),
            'anulado_en'       => $m->anulado_en?->toIso8601String(),
            'anulado_por'      => $m->anuladoPor?->nombre_completo,
            'motivo_anulacion' => $m->motivo_anulacion,
        ];
    }

    /**
     * Los renglones del formulario, uno por consumible.
     *
     * @param  list<array{consumible_id: int, cantidad: int}>  $items
     * @return Collection<int, int>  cantidad por id de consumible
     */
    private function items(array $items): Collection
    {
        $porId = collect($items)->groupBy(fn (array $i) => (int) $i['consumible_id']);
        if ($porId->count() !== count($items)) {
            throw new ReglaNegocioException('Cada consumible va una sola vez: sume las cantidades en un renglón.');
        }

        return $porId->map(fn (Collection $g) => (int) $g->first()['cantidad']);
    }

    /**
     * Bloquea las filas de los consumibles hasta el final de la transacción, en
     * orden de id para que dos transacciones no se crucen.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Consumible>  por id
     */
    private function bloquear(array $ids): Collection
    {
        return Consumible::whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    /** @param  array<string, mixed>  $datos */
    private function crear(Consumible $c, TipoMovimientoConsumible $tipo, int $cantidad, array $datos, User $user): MovimientoConsumible
    {
        return MovimientoConsumible::create([
            ...$datos,
            'consumible_id'  => $c->id,
            'tipo'           => $tipo,
            'cantidad'       => $cantidad,
            'afecta_saldo'   => $c->guarda_tic,
            'registrado_por' => $user->id,
        ])->load(self::RELACIONES);
    }

    private function exigirActivo(Consumible $c): void
    {
        if (! $c->activo) {
            throw new ReglaNegocioException("«{$c->descripcion}» está desactivado: reactívelo para registrarle movimientos.");
        }
    }

    private function exigirQueLoGuardeTic(Consumible $c, string $que): void
    {
        if (! $c->guarda_tic) {
            throw new ReglaNegocioException("«{$c->descripcion}» lo despacha Bodega: no lleva saldo en TIC, así que no tiene {$que}.");
        }
    }

    /** Lo de impresión va a un equipo, y de un modelo al que sirve [P1]. */
    private function exigirCompatible(Consumible $c, ?BienInformatico $bien): void
    {
        if ($bien === null) {
            throw new ReglaNegocioException("«{$c->descripcion}» es de impresión: se entrega a un equipo.");
        }
        $compatible = $c->modelos()
            ->where('marca_id', $bien->marca_id)
            ->where('modelo_normalizado', ConsumibleModelo::normalizar($bien->modelo))
            ->exists();
        if (! $compatible) {
            $modelo = trim(($bien->marca?->nombre ?? '') . ' ' . ($bien->modelo ?? ''));
            throw new ReglaNegocioException("«{$c->descripcion}» no está marcado como compatible con el equipo {$bien->codigo_institucional} ({$modelo}). Agregue el modelo a sus compatibles.");
        }
    }

    /**
     * Que restar `$cantidad` desde `$fecha` no deje el saldo en negativo ningún
     * día. En el kárdex lo que entra va antes que lo que sale en el mismo día,
     * así que basta mirar el saldo al cierre de esa fecha y de cada día posterior
     * con movimientos.
     */
    private function exigirQueAlcance(Consumible $c, string $fecha, int $cantidad, bool $anulando): void
    {
        $desde = Carbon::parse($fecha)->toDateString();
        $porDia = MovimientoConsumible::where('consumible_id', $c->id)->where('afecta_saldo', true)->whereNull('anulado_en')
            ->toBase()->selectRaw('fecha, SUM(cantidad) AS neto')->groupBy('fecha')->orderBy('fecha')->get();

        $acumulado = 0;
        $alCierre = [$desde => 0];
        foreach ($porDia as $dia) {
            $acumulado += (int) $dia->neto;
            $d = Carbon::parse($dia->fecha)->toDateString();
            if ($d <= $desde) {
                $alCierre[$desde] = $acumulado;
            } else {
                $alCierre[$d] = $acumulado;
            }
        }

        $diaNegativo = collect($alCierre)->search(fn (int $saldo) => $saldo < $cantidad);
        if ($diaNegativo === false) {
            return;
        }

        $hoy = $acumulado;
        $cuando = Carbon::parse($diaNegativo)->format('d/m/Y');
        throw new ReglaNegocioException(match (true) {
            $anulando && $hoy < $cantidad => "No se puede anular: el saldo de «{$c->descripcion}» quedaría en " . ($hoy - $cantidad) . '.',
            $anulando                     => "No se puede anular: el kárdex de «{$c->descripcion}» quedaría en negativo el {$cuando}.",
            $hoy < $cantidad              => "No hay saldo suficiente de «{$c->descripcion}»: quedan {$hoy}.",
            default                       => "Al " . Carbon::parse($desde)->format('d/m/Y') . " no alcanzaba el saldo de «{$c->descripcion}»: el kárdex quedaría en negativo el {$cuando}.",
        });
    }
}
