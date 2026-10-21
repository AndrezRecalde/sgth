<?php

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/*
 * Una medicina retirada del catálogo (`estado` falso) no sale en el buscador,
 * pero el API la aceptaba al emitir: la receta mandaba a Farmacia algo que ya
 * no maneja, y nadie se lo decía al médico.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $this->medico = User::create([
        'email' => 'receta-inactiva@example.com', 'usuario_ti' => 'receta-inactiva',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Medicinas Inactivas']);
    $puesto = puestoDePrueba($unidad, 'Analista de Medicinas Inactivas');
    $paciente = Servidor::create([
        'cedula' => '0812380001', 'nombre' => 'Luisa', 'apellido' => 'Prueba',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);
    $historia = HistoriaClinica::create(['servidor_id' => $paciente->id, 'grupo_sanguineo' => 'O+']);

    $this->consulta = ConsultaMedica::create([
        'especialidad' => 'medicina_general', 'historia_clinica_id' => $historia->id,
        'medico_id' => $this->medico->id, 'fecha_consulta' => now(),
        'hora_consulta' => now()->format('H:i:s'), 'motivo_consulta' => 'Control',
    ]);

    $medicina = fn (string $codigo, bool $activa) => InventarioMedicina::create([
        'codigo' => $codigo, 'nombre' => "Medicina {$codigo}", 'principio_activo' => 'prueba',
        'presentacion' => 'tableta', 'stock_actual' => 0, 'stock_minimo' => 0, 'estado' => $activa,
    ]);
    $this->activa = $medicina('MED-INA-1', true);
    $this->retirada = $medicina('MED-INA-2', false);
});

function recetaDeUna(ConsultaMedica $consulta, int $medicinaId): array
{
    return [
        'consulta_medica_id' => $consulta->id,
        'fecha_emision' => now()->toDateString(),
        'items' => [[
            'inventario_medicina_id' => $medicinaId, 'cantidad_prescrita' => 10,
            'dosis' => '1 tableta', 'frecuencia' => 'cada 8 horas', 'duracion' => '3 dias',
        ]],
    ];
}

test('una_medicina_retirada_del_catalogo_no_se_receta', function () {
    $errores = $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/recetas', recetaDeUna($this->consulta, $this->retirada->id))
        ->assertStatus(422)
        ->json('errores');

    expect($errores['items.0.inventario_medicina_id'][0] ?? null)
        ->toBe('Esa medicina no está en el catálogo activo de la farmacia: récetela como medicamento externo.');
});

test('una_medicina_activa_se_receta', function () {
    $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/recetas', recetaDeUna($this->consulta, $this->activa->id))
        ->assertCreated();
});
