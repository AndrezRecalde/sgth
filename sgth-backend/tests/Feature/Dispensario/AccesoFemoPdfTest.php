<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| El PDF de la ficha FEMO es el formulario 028 del MSP completo: motivo de
| consulta, antecedentes personales, examen físico regional y diagnóstico
| CIE-10. Es historia clínica, y no entra al expediente administrativo.
|
| Lo que Talento Humano recibe de una evaluación es la aptitud y sus
| restricciones —el acuerdo con la UATH del 2026-09-26—, y eso ya viaja en el
| listado de solicitudes. La pestaña de Salud Ocupacional del expediente tenía
| además un botón de descarga que nunca pudo funcionar: la ruta pide el rol del
| Dispensario y el 403 salía disfrazado de «No se pudo generar el PDF».
|
| Esta prueba fija la frontera para que el botón no vuelva por descuido.
*/

function usuarioDeRolFemoPdf(string $rol): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole(
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum'])
    );

    return $usuario;
}

test('Talento Humano no descarga el PDF de la ficha FEMO', function (string $rol) {
    $this->actingAs(usuarioDeRolFemoPdf($rol), 'sanctum')
        ->get('/api/v1/dispensario/fichas-sso/1/pdf')
        ->assertStatus(403);
})->with(['admin-uath', 'asistente-uath', 'analista-uath']);

test('quien evalúa no queda bloqueado por el rol en esa misma ruta', function () {
    // La ficha 1 no existe, así que lo que importa es que la respuesta no sea
    // el 403 del middleware: el rol pasa y el fallo es de datos.
    $respuesta = $this->actingAs(usuarioDeRolFemoPdf('medico'), 'sanctum')
        ->get('/api/v1/dispensario/fichas-sso/1/pdf');

    expect($respuesta->status())->not->toBe(403);
});
