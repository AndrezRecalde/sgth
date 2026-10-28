<?php

/*
| El kardex de Farmacia y los certificados médicos solo pintan quién registró
| el movimiento y quién anuló el certificado. Cargaban los usuarios enteros, y
| el accesor `nombre_completo` arrastraba el servidor completo: el JSON salía
| con el correo y el celular personales, el contacto de emergencia, la fecha de
| nacimiento y el tipo de sangre de esas personas. El kardex lo ve también el
| auxiliar de farmacia.
|
| Del paciente, el certificado trae lo que lo identifica y nada más: la
| pantalla de la consulta no pinta nada de él desde ahí.
*/

use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\MovimientoInventarioMed;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    foreach (['medico', 'admin-dispensario', 'auxiliar-farmacia'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    }

    $this->servidorConDatos = fn (string $cedula, string $nombre, string $apellido): Servidor => Servidor::forceCreate([
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

    $this->usuarioConDatos = function (string $rol, string $cedula, string $nombre, string $apellido): User {
        $servidor = ($this->servidorConDatos)($cedula, $nombre, $apellido);

        $usuario = User::factory()->create(['servidor_id' => $servidor->id]);
        $usuario->assignRole($rol);

        return $usuario;
    };

    // Afirma que la persona trae su nombre y nada de su expediente.
    $this->soloElNombre = function (?array $persona, string $nombreCompleto, string $cedula): void {
        expect($persona)->not->toBeNull()
            ->and($persona['nombre_completo'])->toBe($nombreCompleto);

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

    // El paciente servidor: nombre y cédula, sin el resto del expediente.
    $this->soloLaIdentidad = function (array $certificado): void {
        $paciente = $certificado['consulta_medica']['historia_clinica']['servidor'];

        expect($paciente)->toMatchArray([
            'nombre' => 'Rafael', 'apellido' => 'Quinde', 'cedula' => '0803333333',
        ])->not->toHaveKeys([
            'correo_personal', 'telefono_celular', 'fecha_nacimiento', 'tipo_sangre',
            'contacto_emergencia_nombre', 'contacto_emergencia_parentesco',
            'contacto_emergencia_telefono',
        ]);
    };

    $this->consultaDe = function (User $medico, array $historia): ConsultaMedica {
        return ConsultaMedica::forceCreate([
            'especialidad'        => 'medicina_general',
            'historia_clinica_id' => HistoriaClinica::forceCreate($historia)->id,
            'medico_id'           => $medico->id,
            'fecha_consulta'      => now()->toDateString(),
            'hora_consulta'       => '10:00:00',
            'motivo_consulta'     => 'Dolor',
        ]);
    };
});

// ── Kardex ────────────────────────────────────────────────────────────────

test('el kardex devuelve solo el nombre de quien registró cada movimiento', function () {
    $registradora = ($this->usuarioConDatos)('admin-dispensario', '0801111111', 'Ana', 'Torres');
    $auxiliar = User::factory()->create();
    $auxiliar->assignRole('auxiliar-farmacia');

    $medicina = InventarioMedicina::forceCreate([
        'codigo' => 'MED-KDX-1', 'nombre' => 'Paracetamol', 'principio_activo' => 'paracetamol',
        'presentacion' => 'tableta', 'stock_actual' => 45, 'stock_minimo' => 0, 'estado' => true,
    ]);

    MovimientoInventarioMed::forceCreate([
        'inventario_medicina_id' => $medicina->id,
        'tipo_movimiento'        => 'baja',
        'cantidad'               => -5,
        'stock_resultante'       => 45,
        'motivo'                 => 'Rotura',
        'registrado_por'         => $registradora->id,
    ]);

    $fila = $this->actingAs($auxiliar, 'sanctum')
        ->getJson("/api/v1/dispensario/inventario/medicinas/{$medicina->id}/kardex")
        ->assertOk()
        ->json('datos.data.0');

    expect($fila['motivo'])->toBe('Rotura');
    ($this->soloElNombre)($fila['registrador'], 'Ana Torres', '0801111111');
});

// ── Certificados médicos ──────────────────────────────────────────────────

test('emitir, anular, listar y ver un certificado no exponen el expediente de emisor, anulador ni paciente', function () {
    $medica = ($this->usuarioConDatos)('medico', '0801111111', 'Ana', 'Torres');
    $administracion = ($this->usuarioConDatos)('admin-dispensario', '0802222222', 'Luisa', 'Mena');
    $paciente = ($this->servidorConDatos)('0803333333', 'Rafael', 'Quinde');

    $consulta = ($this->consultaDe)($medica, ['servidor_id' => $paciente->id]);

    $emitido = $this->actingAs($medica, 'sanctum')
        ->postJson('/api/v1/dispensario/certificados-medicos', [
            'consulta_medica_id' => $consulta->id,
            'dias_reposo'        => 1,
        ])->assertCreated()->json('datos');

    ($this->soloElNombre)($emitido['emisor'], 'Ana Torres', '0801111111');
    ($this->soloLaIdentidad)($emitido);

    $anulado = $this->actingAs($administracion, 'sanctum')
        ->patchJson("/api/v1/dispensario/certificados-medicos/{$emitido['id']}/anular", [
            'motivo_anulacion' => 'Error de digitación',
        ])->assertOk()->json('datos');

    ($this->soloElNombre)($anulado['emisor'], 'Ana Torres', '0801111111');
    ($this->soloElNombre)($anulado['anulador'], 'Luisa Mena', '0802222222');
    ($this->soloLaIdentidad)($anulado);

    // La tarjeta de la consulta pinta quién lo anuló: el listado lo trae.
    $fila = $this->getJson("/api/v1/dispensario/certificados-medicos?consulta_medica_id={$consulta->id}")
        ->assertOk()
        ->json('datos.data.0');

    expect($fila['id'])->toBe($emitido['id']);
    ($this->soloElNombre)($fila['emisor'], 'Ana Torres', '0801111111');
    ($this->soloElNombre)($fila['anulador'], 'Luisa Mena', '0802222222');
    ($this->soloLaIdentidad)($fila);

    $detalle = $this->getJson("/api/v1/dispensario/certificados-medicos/{$emitido['id']}")
        ->assertOk()
        ->json('datos');

    ($this->soloElNombre)($detalle['emisor'], 'Ana Torres', '0801111111');
    ($this->soloElNombre)($detalle['anulador'], 'Luisa Mena', '0802222222');
    ($this->soloLaIdentidad)($detalle);
});

test('el certificado de un familiar trae su identidad y no su ficha', function () {
    $medica = ($this->usuarioConDatos)('medico', '0801111111', 'Ana', 'Torres');
    $titular = ($this->servidorConDatos)('0803333333', 'Rafael', 'Quinde');

    $hija = CargaFamiliar::create([
        'servidor_id' => $titular->id, 'cedula' => '0804444444',
        'nombres' => 'Sara', 'apellidos' => 'Quinde', 'parentesco' => 'hijo',
        'fecha_nacimiento' => '2015-02-01', 'genero' => 'femenino',
        'persona_con_discapacidad' => true, 'observaciones' => 'Asma desde los 3 años',
    ]);

    $consulta = ($this->consultaDe)($medica, [
        'carga_familiar_id' => $hija->id, 'tipo_paciente' => 'familiar', 'cedula_paciente' => $hija->cedula,
    ]);

    $this->actingAs($medica, 'sanctum')
        ->postJson('/api/v1/dispensario/certificados-medicos', [
            'consulta_medica_id' => $consulta->id,
            'dias_reposo'        => 1,
        ])->assertCreated();

    $familiar = $this->getJson("/api/v1/dispensario/certificados-medicos?consulta_medica_id={$consulta->id}")
        ->assertOk()
        ->json('datos.data.0.consulta_medica.historia_clinica.carga_familiar');

    expect($familiar)->toMatchArray([
        'nombres' => 'Sara', 'apellidos' => 'Quinde', 'cedula' => '0804444444',
    ])->not->toHaveKeys([
        'fecha_nacimiento', 'persona_con_discapacidad', 'posee_enfermedad_catastrofica', 'observaciones',
    ]);
});
