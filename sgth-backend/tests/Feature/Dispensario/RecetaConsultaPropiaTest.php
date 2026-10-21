<?php

use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\RecetaMedica;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/*
 * Bastaba con que la consulta existiera para emitir una receta en ella. Un
 * médico podía recetar en la consulta de un colega, y el impreso salía con la
 * firma del colega: el PDF toma el prescriptor de la consulta.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $rolMedico = Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']);
    $medico = fn (string $alias) => tap(User::create([
        'email' => "{$alias}@example.com", 'usuario_ti' => $alias,
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]))->assignRole($rolMedico);

    $this->autor  = $medico('receta-propia-autor');
    $this->colega = $medico('receta-propia-colega');

    $historia = HistoriaClinica::create([
        'numero_historia' => '0912345692', 'cedula_paciente' => '0912345692',
        'tipo_paciente' => 'candidato', 'estado' => true,
    ]);
    $this->consulta = ConsultaMedica::create([
        'especialidad' => 'medicina_general', 'historia_clinica_id' => $historia->id,
        'medico_id' => $this->autor->id, 'fecha_consulta' => now(),
        'hora_consulta' => '09:00', 'motivo_consulta' => 'Faringitis',
    ]);
});

function recetaDeUnExterno(ConsultaMedica $consulta): array
{
    return [
        'consulta_medica_id' => $consulta->id,
        'fecha_emision'      => now()->toDateString(),
        'items' => [[
            'medicamento_externo' => 'Amoxicilina 500 mg', 'cantidad_prescrita' => 21,
            'dosis' => '1 cápsula', 'frecuencia' => 'cada 8 horas', 'duracion' => '7 días',
        ]],
    ];
}

test('no_se_receta_en_la_consulta_de_un_colega', function () {
    $this->actingAs($this->colega, 'sanctum')
        ->postJson('/api/v1/dispensario/recetas', recetaDeUnExterno($this->consulta))
        ->assertForbidden();

    expect(RecetaMedica::count())->toBe(0);
});

test('quien_atendio_receta_y_queda_como_autor', function () {
    $receta = $this->actingAs($this->autor, 'sanctum')
        ->postJson('/api/v1/dispensario/recetas', recetaDeUnExterno($this->consulta))
        ->assertCreated()
        ->json('datos.receta');

    expect(RecetaMedica::find($receta['id'])->created_by)->toBe($this->autor->id);
});
