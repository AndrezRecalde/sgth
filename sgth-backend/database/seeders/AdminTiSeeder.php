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
        $admin = User::firstOrCreate(
            ['email' => 'admin.ti@gadpe.gob.ec'],
            [
                'name'     => 'Administrador TI',
                'password' => Hash::make('0801234567'), // Contraseña inicial: Cédula
            ]
        );

        $admin->assignRole(Rol::ADMIN_TI->value);
    }
}
