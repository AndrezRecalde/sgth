<?php

namespace App\Services\Viatico;

use App\Models\Viatico\EmpresaTransporte;
use App\Models\Viatico\TramoViatico;
use App\Models\Viatico\Viatico;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
| El itinerario de un viático: sus tramos, en orden y coherentes entre sí.
|
| Antes las reglas vivían en el controlador, repetidas entre crear y editar, y
| dejaban pasar tramos que se cruzaban o salían antes que el viático. Borrar
| el primer tramo dejaba el itinerario sin ida y la numeración con saltos.
|
| Dos clases de reglas (decisión del usuario, 2026-09-18):
|
| - Al guardar un tramo se rechaza lo que no puede ser: llegar antes de
|   salir, salirse de las fechas del viático, cruzarse con otro tramo, dos
|   regresos o un tramo después del regreso. El error va al campo.
|
| - Lo que falta para que el itinerario esté completo —salir con el viático,
|   volver con él— no bloquea cada cambio: si se cambian las fechas del
|   viático, los tramos quedan desajustados y se avisa. Se exige al aprobar.
*/
class ItinerarioViaticoService
{
    public function agregar(Viatico $viatico, array $datos): TramoViatico
    {
        return DB::transaction(function () use ($viatico, $datos) {
            $tramo = new TramoViatico(['viatico_id' => $viatico->id]);
            // Sin tipo, un destino; `renumerar` marca como ida el primero.
            $tramo->fill($this->conTransporte([...$datos, 'tipo_tramo' => $datos['tipo_tramo'] ?? 'destino']));

            $this->asegurarCoherente($viatico, $tramo);
            $tramo->save();
            $this->renumerar($viatico);

            return $tramo->fresh();
        });
    }

    public function actualizar(TramoViatico $tramo, array $datos): TramoViatico
    {
        return DB::transaction(function () use ($tramo, $datos) {
            if (array_key_exists('catalogo_transporte_id', $datos) || array_key_exists('empresa_transporte_id', $datos)) {
                $datos = $this->conTransporte($datos, $tramo);
            }

            $tramo->fill($datos);
            $this->asegurarCoherente($tramo->viatico, $tramo);
            $tramo->save();
            $this->renumerar($tramo->viatico);

            return $tramo->fresh();
        });
    }

    public function eliminar(TramoViatico $tramo): void
    {
        DB::transaction(function () use ($tramo) {
            $viatico = $tramo->viatico;
            $tramo->delete();
            $this->renumerar($viatico);
        });
    }

    /**
     * Lo que le falta al itinerario para aprobar el viático. Vacío si está
     * completo. La ficha lo muestra como aviso.
     *
     * @return list<string>
     */
    public function problemas(Viatico $viatico): array
    {
        $tramos = $viatico->tramos()->orderBy('orden')->get();

        if ($tramos->isEmpty()) {
            return ['El viático no tiene itinerario: registre al menos un tramo.'];
        }

        $salida = Carbon::parse($viatico->datetime_salida);
        $regreso = Carbon::parse($viatico->datetime_llegada);
        $problemas = [];

        $primero = $tramos->first();
        if (! $primero->datetime_salida->eq($salida)) {
            $problemas[] = "El primer tramo sale el {$this->fecha($primero->datetime_salida)}; el viático, el {$this->fecha($salida)}.";
        }

        $ultimo = $tramos->last();
        if ($ultimo->tipo_tramo !== 'regreso') {
            $problemas[] = 'Falta el tramo de regreso.';
        } elseif (! $ultimo->datetime_llegada->eq($regreso)) {
            $problemas[] = "El regreso llega el {$this->fecha($ultimo->datetime_llegada)}; el viático regresa el {$this->fecha($regreso)}.";
        }

        // Tras cambiar las fechas del viático, un tramo puede quedar fuera.
        foreach ($tramos as $t) {
            if ($t->datetime_salida->lt($salida) || $t->datetime_llegada->gt($regreso)) {
                $problemas[] = "El tramo {$t->orden} queda fuera de las fechas del viático.";
            }
        }

        return $problemas;
    }

