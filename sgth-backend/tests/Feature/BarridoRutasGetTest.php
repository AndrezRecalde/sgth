<?php

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * Barrido de todas las rutas GET del API: ninguna debe reventar.
 *
 * Este barrido nació encontrando catorce rutas que devolvían un 500 desde el día
 * que se escribieron. Ninguna se había notado, porque ninguna tenía todavía una
 * pantalla que la pidiera: rutas apuntando a métodos que no existen, comodines
 * que se tragaban a sus vecinas, consultas contra columnas de otro esquema.
 * Todas habrían aparecido el día que alguien construyera la pantalla, meses
 * después y con el trabajo ya empezado.
 *
 * Lo que se comprueba es modesto a propósito: que la ruta llegue a su
 * controlador y que el controlador no se rompa. Un 404 o un 422 sobre una base
 * vacía son respuestas legítimas —el registro no existe, faltan parámetros—; un
 * 500 no lo es nunca.
 *
 * Solo cubre GET. Un POST no se puede llamar a ciegas sin dejar efectos, así que
 * lo que escribe se prueba en el test de su módulo, no aquí.
 */
test('ninguna_ruta_get_del_api_revienta', function () {
    User::unguard();

    // Los permisos del sistema, sembrados. Sin ellos Spatie lanza
    // PermissionDoesNotExist —que sale como un 500— y la ruta ni siquiera llega
    // a ejecutarse, que es justo lo que este barrido quiere mirar.
    $this->seed(RolPermisoSeeder::class);

    $usuario = User::create([
        'email' => 'barrido@example.com', 'usuario_ti' => 'barrido',
        'password' => bcrypt('123456'), 'primer_login' => false,
        'activo' => true,
    ]);

    // Con todos los roles: lo que se busca son fallos de código, no de permisos.
    // Sale del enum para que un rol nuevo entre solo en el barrido.
    $usuario->assignRole(array_column(Rol::cases(), 'value'));

    $rotas = [];

    foreach (Route::getRoutes() as $ruta) {
        if (! in_array('GET', $ruta->methods(), true)) {
            continue;
        }
        if (! str_starts_with($ruta->uri(), 'api/v1/')) {
            continue;
        }

        // Los parámetros se rellenan con 1: si el registro no existe sale un
        // 404, que es lo que se espera de una base vacía.
        $uri = preg_replace('/\{[^}]*\}/', '1', $ruta->uri());

        // Cada ruta en su propio punto de guardado. Un error de SQL aborta la
        // transacción en Postgres, y sin aislarlas todo lo que viniera después
        // fallaba por arrastre: el informe se llenaba de ruido y escondía la
        // ruta que de verdad estaba rota.
        DB::beginTransaction();

        try {
            $respuesta = $this->actingAs($usuario, 'sanctum')->getJson("/{$uri}");
            $codigo    = $respuesta->getStatusCode();
        } catch (\Throwable $e) {
            $rotas[] = describirFallo($uri, $e);
            DB::rollBack();
            continue;
        }

        if ($codigo >= 500) {
            $rotas[] = describirFallo(
                $uri, $respuesta->exception, $respuesta->json('mensaje')
            );
        }

        DB::rollBack();
    }

    expect($rotas)->toBe([], implode("\n", [
        'Estas rutas GET devuelven un 500:',
        '',
        ...$rotas,
        '',
        'Si la ruta ya no debe existir, quítala; si debe existir, arréglala.',
    ]));
});

/** Una línea por ruta rota: qué falló y por qué, sin la traza entera. */
function describirFallo(string $uri, ?Throwable $e, ?string $mensaje = null): string
{
    $causa = $e
        ? class_basename($e) . ': ' . substr($e->getMessage(), 0, 150)
        : ($mensaje ?? 'sin excepción registrada');

    return "  {$uri}  →  {$causa}";
}
