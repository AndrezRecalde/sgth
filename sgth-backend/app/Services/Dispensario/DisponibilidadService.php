<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\DisponibilidadServiceInterface;
use App\Enums\EspecialidadAtencion;
use App\Models\Dispensario\DisponibilidadMedico;
use App\Models\User;

final class DisponibilidadService implements DisponibilidadServiceInterface
{
    public function obtenerEstado(int $userId): bool
    {
        return DisponibilidadMedico::where('user_id', $userId)
            ->value('disponible') ?? false;
    }

    public function alternar(int $userId): bool
    {
        $registro = DisponibilidadMedico::firstOrCreate(
            ['user_id' => $userId],
            ['disponible' => false]
        );

        $registro->update([
            'disponible'     => !$registro->disponible,
            'actualizado_en' => now(),
        ]);

        return $registro->disponible;
    }

    public function marcarNoDisponible(int $userId): void
    {
        DisponibilidadMedico::where('user_id', $userId)
            ->update([
                'disponible'     => false,
                'actualizado_en' => now(),
            ]);
    }

    /**
     * Los profesionales a los que se puede asignar un turno de esta atención.
     *
     * El selector de la pantalla de turnos se llamaba «Profesional disponible»
     * y avisaba de que «no hay profesionales marcados como disponibles», pero
     * pedía la lista completa del rol: nunca miraba la disponibilidad. El
     * interruptor que el médico pulsa no tenía ningún efecto, y este método
     * —que sí filtra— llevaba tiempo escrito sin que nadie lo llamara.
     *
     * Si no hay nadie marcado se devuelven todos, diciéndolo. Filtrar en firme
     * dejaría a Recepción sin poder abrir un turno a las ocho de la mañana
     * porque todavía nadie ha pulsado su interruptor, y eso es peor problema
     * que el que se viene a resolver.
     *
     * @return array{personal: array, hay_disponibles: bool}
     */
    public function listarParaAtencion(EspecialidadAtencion $especialidad): array
    {
        $roles = [$especialidad->rol()];

        $disponibles = $this->listarDisponibles($roles);

        if ($disponibles !== []) {
            return ['personal' => $disponibles, 'hay_disponibles' => true];
        }

        return [
            'personal'        => $this->listarDelRol($roles),
            'hay_disponibles' => false,
        ];
    }

    /** Todo el personal activo de esos roles, esté marcado o no. */
    private function listarDelRol(array $roles): array
    {
        return $this->mapear(
            User::role($roles)->where('activo', true)
        );
    }

    public function listarDisponibles(array $roles): array
    {
        return $this->mapear(
            User::role($roles)
                ->where('activo', true)
                ->whereHas('disponibilidadMedico', function ($q) {
                    $q->where('disponible', true);
                })
        );
    }

    /** @param \Illuminate\Database\Eloquent\Builder<User> $query */
    private function mapear($query): array
    {
        return $query
            ->with('servidor.puesto.cargo')
            ->get()
            ->map(function (User $user) {
                return [
                    'id'              => $user->id,
                    'nombre_completo' => $user->nombre_completo
                        ?? trim(
                            ($user->servidor?->nombre ?? '') . ' ' .
                            ($user->servidor?->apellido ?? '')
                        ),
                    'roles'  => $user->getRoleNames(),
                    'puesto' => $user->servidor?->puesto?->cargo?->nombre,
                ];
            })
            ->toArray();
    }
}
