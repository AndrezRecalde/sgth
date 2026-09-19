<?php

namespace App\Services\Viatico;

use App\Enums\ZonaViatico;
use App\Models\Viatico\EmpresaTransporte;
use App\Models\Viatico\TramoViatico;
use App\Models\Viatico\Viatico;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
|   salir, salirse de las fechas del viático o cruzarse con otro tramo. El
|   error va al campo.
|
| - Lo que falta para que el itinerario esté completo —salir con el viático,
|   volver con él— no bloquea cada cambio: si se cambian las fechas del
|   viático, los tramos quedan desajustados y se avisa. Se exige al aprobar.
|
| El tipo de cada tramo lo deduce el sistema (2026-09-18): el primero es la
| ida y el último, si vuelve al lugar de donde salió la ida, el regreso. De
| los demás, quien viaja solo dice si realiza actividades ahí (destino) o
| solo pasa (escala). Antes elegía entre ida, destino, escala y regreso, una
| distinción que casi no servía y que se podía contradecir.
*/
class ItinerarioViaticoService
{
    public function agregar(Viatico $viatico, array $datos): TramoViatico
    {
        return DB::transaction(function () use ($viatico, $datos) {
            $tramo = new TramoViatico(['viatico_id' => $viatico->id]);
            $tramo->fill($this->conTransporte([...$datos, 'tipo_tramo' => $this->respuesta($datos)]));

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
            if (array_key_exists('tipo_tramo', $datos)) {
                $datos['tipo_tramo'] = $this->respuesta($datos);
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
            $problemas[] = "Falta el tramo de regreso: el último tramo tiene que volver a {$primero->origen_ciudad}.";
        } elseif (! $ultimo->datetime_llegada->eq($regreso)) {
            $problemas[] = "El regreso llega el {$this->fecha($ultimo->datetime_llegada)}; el viático regresa el {$this->fecha($regreso)}.";
        }

        // Tras cambiar las fechas del viático, un tramo puede quedar fuera.
        foreach ($tramos as $t) {
            if ($t->datetime_salida->lt($salida) || $t->datetime_llegada->gt($regreso)) {
                $problemas[] = "El tramo {$t->orden} queda fuera de las fechas del viático.";
            }
        }

        // La zona decide la tarifa de todas las noches. Si el viaje tiene un
        // destino en el exterior, todas se pagan como exterior, aunque haya
        // noches en el país (Gestión Financiera, 2026-09-18). Sin esto, un
        // viático «fuera de la provincia» con un tramo a Bogotá se pagaba a
        // tarifa nacional.
        $alExterior = $tramos->contains(fn (TramoViatico $t) => $t->destino_tipo === 'internacional');
        $esExterior = $viatico->zona === ZonaViatico::EXTERIOR;

        if ($alExterior && ! $esExterior) {
            $problemas[] = 'El itinerario incluye un destino en el exterior: el viático tiene que ser de zona Exterior.';
        } elseif ($esExterior && ! $alExterior) {
            $problemas[] = 'El viático es al exterior, pero ningún tramo llega al exterior.';
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
    }

    /**
     * Numera los tramos por su salida (1, 2, 3…) y deduce su tipo: el primero
     * es la ida; el último, si vuelve al lugar de donde salió la ida, el
     * regreso; los demás conservan lo que dijo quien viaja (destino o escala).
     *
     * Como se deduce sobre el itinerario entero, no importa en qué orden se
     * registren los tramos. Si el viaje pasa por el lugar de partida a mitad
     * de camino, ese tramo es un destino: el regreso es solo el último.
     */
    private function renumerar(Viatico $viatico): void
    {
        $tramos = $viatico->tramos()->reorder()->orderBy('datetime_salida')->orderBy('id')->get()->values();
        $base = $tramos->first();
        $ultimo = $tramos->count() - 1;

        $tramos->each(function (TramoViatico $t, int $i) use ($base, $ultimo) {
            $tipo = match (true) {
                $i === 0                                    => 'ida',
                $i === $ultimo && $this->vuelveA($t, $base) => 'regreso',
                in_array($t->tipo_tramo, ['destino', 'escala'], true) => $t->tipo_tramo,
                default                                     => 'destino',
            };

            if ($t->orden !== $i + 1 || $t->tipo_tramo !== $tipo) {
                $t->forceFill(['orden' => $i + 1, 'tipo_tramo' => $tipo])->saveQuietly();
            }
        });
    }

    /**
     * Si el tramo llega al lugar de donde salió la ida. Por el cantón cuando
     * los dos lo tienen; si no, por la ciudad escrita, sin mayúsculas ni
     * tildes («Esmeraldas» y «esmeraldas» son el mismo lugar).
     */
    private function vuelveA(TramoViatico $tramo, TramoViatico $ida): bool
    {
        if ($tramo->destino_canton_id && $ida->origen_canton_id) {
            return (int) $tramo->destino_canton_id === (int) $ida->origen_canton_id;
        }

        $lugar = fn (?string $ciudad) => Str::of((string) $ciudad)->ascii()->lower()->squish()->value();

        return $lugar($tramo->destino_ciudad) !== '' && $lugar($tramo->destino_ciudad) === $lugar($ida->origen_ciudad);
    }

    /**
     * Lo único que decide quien viaja sobre el tipo: si en ese lugar realiza
     * actividades (destino) o solo pasa (escala). La ida y el regreso los
     * deduce `renumerar`, aunque lleguen en la petición.
     */
    private function respuesta(array $datos): string
    {
        return ($datos['tipo_tramo'] ?? null) === 'escala' ? 'escala' : 'destino';
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
