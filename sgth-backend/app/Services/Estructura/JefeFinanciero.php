<?php

namespace App\Services\Estructura;

/**
 * A quién se le escribe en Gestión Financiera: el jefe de la unidad marcada
 * como financiera, o quien lo subrogue ese día, a la cuenta institucional de
 * su usuario. Lo usan los avisos de la multa y de la liquidación del obrero, y
 * la entrega de las novedades para nómina (fase 7.3a), que antes repetían
 * cada uno la misma búsqueda.
 */
class JefeFinanciero
{
    public function __construct(
        private readonly FirmanteOrganigramaService $organigrama,
    ) {
    }

    public function direccion(): ?string
    {
        $unidad = $this->organigrama->unidadAnclada('es_unidad_financiera');

        if (! $unidad) {
            return null;
        }

        $jefe = $this->organigrama->jefeDeUnidadEn($unidad, now()->toDateString())['servidor'];

        return $jefe?->loadMissing('usuario')->usuario?->email;
    }
}
