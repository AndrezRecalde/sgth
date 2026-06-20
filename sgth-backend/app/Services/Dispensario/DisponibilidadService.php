<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\DisponibilidadServiceInterface;
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

    public function listarDisponibles(array $roles): array
    {
        return User::role($roles)
            ->where('activo', true)
            ->whereHas('disponibilidadMedico', function ($q) {
                $q->where('disponible', true);
            })
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
