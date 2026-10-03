<?php

namespace App\Services\Dispensario\Reportes;

use App\Models\User;

/**
 * Qué parte del Dispensario puede ver en Reportes quien los pide.
 *
 * - La administración del Dispensario ve todo.
 * - La máxima autoridad ve solo los agregados: conteos y tasas, nunca nombres
 *   con diagnóstico. Los datos de salud son sensibles (LOPDP) y el detalle
 *   paciente por paciente se queda dentro del Dispensario.
 * - Médicos, odontólogos y enfermeras ven sus propios reportes, filtrados a
 *   sus atenciones: el informe mensual de actividades de cada uno.
 *
 * Quien tiene varios roles se queda con el más amplio: una administradora que
 * además atiende ve todo el Dispensario. Decisión del usuario, 2026-10-03.
 */
final class AlcanceReporte
{
    public const ADMINISTRACION = 'administracion';
    public const AUTORIDAD      = 'autoridad';
    public const MEDICO         = 'medico';
    public const ODONTOLOGO     = 'odontologo';
    public const ENFERMERIA     = 'enfermeria';

    private function __construct(
        public readonly string $perfil,
        /** Solo las atenciones de este usuario; `null` es todo el Dispensario. */
        public readonly ?int $profesionalId,
    ) {}

    public static function paraUsuario(User $usuario): ?self
    {
        return match (true) {
            $usuario->hasRole('admin-dispensario') => new self(self::ADMINISTRACION, null),
            $usuario->hasRole('maxima-autoridad')  => new self(self::AUTORIDAD, null),
            $usuario->hasRole('medico')            => new self(self::MEDICO, $usuario->id),
            $usuario->hasRole('odontologo')        => new self(self::ODONTOLOGO, $usuario->id),
            $usuario->hasRole('enfermera')         => new self(self::ENFERMERIA, $usuario->id),
            default                                => null,
        };
    }

    public function soloLoPropio(): bool
    {
        return $this->profesionalId !== null;
    }

    public function soloAgregados(): bool
    {
        return $this->perfil === self::AUTORIDAD;
    }

    /** Cómo se lee en el encabezado del archivo. */
    public function descripcion(): string
    {
        return $this->soloLoPropio() ? 'Solo mis atenciones' : 'Todo el Dispensario';
    }
}
