<?php

namespace App\Services\Expediente;

use App\Enums\RolFirmaAccionPersonal;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;

/**
 * Resuelve quién firma una Acción de Personal a partir del organigrama y sella
 * esos datos en el movimiento al suscribirlo.
 *
 * Vía única: no hay designación manual de firmantes. El firmante es el jefe
 * vigente de la unidad marcada como Talento Humano y el de la unidad marcada
 * como máxima autoridad. Así no existe una segunda fuente de verdad que pueda
 * desincronizarse del organigrama.
 *
 * El sellado sigue siendo lo que da la auditoría: las autoridades rotan, y un
 * documento reimpreso años después debe seguir mostrando a quien firmó
 * entonces, no a quien ocupe hoy el cargo.
 */
class FirmanteAccionPersonalService
{
    public function __construct(
        private readonly \App\Services\Estructura\FirmanteOrganigramaService $organigrama,
    ) {}

    /**
     * Copia al movimiento los firmantes vigentes a la fecha de suscripción. Es
     * idempotente: si ya trae firmantes sellados no los pisa, para que una
     * corrección de estado no reescriba quién firmó.
     */
    public function sellarEn(MovimientoPersonal $movimiento, ?string $fecha = null): void
    {
        if ($movimiento->firmante_autoridad_nombre || $movimiento->firmante_th_nombre) {
            return;
        }

        $fecha = $fecha ?? now()->toDateString();

        $movimiento->fecha_suscripcion = $fecha;

        foreach (RolFirmaAccionPersonal::cases() as $rol) {
            $this->copiar($movimiento, $rol, $this->resolver($rol, $fecha));
        }
    }

    /**
     * La constancia de un acto de autoridad electa la firma solo Talento
     * Humano (fase 4.1b): ninguno de esos actos nace de la autoridad
     * nominadora. Se sella al suscribir, como los demás.
     */
    public function sellarTalentoHumanoEn(MovimientoPersonal $movimiento, ?string $fecha = null): void
    {
        if ($movimiento->firmante_th_nombre) {
            return;
        }

        $fecha = $fecha ?? now()->toDateString();

        $movimiento->fecha_suscripcion = $fecha;

        $rol = RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO;
        $this->copiar($movimiento, $rol, $this->resolver($rol, $fecha));
    }

    /**
     * Firmante vigente de un rol en una fecha: el jefe de la unidad anclada,
     * o quien lo subrogue/encargue si hay una subrogación vigente sobre ese
     * puesto — quien ejerce el cargo es quien firma.
     *
     * @return array{servidor: ?Servidor, cargo: string, subrogado: bool}
     */
    public function resolver(RolFirmaAccionPersonal $rol, string $fecha): array
    {
        $firma = $this->organigrama->jefeDeUnidadEn(
            $this->unidadDe($rol),
            $fecha,
            $rol->cargoPorDefecto(),
        );

        return [
            'servidor'  => $firma['servidor'],
            'cargo'     => $firma['cargo'] ?? $rol->cargoPorDefecto(),
            'subrogado' => $firma['subrogado'],
        ];
    }

    /**
     * Quién firmaría en esa fecha, sin sellarlo: para la vista previa del
     * borrador (fase 6.2). El nombre y el cargo salen con el mismo formato con
     * que se sellan al suscribir; sin firmante —el puesto vacante—, el nombre
     * va vacío.
     *
     * @return array{nombre: ?string, cargo: string}
     */
    public function provisional(RolFirmaAccionPersonal $rol, ?string $fecha = null): array
    {
        $firma = $this->resolver($rol, $fecha ?? now()->toDateString());

        return [
            'nombre' => $firma['servidor'] ? $this->nombreCompleto($firma['servidor']) : null,
            'cargo'  => $firma['subrogado'] ? $firma['cargo'].' (S)' : $firma['cargo'],
        ];
    }

    /** Unidad anclada para el rol, o null si nadie la marcó todavía. */
    public function unidadDe(RolFirmaAccionPersonal $rol): ?UnidadAdministrativa
    {
        $columna = match ($rol) {
            RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA       => 'es_maxima_autoridad',
            RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO => 'es_unidad_talento_humano',
        };

        return UnidadAdministrativa::where($columna, true)->first();
    }

    /** @param  array{servidor: ?Servidor, cargo: string, subrogado: bool}  $firma */
    private function copiar(
        MovimientoPersonal $movimiento,
        RolFirmaAccionPersonal $rol,
        array $firma
    ): void {
        $prefijo = match ($rol) {
            RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA       => 'firmante_autoridad',
            RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO => 'firmante_th',
        };

        $servidor = $firma['servidor'];

        $movimiento->{"{$prefijo}_id"} = $servidor?->id;
        $movimiento->{"{$prefijo}_nombre"} = $servidor ? $this->nombreCompleto($servidor) : null;
        // Se deja constancia del encargo: el cargo impreso es el del puesto,
        // pero quien firmó lo hacía por subrogación.
        $movimiento->{"{$prefijo}_cargo"} = $firma['subrogado']
            ? $firma['cargo'].' (S)'
            : $firma['cargo'];
        $movimiento->{"{$prefijo}_cedula"} = $servidor?->cedula;
    }

    private function nombreCompleto(Servidor $servidor): string
    {
        return $this->organigrama->nombreCompleto($servidor);
    }
}
