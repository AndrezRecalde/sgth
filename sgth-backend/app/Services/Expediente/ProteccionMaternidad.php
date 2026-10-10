<?php

namespace App\Services\Expediente;

use App\Models\Dispensario\FichaSaludOcupacional;

/**
 * ¿Consta que la servidora está embarazada o en período de lactancia?
 *
 * La Corte Constitucional (sentencia 309-16-SEP-CC) condiciona el Art. 146 del
 * Reglamento a la LOSEP: a una ocasional embarazada o en lactancia no se le
 * termina el contrato unilateralmente (diseño de Acciones de Personal, 4.3;
 * TH 11). El único dato que tiene el sistema es la ficha de salud ocupacional
 * del Dispensario, y de ella se lee solo eso, sí o no: ni el diagnóstico ni
 * nada más de la ficha, que es historia clínica, llega a Talento Humano.
 */
final class ProteccionMaternidad
{
    public static function consta(int $servidorId): bool
    {
        $ficha = FichaSaludOcupacional::where('servidor_id', $servidorId)
            ->where('estado', true)
            ->orderByDesc('fecha_evaluacion')
            ->orderByDesc('id')
            ->first(['id', 'grupo_embarazada', 'grupo_lactancia']);

        return (bool) ($ficha?->grupo_embarazada || $ficha?->grupo_lactancia);
    }

    public static function aviso(): string
    {
        return 'Consta en su ficha de salud ocupacional que está embarazada o en período de '
            .'lactancia: la Corte Constitucional (309-16-SEP-CC) la protege frente a la '
            .'terminación de su contrato.';
    }
}
