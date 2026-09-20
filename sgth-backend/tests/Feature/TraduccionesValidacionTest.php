<?php

namespace Tests\Feature;

use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
| La aplicación corre en español (APP_LOCALE=es) y hasta el 2026-09-20 no
| existía la carpeta lang/: toda regla sin mensaje propio en su FormRequest
| llegaba a la pantalla como la clave cruda, por ejemplo «validation.unique»
| al editar una ficha con una cédula repetida.
*/

uses(TestCase::class, RefreshDatabase::class);

test('el idioma de la aplicación es español y tiene sus traducciones', function () {
    expect(config('app.locale'))->toBe('es')
        ->and(Lang::has('validation.required'))->toBeTrue()
        ->and(Lang::get('validation.required'))->not->toStartWith('validation.');
});

test('un 422 sin mensaje propio llega en español, no como validation.*', function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $unidad = unidadDePrueba(['codigo' => 'UATH', 'nombre' => 'Talento Humano']);

    $servidor = Servidor::create([
        'cedula' => '1111111111', 'nombre' => 'Ana', 'apellido' => 'Prueba',
        'regimen_laboral' => 'losep', 'estado' => true,
        'puesto_id' => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
    ]);

    $uath = User::factory()->create();
    $uath->assignRole('admin-uath');
    $this->actingAs($uath, 'sanctum');

    // `nombre` solo tiene `max:100` en UpdateServidorRequest, sin mensaje propio.
    $respuesta = $this->putJson("/api/v1/expediente/servidores/{$servidor->id}", [
        'nombre' => str_repeat('a', 101),
    ])->assertUnprocessable();

    $mensaje = $respuesta->json('errores.nombre.0');
    expect($mensaje)->not->toStartWith('validation.')
        ->and($mensaje)->toContain('100');
});

test('los mensajes traducidos nombran el campo en español', function () {
    $validador = Validator::make([], ['correo_personal' => 'required']);

    expect($validador->errors()->first('correo_personal'))
        ->toBe('El campo correo personal es obligatorio.');
});

test('no quedan claves sin traducir en las reglas que usa el proyecto', function () {
    $reglas = [
        'required', 'email', 'unique', 'exists', 'boolean', 'integer', 'numeric',
        'date', 'after', 'before', 'in', 'regex', 'prohibited', 'file', 'mimes',
        'string', 'array', 'confirmed', 'enum',
    ];

    foreach ($reglas as $regla) {
        expect(Lang::get("validation.$regla"))
            ->not->toStartWith('validation.', "falta la traducción de $regla");
    }

    foreach (['max', 'min', 'between', 'size'] as $regla) {
        foreach (['numeric', 'file', 'string', 'array'] as $tipo) {
            expect(Lang::get("validation.$regla.$tipo"))
                ->not->toStartWith('validation.', "falta la traducción de $regla.$tipo");
        }
    }
});
