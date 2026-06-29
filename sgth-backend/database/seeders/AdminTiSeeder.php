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
        // Se crea usando los campos nativos de Laravel por ahora.
        // En el Sprint 1 se añadirán los campos usuario_ti y primer_login a la tabla users.
        $admin = User::updateOrCreate(
            ['email' => 'crecalde@gadpe.gob.ec'],
            [
                'usuario_ti' => 'crecalde',
                //'name'     => 'Administrador TI',
                'password' => Hash::make('0802704171a'), // Contraseña inicial actualizada
            ]
        );

        $admin->assignRole([
            Rol::ADMIN_TI->value,
            Rol::ADMIN_UATH->value,
            Rol::ASISTENTE_UATH->value,
        ]);
    }
}