    /**
     * Lo que el tramo no puede ser, con el error en su campo.
     *
     * @throws ValidationException
     */
    private function asegurarCoherente(Viatico $viatico, TramoViatico $tramo): void
    {
        $sale = Carbon::parse($tramo->datetime_salida);
        $llega = Carbon::parse($tramo->datetime_llegada);
        $salidaViatico = Carbon::parse($viatico->datetime_salida);
        $regresoViatico = Carbon::parse($viatico->datetime_llegada);

        if (! $llega->gt($sale)) {
            $this->error('datetime_llegada', 'La llegada tiene que ser después de la salida.');
        }
        if ($sale->lt($salidaViatico)) {
            $this->error('datetime_salida', "El tramo no puede salir antes que el viático ({$this->fecha($salidaViatico)}).");
        }
        if ($llega->gt($regresoViatico)) {
            $this->error('datetime_llegada', "El tramo no puede llegar después del regreso del viático ({$this->fecha($regresoViatico)}).");
        }

        $otros = $viatico->tramos()
            ->when($tramo->exists, fn ($q) => $q->whereKeyNot($tramo->id))
            ->orderBy('datetime_salida')
            ->get();

        $cruce = $otros->first(fn (TramoViatico $o) => $sale->lt($o->datetime_llegada) && $o->datetime_salida->lt($llega));
        if ($cruce) {
            $this->error('datetime_salida', "Se cruza con el tramo {$cruce->orden} ({$this->fecha($cruce->datetime_salida)} – {$this->fecha($cruce->datetime_llegada)}).");
        }

        $regreso = $otros->firstWhere('tipo_tramo', 'regreso');
        if ($tramo->tipo_tramo === 'regreso' && $regreso) {
            $this->error('tipo_tramo', "Ya hay un tramo de regreso (el {$regreso->orden}).");
        }
        if ($regreso && $sale->gte($regreso->datetime_llegada)) {
            $this->error('datetime_salida', 'Después del regreso no puede haber otro tramo.');
        }
        if ($tramo->tipo_tramo === 'regreso' && $otros->contains(fn (TramoViatico $o) => $o->datetime_salida->gte($llega))) {
            $this->error('tipo_tramo', 'El regreso tiene que ser el último tramo.');
        }
    }

    /**
     * Numera los tramos por su salida (1, 2, 3…) y marca el primero como la
     * ida. Antes el orden era «el mayor más uno»: al borrar quedaban saltos, y
     * al borrar la ida ningún tramo pasaba a serlo.
     */
    private function renumerar(Viatico $viatico): void
    {
        $viatico->tramos()->reorder()->orderBy('datetime_salida')->orderBy('id')->get()
            ->values()
            ->each(function (TramoViatico $t, int $i) {
                $tipo = $i === 0
                    ? 'ida'
                    : ($t->tipo_tramo === 'ida' ? 'destino' : $t->tipo_tramo);

                if ($t->orden !== $i + 1 || $t->tipo_tramo !== $tipo) {
                    $t->forceFill(['orden' => $i + 1, 'tipo_tramo' => $tipo])->saveQuietly();
                }
            });
    }

    /**
     * El tipo de transporte y la empresa del tramo, coherentes entre sí.
     *
     * - El tipo es obligatorio. Si solo llega la empresa, se toma el suyo.
     * - La empresa se exige solo si el tipo tiene empresas activas (bus,
     *   avión). Un vehículo institucional, un taxi o una lancha no las tienen.
     * - Una empresa tiene que ser de ese tipo.
     */
    private function conTransporte(array $datos, ?TramoViatico $tramo = null): array
    {
        $empresaId = array_key_exists('empresa_transporte_id', $datos)
            ? $datos['empresa_transporte_id']
            : $tramo?->empresa_transporte_id;

        $catalogoId = $datos['catalogo_transporte_id']
            ?? ($empresaId !== null && array_key_exists('empresa_transporte_id', $datos)
                ? EmpresaTransporte::whereKey($empresaId)->value('catalogo_transporte_id')
                : $tramo?->catalogo_transporte_id);

        if ($catalogoId === null) {
            $this->error('catalogo_transporte_id', 'Elija el tipo de transporte.');
        }

        if ($empresaId !== null
            && ! EmpresaTransporte::whereKey($empresaId)->where('catalogo_transporte_id', $catalogoId)->exists()) {
            // Al cambiar solo el tipo, la empresa anterior ya no aplica.
            if (array_key_exists('empresa_transporte_id', $datos)) {
                $this->error('empresa_transporte_id', 'La empresa no corresponde a ese tipo de transporte.');
            }
            $empresaId = null;
        }

        $conEmpresas = EmpresaTransporte::where('catalogo_transporte_id', $catalogoId)->where('activo', true)->exists();
        if ($empresaId === null && $conEmpresas) {
            $this->error('empresa_transporte_id', 'Elija la empresa de transporte.');
        }

        return [
            ...$datos,
            'catalogo_transporte_id' => (int) $catalogoId,
            'empresa_transporte_id'  => $empresaId,
        ];
    }

    private function fecha(CarbonInterface $f): string
    {
        return $f->format('d/m/Y H:i');
    }

    /** @throws ValidationException */
    private function error(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
