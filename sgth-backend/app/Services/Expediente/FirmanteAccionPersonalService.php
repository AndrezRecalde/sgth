<?php

namespace App\Services\Expediente;

use App\Enums\EstadoSubrogacion;
use App\Enums\RolFirmaAccionPersonal;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;

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
     * Firmante vigente de un rol en una fecha: el jefe de la unidad anclada,
     * o quien lo subrogue/encargue si hay una subrogación vigente sobre ese
     * puesto — quien ejerce el cargo es quien firma.
     *
     * @return array{servidor: ?Servidor, cargo: string, subrogado: bool}
     */
    public function resolver(RolFirmaAccionPersonal $rol, string $fecha): array
    {
        $unidad = $this->unidadDe($rol);
        $puesto = $unidad?->puestos()
            ->where('es_jefe', true)
            ->with('cargo')
            ->first();

        if (!$puesto) {
            return ['servidor' => null, 'cargo' => $rol->cargoPorDefecto(), 'subrogado' => false];
        }

        $cargo = $puesto->cargo?->nombre ?? $rol->cargoPorDefecto();

        $subrogante = $this->subroganteDe($puesto->id, $fecha);

        if ($subrogante) {
            return ['servidor' => $subrogante, 'cargo' => $cargo, 'subrogado' => true];
        }

        return [
            'servidor'  => $this->titularDe($puesto->id),
            'cargo'     => $cargo,
            'subrogado' => false,
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

    /**
     * Titular del puesto: el servidor con contrato vigente sobre él. Si el
     * puesto está vacante devuelve null y el documento sale con el cargo pero
     * sin nombre — es preferible a atribuirle la firma a alguien que no la dio.
     */
    private function titularDe(int $puestoId): ?Servidor
    {
        return Servidor::whereHas(
            'contratos',
            fn ($q) => $q->where('puesto_id', $puestoId)->where('estado', 'vigente')
        )->first();
    }

    private function subroganteDe(int $puestoId, string $fecha): ?Servidor
    {
        $subrogacion = Subrogacion::with('subrogante')
            ->where('puesto_subrogado_id', $puestoId)
            ->where('estado', EstadoSubrogacion::ACTIVA->value)
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $fecha);
            })
            ->orderByDesc('fecha_inicio')
            ->first();

        return $subrogacion?->subrogante;
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
        return trim(implode(' ', array_filter([
            $servidor->apellido,
            $servidor->segundo_apellido,
            $servidor->nombre,
            $servidor->segundo_nombre,
        ])));
    }
}
