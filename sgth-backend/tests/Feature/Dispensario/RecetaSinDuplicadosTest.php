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
 * Una receta podía llevar el mismo medicamento en dos líneas: el buscador lo
 * dejaba agregar otra vez y el servidor no lo comprobaba. Eran dos ítems que
 * el despacho entregaba por separado, cada uno con su dosis.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $this->medico = User::create([
        'email' => 'receta-duplicados@example.com', 'usuario_ti' => 'receta-duplicados',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Recetas Duplicadas']);
    $puesto = puestoDePrueba($unidad, 'Analista de Recetas Duplicadas');
    $paciente = Servidor::create([
        'cedula' => '0812360001', 'nombre' => 'Marta', 'apellido' => 'Prueba',
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

    $medicina = fn (string $codigo, string $nombre) => InventarioMedicina::create([
        'codigo' => $codigo, 'nombre' => $nombre, 'principio_activo' => strtolower($nombre),
        'presentacion' => 'tableta', 'stock_actual' => 0, 'stock_minimo' => 0, 'estado' => true,
    ]);
    $this->paracetamol = $medicina('MED-DUP-1', 'Paracetamol');
    $this->ibuprofeno = $medicina('MED-DUP-2', 'Ibuprofeno');
});

function recetaConItems(ConsultaMedica $consulta, array $items): array
{
    return [
        'consulta_medica_id' => $consulta->id,
        'fecha_emision' => now()->toDateString(),
        'items' => array_map(fn (array $item) => [
            'cantidad_prescrita' => 10, 'dosis' => '1 tableta',
            'frecuencia' => 'cada 8 horas', 'duracion' => '3 dias', ...$item,
        ], $items),
    ];
}

test('el_mismo_medicamento_del_catalogo_dos_veces_no_se_receta', function () {
    $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/recetas', recetaConItems($this->consulta, [
            ['inventario_medicina_id' => $this->paracetamol->id],
            ['inventario_medicina_id' => $this->paracetamol->id],
        ]))
        ->assertStatus(422)
        ->assertJsonPath('errores', fn ($errores) => array_key_exists('items.1.inventario_medicina_id', $errores));
});

test('el_mismo_medicamento_externo_dos_veces_no_se_receta_aunque_cambien_las_mayusculas', function () {
    $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/recetas', recetaConItems($this->consulta, [
            ['medicamento_externo' => 'Rosuvastatina 20 mg'],
            ['medicamento_externo' => 'rosuvastatina 20 MG'],
        ]))
        ->assertStatus(422)
        ->assertJsonPath('errores', fn ($errores) => array_key_exists('items.1.medicamento_externo', $errores));
});

test('medicamentos_distintos_y_varios_externos_se_recetan', function () {
    $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/recetas', recetaConItems($this->consulta, [
            ['inventario_medicina_id' => $this->paracetamol->id],
            ['inventario_medicina_id' => $this->ibuprofeno->id],
            ['medicamento_externo' => 'Rosuvastatina 20 mg'],
            ['medicamento_externo' => 'Insulina glargina'],
        ]))
        ->assertCreated();
});
