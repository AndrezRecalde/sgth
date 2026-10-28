<?php

/*
| El historial de adquisiciones de Farmacia solo pinta quién registró y quién
| anuló cada compra. Cargaba los dos usuarios enteros, y el accesor
| `nombre_completo` arrastraba el servidor completo: el JSON salía con el
| correo y el celular personales, el contacto de emergencia, la fecha de
| nacimiento y el tipo de sangre de esas personas.
*/

use App\Models\Dispensario\InventarioMedicina;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-dispensario', 'guard_name' => 'sanctum']);

    $this->adminConDatos = function (string $cedula, string $nombre, string $apellido): User {
        $servidor = Servidor::forceCreate([
            'cedula'                         => $cedula,
            'nombre'                         => $nombre,
            'apellido'                       => $apellido,
            'correo_personal'                => "{$cedula}@correo-personal.test",
            'telefono_celular'               => "09{$cedula}",
            'fecha_nacimiento'               => '1985-03-14',
            'tipo_sangre'                    => 'O+',
            'contacto_emergencia_nombre'     => "Familiar de {$nombre}",
            'contacto_emergencia_parentesco' => 'Cónyuge',
            'contacto_emergencia_telefono'   => "08{$cedula}",
        ]);

        $usuario = User::factory()->create(['servidor_id' => $servidor->id]);
        $usuario->assignRole('admin-dispensario');

        return $usuario;
    };

    // Afirma que la persona trae su nombre y nada de su expediente.
    $this->soloElNombre = function (array $persona, string $nombreCompleto, string $cedula): void {
        expect($persona['nombre_completo'])->toBe($nombreCompleto);

        $servidor = $persona['servidor'] ?? [];
        expect($servidor)->not->toHaveKeys([
            'correo_personal', 'telefono_celular', 'fecha_nacimiento', 'tipo_sangre',
            'contacto_emergencia_nombre', 'contacto_emergencia_parentesco',
            'contacto_emergencia_telefono', 'cedula',
        ]);

        $json = json_encode($persona);
        expect($json)->not->toContain("{$cedula}@correo-personal.test")
            ->and($json)->not->toContain("09{$cedula}")
            ->and($json)->not->toContain("08{$cedula}");
    };

    $this->registradora = ($this->adminConDatos)('0801111111', 'Ana', 'Torres');
    $this->anuladora = ($this->adminConDatos)('0802222222', 'Luisa', 'Mena');

    $this->medicina = InventarioMedicina::forceCreate([
        'codigo' => 'MED-ADQ-1', 'nombre' => 'Ibuprofeno', 'principio_activo' => 'ibuprofeno',
        'presentacion' => 'tableta', 'stock_actual' => 0, 'stock_minimo' => 0, 'estado' => true,
    ]);
});

test('registrar_una_adquisicion_devuelve_solo_el_nombre_de_quien_la_registra', function () {
    $registrada = $this->actingAs($this->registradora, 'sanctum')
        ->postJson('/api/v1/dispensario/adquisiciones', [
            'tipo'                => 'compra',
            'numero_documento'    => 'FACT-1001',
            'proveedor_o_donante' => 'Difare S.A.',
            'fecha_adquisicion'   => now()->toDateString(),
            'items'               => [['inventario_medicina_id' => $this->medicina->id, 'cantidad' => 20]],
        ])->assertCreated()->json('datos');

    ($this->soloElNombre)($registrada['registrador'], 'Ana Torres', '0801111111');
});

test('el_historial_y_el_detalle_no_exponen_el_expediente_de_registrador_ni_anulador', function () {
    $id = $this->actingAs($this->registradora, 'sanctum')
        ->postJson('/api/v1/dispensario/adquisiciones', [
            'tipo'                => 'donacion',
            'numero_documento'    => 'ACTA-77',
            'proveedor_o_donante' => 'Cruz Roja',
            'fecha_adquisicion'   => now()->toDateString(),
            'items'               => [['inventario_medicina_id' => $this->medicina->id, 'cantidad' => 10]],
        ])->assertCreated()->json('datos.id');

    $anulada = $this->actingAs($this->anuladora, 'sanctum')
        ->postJson("/api/v1/dispensario/adquisiciones/{$id}/anular", [
            'motivo_anulacion' => 'Error de digitación',
        ])->assertOk()->json('datos');

    ($this->soloElNombre)($anulada['registrador'], 'Ana Torres', '0801111111');
    ($this->soloElNombre)($anulada['anulador'], 'Luisa Mena', '0802222222');

    $fila = $this->getJson('/api/v1/dispensario/adquisiciones')
        ->assertOk()->json('datos.data.0');

    expect($fila['id'])->toBe($id);
    ($this->soloElNombre)($fila['registrador'], 'Ana Torres', '0801111111');
    ($this->soloElNombre)($fila['anulador'], 'Luisa Mena', '0802222222');

    $detalle = $this->getJson("/api/v1/dispensario/adquisiciones/{$id}")
        ->assertOk()->json('datos');

    ($this->soloElNombre)($detalle['registrador'], 'Ana Torres', '0801111111');
    ($this->soloElNombre)($detalle['anulador'], 'Luisa Mena', '0802222222');
});
