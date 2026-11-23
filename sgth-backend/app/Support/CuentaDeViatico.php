<?php

namespace App\Support;

use App\Models\Expediente\CuentaBancariaServidor;
use App\Models\Expediente\Servidor;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * La cuenta a la que Gestión Financiera le transfiere al servidor.
 *
 * Es la que el expediente tiene marcada como principal para viáticos. La
 * condición estaba escrita dentro del `with()` del servicio de PDF, y ahora la
 * necesita también la ficha del viático para avisar en pantalla de que falta.
 * Se escribe una vez: el aviso tiene que predecir lo que va a salir en el papel.
 *
 * No expone el número de cuenta: quien pregunta si falta recibe un sí o un no.
 */
final class CuentaDeViatico
{
    /** La condición, para un `with()` o un `whereHas()`. */
    public static function condicion(Relation|\Illuminate\Database\Eloquent\Builder $query): mixed
    {
        return $query->where('es_principal_viatico', true);
    }

    /** La cuenta principal de viáticos, o null si el servidor no tiene una. */
    public static function de(?Servidor $servidor): ?CuentaBancariaServidor
    {
        if (! $servidor) {
            return null;
        }

        // Si ya viene cargada —el PDF la trae con su entidad financiera— no se
        // vuelve a consultar.
        if ($servidor->relationLoaded('cuentasBancarias')) {
            return $servidor->cuentasBancarias->first();
        }

        return self::condicion($servidor->cuentasBancarias())->first();
    }

    /**
     * Si al servidor le falta la cuenta con la que se le paga.
     *
     * Hace falta con anticipo y sin él: un viático sin anticipo termina igual
     * con un saldo a favor del servidor que hay que transferirle.
     */
    public static function falta(?Servidor $servidor): bool
    {
        return self::de($servidor) === null;
    }
}
