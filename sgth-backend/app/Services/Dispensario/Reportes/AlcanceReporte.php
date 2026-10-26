<?php

namespace App\Services\Dispensario\Reportes;

use App\Models\User;

/**
 * Qué parte del Dispensario puede ver en Reportes quien los pide.
 *
 * - La administración del Dispensario ve todo.
 * - Médicos, odontólogos y enfermeras ven sus propios reportes, filtrados a
 *   sus atenciones: el informe mensual de actividades de cada uno.
 *
 * Hubo un perfil más, el de la máxima autoridad, que veía solo los agregados.
 * Se retiró con su rol el 2026-10-26 (docs/plan-roles-y-accesos.md, PR 7).
 *
 * Quien tiene varios roles se queda con el más amplio: una administradora que
 * además atiende ve todo el Dispensario. Decisión del usuario, 2026-10-03.
 */
final class AlcanceReporte
{
    public const ADMINISTRACION = 'administracion';
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

    /** Cómo se lee en el encabezado del archivo. */
    public function descripcion(): string
    {
        return $this->soloLoPropio() ? 'Solo mis atenciones' : 'Todo el Dispensario';
    }
}
