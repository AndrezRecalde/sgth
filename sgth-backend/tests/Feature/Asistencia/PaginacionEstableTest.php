<?php

/*
| Que dos páginas del mismo listado no se solapen.
|
| Los listados paginados ordenaban solo por `created_at`, y en este esquema esa
| columna es `timestamp(0)`: guarda segundos enteros. Las filas creadas de un
| tirón —una carga masiva, un seeder, una ráfaga de peticiones— empatan todas, y
| con el orden empatado Postgres resuelve cada consulta como le conviene, sin
| que dos consultas distintas tengan por qué coincidir. La página 2 repetía
| filas de la página 1, y las que desplazaba no salían en ninguna.
|
| Se cubren aquí los dos listados de Asistencia. El arreglo es el mismo
| `->orderBy('id', ...)` en los seis controladores del barrido.
|
| Cuál de estos tests es el canario: revertido el arreglo, los dos primeros
| —los del solapamiento— pueden pasar igualmente, porque que Postgres devuelva
| dos órdenes distintos depende del plan y con pocas filas suele coincidir. El
| que falla siempre es el tercero, que comprueba el orden DENTRO del bloque de
| empatados: sin el desempate salen del más viejo al más nuevo, al revés de lo
| que el listado promete. Los dos primeros describen la propiedad que de verdad
| importa; el tercero es el que la vigila.
*/

use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
use App\Enums\TipoPermiso;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Asistencia\Vacacion;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    PermisoServidor::unguard();
    Vacacion::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba(['nombre' => 'Dirección de Paginación']);

    $this->servidor = Servidor::create([
        'cedula'                   => '0809999991',
        'nombre'                   => 'Paula',
        'apellido'                 => 'Página',
        'puesto_id'                => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);

    $this->unidad = $unidad;

    $this->uath = User::create([
        'email'        => 'uath-paginacion@example.com',
        'usuario_ti'   => 'uathpag',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $this->uath->assignRole('admin-uath');

    $this->actingAs($this->uath, 'sanctum');
});

/**
 * Recorre el listado página a página y devuelve todos los ids en el orden en
 * que los fue entregando la API.
 */
function idsPaginados(string $url, int $porPagina, int $paginas): Illuminate\Support\Collection
{
    return collect(range(1, $paginas))->flatMap(
        fn (int $n) => collect(
            test()->getJson("{$url}?per_page={$porPagina}&page={$n}")
                ->assertOk()
                ->json('datos.data')
        )->pluck('id')
    );
}

test('el_listado_de_permisos_no_repite_filas_entre_páginas', function () {
    // Todos en el mismo segundo, que es lo que provoca el empate.
    foreach (range(1, 18) as $i) {
        PermisoServidor::create([
            'servidor_id'              => $this->servidor->id,
            'unidad_administrativa_id' => $this->unidad->id,
            'tipo'                     => TipoPermiso::OFICIAL->value,
            'fecha'                    => now()->addDay()->format('Y-m-d'),
            'hora_inicio'              => '08:00',
            'hora_fin'                 => '10:00',
            'observacion'              => "Comisión {$i}",
            'estado'                   => EstadoPermiso::PENDIENTE->value,
            'vence_en'                 => now()->addDays(4),
            'folio'                    => sprintf('PER-2026-%05d', $i),
        ]);
    }

    // Que haya empates no es una suposición del test: se comprueba. No se
    // exige que caigan todos en el mismo segundo —el bucle puede cruzar el
    // límite—, solo que haya menos segundos distintos que filas.
    expect(PermisoServidor::distinct()->count('created_at'))->toBeLessThan(18);

    $ids = idsPaginados('/api/v1/asistencia/permisos', 5, 4);

    expect($ids)->toHaveCount(18)
        ->and($ids->duplicates())->toBeEmpty()
        ->and($ids->unique())->toHaveCount(18);
});

test('el_listado_de_vacaciones_no_repite_filas_entre_páginas', function () {
    foreach (range(1, 18) as $i) {
        Vacacion::create([
            'servidor_id'      => $this->servidor->id,
            'fecha_inicio'     => now()->addDays(5)->format('Y-m-d'),
            'fecha_fin'        => now()->addDays(10)->format('Y-m-d'),
            'dias_solicitados' => 4,
            'tipo_dias'        => 'habiles',
            'estado'           => 'pendiente',
        ]);
    }

    expect(Vacacion::distinct()->count('created_at'))->toBeLessThan(18);

    $ids = idsPaginados('/api/v1/asistencia/vacaciones', 5, 4);

    expect($ids)->toHaveCount(18)
        ->and($ids->duplicates())->toBeEmpty()
        ->and($ids->unique())->toHaveCount(18);
});

test('el_orden_entre_empatados_se_mantiene_entre_dos_peticiones_idénticas', function () {
    foreach (range(1, 12) as $i) {
        Vacacion::create([
            'servidor_id'      => $this->servidor->id,
            'fecha_inicio'     => now()->addDays(5)->format('Y-m-d'),
            'fecha_fin'        => now()->addDays(10)->format('Y-m-d'),
            'dias_solicitados' => 4,
            'tipo_dias'        => 'habiles',
            'estado'           => 'pendiente',
        ]);
    }

    // Sin desempate, dos consultas iguales pueden devolver órdenes distintos:
    // es lo que hace que la paginación pierda filas.
    $primera = idsPaginados('/api/v1/asistencia/vacaciones', 12, 1);
    $segunda = idsPaginados('/api/v1/asistencia/vacaciones', 12, 1);

    expect($primera->all())->toBe($segunda->all())
        ->and($primera->first())->toBeGreaterThan($primera->last());
});
