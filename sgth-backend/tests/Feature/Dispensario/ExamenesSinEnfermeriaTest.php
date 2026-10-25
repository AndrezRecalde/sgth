<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/*
 * Enfermería no entra a nada de los exámenes desde el 2026-10-24: no registra
 * resultados, no los valida ni hace el seguimiento que justificaba que viera
 * la bandeja, y lo que veía eran resultados y nombres de exámenes —VIH,
 * VDRL— de cada paciente. Decisión del usuario.
 *
 * Una ruta que vuelva a abrirse a Enfermería falla aquí, aunque la prueba de
 * su pantalla siga en verde.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

test('enfermeria_no_lee_nada_de_los_examenes', function (string $url) {
    $conRol = function (string $alias, string $rol): User {
        $user = User::create([
            'email' => "{$alias}@example.com", 'usuario_ti' => $alias,
            'password' => bcrypt('123456'), 'primer_login' => false,
        ]);
        $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));

        return $user;
    };

    $this->actingAs($conRol('examenes-sin-enfermeria', 'enfermera'), 'sanctum')
        ->getJson("/api/v1/dispensario/{$url}")
        ->assertForbidden();

    // El 403 es por el rol: un médico pasa la puerta (y recibe lo que toque:
    // la lista, o un 404 porque el id 1 no existe).
    $this->actingAs($conRol('examenes-con-medico', 'medico'), 'sanctum');
    expect($this->getJson("/api/v1/dispensario/{$url}")->status())->not->toBe(403);
})->with([
    'la bandeja'               => 'examenes/bandeja',
    'los resultados por revisar' => 'examenes/bandeja/por-revisar',
    'lo enviado por el Portal' => 'examenes/bandeja/por-validar',
    'el archivo de un envío'   => 'envios-resultado/1/archivo',
    'los resultados'           => 'resultados-medicos?historia_clinica_id=1',
    'el archivo de un resultado' => 'resultados-medicos/1/archivo',
    'las órdenes'              => 'ordenes-examen?historia_clinica_id=1',
    'una orden'                => 'ordenes-examen/1',
    'el impreso de una orden'  => 'ordenes-examen/1/pdf',
    'el catálogo'              => 'catalogo-examenes',
    'los perfiles'             => 'perfiles-examen',
]);
