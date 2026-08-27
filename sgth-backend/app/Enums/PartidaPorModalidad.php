<?php

namespace App\Enums;

use App\Models\Estructura\PartidaPresupuestaria;

/**
 * Qué partida presupuestaria paga cada modalidad de vinculación.
 *
 * Correspondencia confirmada por la Dirección Financiera del GAD Provincial de
 * Esmeraldas en agosto de 2026.
 *
 * En casi todos los casos hay una sola respuesta posible y el sistema la
 * propone. La excepción es el contrato profesional: el clasificador lo separa
 * en gasto corriente (530606) y de inversión (730606), y cuál aplica depende de
 * qué fondo financia ese contrato en particular — un dato que vive en el
 * convenio, no en el expediente. Ahí el sistema ofrece las dos y decide quien
 * sabe de dónde sale el dinero.
 *
 * Por eso esto **sugiere** y no impone: el campo queda editable en el
 * formulario. Lo que sí bloquea sin excepción es suscribir una acción con
 * efecto económico sobre una partida sin disponibilidad certificada (Art. 105
 * LOSEP), y ese control es independiente de esta sugerencia.
 */
final class PartidaPorModalidad
{
    /** @var array<string, list<string>> Códigos, en orden de preferencia. */
    private const CODIGOS = [
        TipoNombramiento::PERMANENTE->value              => ['510105'],
        TipoNombramiento::PROVISIONAL->value             => ['510105'],
        TipoNombramiento::LIBRE_NOMBRAMIENTO->value      => ['510105'],
        TipoNombramiento::ELECCION_POPULAR->value        => ['510105'],
        TipoNombramiento::SERVICIOS_OCASIONALES->value   => ['510510'],
        // El obrero se imputa a inversión. Se ofrece también la de gasto
        // corriente porque el clasificador la contempla con la misma
        // denominación y está pendiente de confirmar si el GAD la usa.
        TipoNombramiento::CODIGO_TRABAJO->value          => ['710106', '510106'],
        TipoNombramiento::SERVICIOS_PROFESIONALES->value => ['530606', '730606'],
    ];

    /** Subrogación y encargo no son modalidades de vínculo: son actos sobre uno. */
    public const SUBROGACION = '510512';
    public const ENCARGO     = '510513';

    /**
     * Las partidas que aplican a una modalidad, en orden de preferencia.
     * Vacío si la modalidad no tiene correspondencia definida.
     *
     * @return list<string>
     */
    public static function codigosPara(?TipoNombramiento $tipo): array
    {
        return $tipo ? (self::CODIGOS[$tipo->value] ?? []) : [];
    }

    /** ¿La modalidad admite más de una partida y exige que alguien elija? */
    public static function exigeEleccion(?TipoNombramiento $tipo): bool
    {
        return count(self::codigosPara($tipo)) > 1;
    }

    /**
     * La partida sugerida para una modalidad, ya resuelta contra el catálogo.
     * Null si la modalidad no tiene correspondencia o la partida no está
     * registrada — el formulario queda entonces sin preselección, que es
     * preferible a proponer una que no existe.
     */
    public static function sugerirPara(?TipoNombramiento $tipo): ?PartidaPresupuestaria
    {
        $codigos = self::codigosPara($tipo);

        if ($codigos === []) {
            return null;
        }

        return PartidaPresupuestaria::whereIn('codigo', $codigos)
            ->where('activo', true)
            ->orderByRaw(self::ordenDePreferencia($codigos))
            ->first();
    }

    /**
     * Mantiene el orden declarado arriba en vez del alfabético: para el obrero
     * la primera es la de inversión, que es la que el GAD usa.
     *
     * @param  list<string>  $codigos
     */
    private static function ordenDePreferencia(array $codigos): string
    {
        $casos = [];

        foreach ($codigos as $posicion => $codigo) {
            $casos[] = "WHEN codigo = '{$codigo}' THEN {$posicion}";
        }

        return 'CASE '.implode(' ', $casos).' ELSE 99 END';
    }
}
