<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminTiSeeder extends Seeder
{
    public function run(): void
    {
        // Solo la primera vez. Antes era un `updateOrCreate` que en cada
        // corrida volvía a poner la contraseña inicial: sembrar de nuevo una
        // base en uso le cambiaba la clave al administrador. Si ya existe, se
        // deja como está y solo se asegura que tenga sus roles.
        $admin = User::firstOrCreate(
            ['email' => 'crecalde@gadpe.gob.ec'],
            [
                'usuario_ti' => 'crecalde',
                'password' => Hash::make('0802704171a'), // Contraseña inicial
            ]
        );

        $admin->assignRole([
            Rol::ADMIN_TI->value,
            Rol::ADMIN_UATH->value,
            Rol::ASISTENTE_UATH->value,
        ]);
    }
}
