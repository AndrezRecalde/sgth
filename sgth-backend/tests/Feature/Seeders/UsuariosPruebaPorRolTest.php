<?php

/*
| Un usuario de prueba por rol (docs/plan-roles-y-accesos.md, PR 1).
|
| Para recorrer el sistema con lo que ve cada rol, y no con `crecalde`, cuyos
| siete roles taparon que el buscador de servidores le daba 403 a TIC.
*/

use App\Enums\Rol;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Database\Seeders\TipoUnidadSeeder;
use Database\Seeders\UnidadAdministrativaSeeder;
use Database\Seeders\UsuariosPruebaPorRolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolPermisoSeeder::class, TipoUnidadSeeder::class, UnidadAdministrativaSeeder::class]);

    config(['auth.usuarios_prueba.clave' => 'clave-de-las-cuentas-de-prueba']);

    // Se llama directo y no con `$this->seed()`: `db:seed` pide confirmación
    // en producción y no llegaría a la guarda del propio seeder.
    $this->sembrarUsuariosPrueba = fn () => app(UsuariosPruebaPorRolSeeder::class)->run();
});

it('crea un usuario por rol, con ese rol solo y su ficha de prueba', function () {
    ($this->sembrarUsuariosPrueba)();

    foreach (Rol::cases() as $rol) {
        $usuario = User::where('usuario_ti', 'prueba.'.$rol->value)->sole();

        expect($usuario->getRoleNames()->all())->toBe([$rol->value])
            ->and($usuario->email)->toBe('prueba.'.$rol->value.'@gadpe.test')
            ->and($usuario->primer_login)->toBeFalse()
            ->and($usuario->activo)->toBeTrue()
            ->and(Hash::check('clave-de-las-cuentas-de-prueba', $usuario->password))->toBeTrue();

        $ficha = $usuario->servidor;

        expect($ficha->cedula)->toStartWith('0999000')
            ->and($ficha->apellido)->toBe('PRUEBA '.mb_strtoupper(str_replace('-', ' ', $rol->value)))
            ->and($ficha->unidadAdministrativa->nombre)->toBe('Gestión de Talento Humano y Riesgos Laborales')
            ->and($ficha->puede_marcar)->toBeFalse();
    }

    expect(Servidor::where('cedula', 'like', '0999000%')->pluck('cedula')->unique())
        ->toHaveCount(count(Rol::cases()));
});

it('sembrar dos veces no duplica, y vuelve a dejar la clave del .env y un solo rol', function () {
    ($this->sembrarUsuariosPrueba)();

    $auditor = User::where('usuario_ti', 'prueba.auditor')->sole();
    $auditor->update(['password' => 'otra-clave-cualquiera']);
    $auditor->assignRole(Rol::ADMIN_UATH->value);
    $auditor->givePermissionTo('marcar-en-linea');
    $fichaAntes = $auditor->servidor_id;

    ($this->sembrarUsuariosPrueba)();

    $auditor->refresh();

    expect(User::where('usuario_ti', 'like', 'prueba.%')->count())->toBe(count(Rol::cases()))
        ->and(Servidor::where('cedula', 'like', '0999000%')->count())->toBe(count(Rol::cases()))
        ->and(Hash::check('clave-de-las-cuentas-de-prueba', $auditor->password))->toBeTrue()
        ->and($auditor->getRoleNames()->all())->toBe([Rol::AUDITOR->value])
        ->and($auditor->servidor_id)->toBe($fichaAntes)
        // El permiso dado a mano se queda: es la forma de probar, por ejemplo,
        // la marcación en línea.
        ->and($auditor->getDirectPermissions()->pluck('name')->all())->toBe(['marcar-en-linea']);
});

it('reutiliza la cuenta de prueba que ya existía y no choca con su cédula', function () {
    $ficha = Servidor::create([
        'cedula' => '0999000001',
        'nombre' => 'Usuario',
        'apellido' => 'PRUEBA RECEPCION',
        'estado' => true,
    ]);

    $recepcion = User::create([
        'usuario_ti' => 'prueba.recepcion',
        'email' => 'prueba.recepcion@gadpe.test',
        'password' => 'la-que-tenia',
        'servidor_id' => $ficha->id,
    ]);
    $recepcion->assignRole(Rol::RECEPCION->value);

    ($this->sembrarUsuariosPrueba)();

    expect(User::where('usuario_ti', 'prueba.recepcion')->sole()->servidor_id)->toBe($ficha->id)
        ->and(Servidor::where('cedula', '0999000001')->count())->toBe(1)
        ->and(Servidor::where('cedula', 'like', '0999000%')->count())->toBe(count(Rol::cases()));
});

it('salta el rol que todavía no existe en la base', function () {
    Role::where('name', Rol::AUDITOR->value)->delete();

    ($this->sembrarUsuariosPrueba)();

    expect(User::where('usuario_ti', 'prueba.auditor')->exists())->toBeFalse()
        ->and(User::where('usuario_ti', 'like', 'prueba.%')->count())->toBe(count(Rol::cases()) - 1);
});

it('sin la clave en el .env no crea nada', function () {
    config(['auth.usuarios_prueba.clave' => null]);

    expect(fn () => ($this->sembrarUsuariosPrueba)())
        ->toThrow(RuntimeException::class, 'SGTH_CLAVE_USUARIOS_PRUEBA');

    expect(User::where('usuario_ti', 'like', 'prueba.%')->exists())->toBeFalse();
});

it('fuera del entorno local no corre', function () {
    $this->app['env'] = 'production';

    expect(fn () => ($this->sembrarUsuariosPrueba)())
        ->toThrow(RuntimeException::class, 'solo se crean en el entorno local');

    expect(User::where('usuario_ti', 'like', 'prueba.%')->exists())->toBeFalse();
});
