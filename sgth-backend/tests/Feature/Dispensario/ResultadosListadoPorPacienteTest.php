<?php

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\ResultadoMedico;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/*
 * El listado de resultados tenía el paciente como filtro opcional y no
 * paginaba: pedido sin él, devolvía de una vez los resultados de todos los
 * pacientes del Dispensario.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $this->enfermera = User::create([
        'email' => 'resultados-listado@example.com', 'usuario_ti' => 'resultados-listado',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->enfermera->assignRole(Role::firstOrCreate(['name' => 'enfermera', 'guard_name' => 'sanctum']));

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Listado de Resultados']);
    $puesto = puestoDePrueba($unidad, 'Analista de Listado de Resultados');
    $historia = function (string $cedula) use ($unidad, $puesto) {
        $servidor = Servidor::create([
            'cedula' => $cedula, 'nombre' => 'Paciente', 'apellido' => $cedula,
            'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral' => RegimenLaboral::LOSEP,
            'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
        ]);

        return HistoriaClinica::create(['servidor_id' => $servidor->id, 'grupo_sanguineo' => 'O+']);
    };

    $this->historia = $historia('0812400001');
    $this->otra = $historia('0812400002');

    $resultado = fn (HistoriaClinica $h, string $descripcion) => ResultadoMedico::create([
        'historia_clinica_id' => $h->id, 'subido_por' => $this->enfermera->id,
        'tipo' => 'laboratorio', 'descripcion' => $descripcion,
        'archivo' => "resultados-medicos/{$descripcion}.pdf", 'fecha_resultado' => '2026-10-20',
    ]);
    $this->primero = $resultado($this->historia, 'primero');
    $this->segundo = $resultado($this->historia, 'segundo');
    $resultado($this->otra, 'ajeno');
});

test('sin_paciente_no_lista_nada', function () {
    $errores = $this->actingAs($this->enfermera, 'sanctum')
        ->getJson('/api/v1/dispensario/resultados-medicos')
        ->assertStatus(422)
        ->json('errores');

    expect($errores['historia_clinica_id'][0] ?? null)
        ->toBe('Indique de qué paciente son los resultados.');
});

test('lista_solo_los_del_paciente_con_el_mas_reciente_primero', function () {
    $datos = $this->actingAs($this->enfermera, 'sanctum')
        ->getJson("/api/v1/dispensario/resultados-medicos?historia_clinica_id={$this->historia->id}")
        ->assertOk()
        ->json('datos');

    // Mismo día: desempata el id, el último subido arriba.
    expect(collect($datos)->pluck('descripcion')->all())->toBe(['segundo', 'primero']);
});
