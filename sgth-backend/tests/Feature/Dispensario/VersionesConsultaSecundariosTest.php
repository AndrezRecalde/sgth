<?php

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\DiagnosticoCie10;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/*
 * Las versiones anteriores de una consulta archivan los diagnósticos
 * secundarios como una lista de ids, y la pantalla de correcciones no tenía
 * con qué traducirlos: si una corrección solo cambiaba los secundarios, no se
 * veía qué decían antes.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $this->medico = User::create([
        'email' => 'versiones-secundarios@example.com', 'usuario_ti' => 'versiones-secundarios',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Versiones Secundarias']);
    $puesto = puestoDePrueba($unidad, 'Analista de Versiones Secundarias');
    $paciente = Servidor::create([
        'cedula' => '0812350001', 'nombre' => 'Rosa', 'apellido' => 'Prueba',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);
    $this->historia = HistoriaClinica::create([
        'numero_historia' => $paciente->cedula, 'cedula_paciente' => $paciente->cedula,
        'tipo_paciente' => 'servidor', 'servidor_id' => $paciente->id, 'estado' => true,
    ]);

    $cie10 = fn (string $codigo, string $descripcion) => DiagnosticoCie10::create([
        'codigo' => $codigo, 'descripcion' => $descripcion, 'categoria' => 'ZZ9', 'activo' => true,
    ]);
    $this->principal = $cie10('ZZ90', 'Diagnostico principal de prueba');
    $this->hipertension = $cie10('ZZ91', 'Hipertension de prueba');
    $this->diabetes = $cie10('ZZ92', 'Diabetes de prueba');
    $this->asma = $cie10('ZZ93', 'Asma de prueba');
});

test('las_versiones_anteriores_traen_los_secundarios_con_codigo_y_descripcion', function () {
    $this->actingAs($this->medico, 'sanctum');

    $consulta = $this->postJson('/api/v1/dispensario/consultas', [
        'historia_clinica_id' => $this->historia->id,
        'fecha_consulta' => now()->toDateString(), 'hora_consulta' => now()->format('H:i'),
        'tipo_atencion' => 'primera_vez', 'tipo_diagnostico' => 'presuntivo',
        'motivo_consulta' => 'Control', 'diagnostico_detallado' => 'Control de cronicos',
        'diagnostico_cie10_id' => $this->principal->id,
        'diagnosticos_secundarios' => [$this->hipertension->id, $this->diabetes->id],
    ])->assertCreated()->json('datos');

    // La corrección solo cambia los secundarios: lo demás queda igual.
    $this->patchJson("/api/v1/dispensario/consultas/{$consulta['id']}", [
        'tipo_atencion' => 'primera_vez', 'tipo_diagnostico' => 'presuntivo',
        'motivo_consulta' => 'Control', 'diagnostico_detallado' => 'Control de cronicos',
        'diagnostico_cie10_id' => $this->principal->id,
        'diagnosticos_secundarios' => [$this->asma->id],
    ])->assertOk();

    $versiones = $this->getJson("/api/v1/dispensario/consultas/{$consulta['id']}/versiones")
        ->assertOk()
        ->json('datos');

    expect($versiones)->toHaveCount(1);
    expect(collect($versiones[0]['diagnosticos_secundarios_cie10'])->pluck('codigo')->sort()->values()->all())
        ->toBe(['ZZ91', 'ZZ92']);
    expect($versiones[0]['diagnosticos_secundarios_cie10'][0])
        ->toHaveKeys(['id', 'codigo', 'descripcion']);
});

test('una_version_sin_secundarios_trae_la_lista_vacia', function () {
    $this->actingAs($this->medico, 'sanctum');

    $consulta = $this->postJson('/api/v1/dispensario/consultas', [
        'historia_clinica_id' => $this->historia->id,
        'fecha_consulta' => now()->toDateString(), 'hora_consulta' => now()->format('H:i'),
        'tipo_atencion' => 'primera_vez', 'tipo_diagnostico' => 'presuntivo',
        'motivo_consulta' => 'Control', 'diagnostico_detallado' => 'Sin secundarios',
    ])->assertCreated()->json('datos');

    $this->patchJson("/api/v1/dispensario/consultas/{$consulta['id']}", [
        'tipo_atencion' => 'primera_vez', 'tipo_diagnostico' => 'presuntivo',
        'motivo_consulta' => 'Control', 'diagnostico_detallado' => 'Ahora con uno',
        'diagnosticos_secundarios' => [$this->asma->id],
    ])->assertOk();

    $versiones = $this->getJson("/api/v1/dispensario/consultas/{$consulta['id']}/versiones")
        ->assertOk()
        ->json('datos');

    expect($versiones[0]['diagnosticos_secundarios_cie10'])->toBe([]);
});
