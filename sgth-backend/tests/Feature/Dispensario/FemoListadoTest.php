<?php

use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| El listado de fichas FEMO: la pantalla no paginaba (a partir de la ficha 21
| no había cómo llegar) y no se podía buscar a nadie.
*/

function fichaListadoFemo(User $medico, string $cedula, string $apellido): FichaSaludOcupacional
{
    Servidor::unguard();
    $servidor = Servidor::create(['cedula' => $cedula, 'nombre' => 'Ana', 'apellido' => $apellido]);

    return FichaSaludOcupacional::create([
        'servidor_id' => $servidor->id, 'evaluador_id' => $medico->id,
        'fecha_evaluacion' => '2026-10-01', 'tipo_ficha' => 'periodica',
        'aptitud' => 'apto', 'estado' => true,
    ]);
}

beforeEach(function () {
    $this->medico = User::factory()->create();
    $this->medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));
});

test('se busca por cédula o por apellido', function () {
    fichaListadoFemo($this->medico, '0804258986', 'Quinde');
    fichaListadoFemo($this->medico, '0802704171', 'Recalde');

    $porCedula = $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/fichas-sso?buscar=0802704')
        ->assertOk()->json('datos.data');
    $porApellido = $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/fichas-sso?buscar=quin')
        ->assertOk()->json('datos.data');

    expect($porCedula)->toHaveCount(1)
        ->and($porCedula[0]['servidor']['apellido'])->toBe('Recalde')
        ->and($porApellido)->toHaveCount(1)
        ->and($porApellido[0]['servidor']['apellido'])->toBe('Quinde');
});

test('las páginas no repiten fichas del mismo día', function () {
    foreach (range(1, 5) as $i) {
        fichaListadoFemo($this->medico, "08000000{$i}0", "Apellido{$i}");
    }

    $ids = collect([1, 2, 3])->flatMap(fn ($pagina) => $this->actingAs($this->medico, 'sanctum')
        ->getJson("/api/v1/dispensario/fichas-sso?per_page=2&page={$pagina}")
        ->assertOk()->json('datos.data.*.id'));

    expect($ids)->toHaveCount(5)->and($ids->unique())->toHaveCount(5);
});
