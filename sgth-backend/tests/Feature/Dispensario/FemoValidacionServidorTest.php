<?php

use App\Models\Dispensario\DiagnosticoCie10;
use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Dispensario\SolicitudConstantesVitales;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| Lo que el servidor ya no le cree al navegador al guardar una ficha FEMO:
| las constantes vitales (salen del triaje), las fechas, la coherencia entre
| campos, y qué parte del triaje ve Talento Humano.
*/

function usuarioValidacionFemo(string $rol): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));

    return $usuario;
}

function solicitudValidacionFemo(User $quien, string $estado = 'en_proceso', string $tipo = 'periodica'): SolicitudCertificacionMedica
{
    Servidor::unguard();
    $servidor = Servidor::create([
        'cedula' => (string) random_int(1000000000, 1999999999),
        'nombre' => 'Rafael', 'apellido' => 'Quinde', 'genero' => 'femenino',
    ]);

    return SolicitudCertificacionMedica::create([
        'tipo_evento' => $tipo,
        'origen' => 'expediente',
        'servidor_id' => $servidor->id,
        'cedula_paciente' => $servidor->cedula,
        'nombres_paciente' => 'Rafael Quinde',
        'solicitado_por' => $quien->id,
        'estado' => $estado,
        'fecha_limite' => now()->addDays(7),
    ]);
}

function triajeValidacionFemo(SolicitudCertificacionMedica $solicitud, User $enfermera): void
{
    SolicitudConstantesVitales::create([
        'solicitud_id' => $solicitud->id,
        'enfermera_id' => $enfermera->id,
        'peso_kg' => 82, 'talla_cm' => 169, 'imc' => 28.71,
        'temperatura_c' => 36.5, 'presion_sistolica' => 110, 'presion_diastolica' => 70,
        'frecuencia_cardiaca' => 79, 'frecuencia_respiratoria' => 20,
        'saturacion_oxigeno' => 99, 'perimetro_abdominal_cm' => 94,
        'observaciones_enfermera' => 'Refiere cefalea leve.',
    ]);
}

function postFichaValidacionFemo($test, User $medico, SolicitudCertificacionMedica $solicitud, array $extra = [])
{
    return $test->actingAs($medico, 'sanctum')->postJson('/api/v1/dispensario/fichas-sso', [
        'solicitud_id' => $solicitud->id,
        ...$extra,
        'ficha' => ['fecha_evaluacion' => '2026-10-01', ...($extra['ficha'] ?? [])],
    ]);
}

beforeEach(function () {
    $this->medico = usuarioValidacionFemo('medico');
    $this->enfermera = usuarioValidacionFemo('enfermera');
    $this->solicitud = solicitudValidacionFemo($this->medico);
});

test('las constantes vitales de la ficha se copian del triaje, no del cliente', function () {
    triajeValidacionFemo($this->solicitud, $this->enfermera);

    $id = postFichaValidacionFemo($this, $this->medico, $this->solicitud, [
        'constantes_vitales' => ['peso_kg' => 1000, 'imc' => 99, 'temperatura_c' => 99],
    ])->assertCreated()->json('datos.id');

    $cv = FichaSaludOcupacional::findOrFail($id)->constantesVitales;
    expect((float) $cv->peso_kg)->toBe(82.0)
        ->and((float) $cv->imc)->toBe(28.71)
        ->and((float) $cv->temperatura_c)->toBe(36.5)
        ->and((float) $cv->perimetro_abdominal_cm)->toBe(94.0);
});

test('Enfermería registra el perímetro abdominal en el triaje', function () {
    $solicitud = solicitudValidacionFemo($this->medico, 'pendiente');

    $this->actingAs($this->enfermera, 'sanctum')
        ->postJson("/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}/signos-vitales", [
            'presion_sistolica' => 120, 'presion_diastolica' => 80,
            'frecuencia_cardiaca' => 72, 'frecuencia_respiratoria' => 16,
            'temperatura_c' => 36.6, 'saturacion_oxigeno' => 98,
            'peso_kg' => 70, 'talla_cm' => 170, 'perimetro_abdominal_cm' => 88.5,
        ])
        ->assertCreated();

    expect((float) $solicitud->constantesVitales()->first()->perimetro_abdominal_cm)->toBe(88.5);
});

test('la diastólica no puede ser mayor o igual que la sistólica', function () {
    $solicitud = solicitudValidacionFemo($this->medico, 'pendiente');

    $this->actingAs($this->enfermera, 'sanctum')
        ->postJson("/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}/signos-vitales", [
            'presion_sistolica' => 80, 'presion_diastolica' => 120,
            'frecuencia_cardiaca' => 72, 'frecuencia_respiratoria' => 16,
            'temperatura_c' => 36.6, 'saturacion_oxigeno' => 98,
            'peso_kg' => 70, 'talla_cm' => 170,
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['presion_diastolica']]);
});

test('el triaje no se reescribe una vez iniciada la evaluación', function () {
    triajeValidacionFemo($this->solicitud, $this->enfermera);

    $this->actingAs($this->enfermera, 'sanctum')
        ->postJson("/api/v1/dispensario/solicitudes-certificacion/{$this->solicitud->id}/signos-vitales", [
            'presion_sistolica' => 150, 'presion_diastolica' => 95,
            'frecuencia_cardiaca' => 72, 'frecuencia_respiratoria' => 16,
            'temperatura_c' => 36.6, 'saturacion_oxigeno' => 98,
            'peso_kg' => 70, 'talla_cm' => 170,
        ])
        ->assertStatus(422);

    expect($this->solicitud->constantesVitales()->first()->presion_sistolica)->toBe(110);
});

test('Talento Humano sabe que se tomaron los signos, pero no los lee', function () {
    triajeValidacionFemo($this->solicitud, $this->enfermera);
    $th = usuarioValidacionFemo('admin-uath');

    $fila = collect($this->actingAs($th, 'sanctum')
        ->getJson('/api/v1/dispensario/solicitudes-certificacion')
        ->assertOk()
        ->json('datos.data'))->firstWhere('id', $this->solicitud->id);

    expect($fila['constantes_vitales'])->not->toBeNull()
        ->and($fila['constantes_vitales'])->not->toHaveKey('presion_sistolica')
        ->and($fila['constantes_vitales'])->not->toHaveKey('observaciones_enfermera');

    $this->actingAs($th, 'sanctum')
        ->getJson("/api/v1/dispensario/solicitudes-certificacion/{$this->solicitud->id}")
        ->assertOk()
        ->assertJsonMissingPath('datos.constantes_vitales.presion_sistolica');
});

test('el médico sí lee el triaje completo', function () {
    triajeValidacionFemo($this->solicitud, $this->enfermera);

    $this->actingAs($this->medico, 'sanctum')
        ->getJson("/api/v1/dispensario/solicitudes-certificacion/{$this->solicitud->id}")
        ->assertOk()
        ->assertJsonPath('datos.constantes_vitales.presion_sistolica', 110);
});

test('la fecha de atención no puede ser futura', function () {
    postFichaValidacionFemo($this, $this->medico, $this->solicitud, [
        'ficha' => ['fecha_evaluacion' => now()->addYear()->toDateString()],
    ])->assertStatus(422)->assertJsonStructure(['errores' => ['ficha.fecha_evaluacion']]);
});

test('la fecha de ingreso no puede ser posterior a la atención', function () {
    postFichaValidacionFemo($this, $this->medico, $this->solicitud, [
        'ficha' => ['fecha_ingreso_trabajo' => '2026-10-02'],
    ])->assertStatus(422)->assertJsonStructure(['errores' => ['ficha.fecha_ingreso_trabajo']]);
});

test('un factor de riesgo tiene que ser de su propia categoría', function () {
    postFichaValidacionFemo($this, $this->medico, $this->solicitud, [
        'factores_riesgo' => [['categoria' => 'quimico', 'factor' => 'Ruido', 'presente' => true]],
    ])->assertStatus(422)->assertJsonStructure(['errores' => ['factores_riesgo.0.factor']]);
});

test('un factor no puede colgar de una actividad que no existe', function () {
    postFichaValidacionFemo($this, $this->medico, $this->solicitud, [
        'actividades' => [['actividad' => 'Mantenimiento eléctrico']],
        'factores_riesgo' => [['categoria' => 'fisico', 'factor' => 'Ruido', 'actividad_index' => 3]],
    ])->assertStatus(422)->assertJsonStructure(['errores' => ['factores_riesgo.0.actividad_index']]);
});

test('el formulario admite hasta siete actividades', function () {
    postFichaValidacionFemo($this, $this->medico, $this->solicitud, [
        'actividades' => array_map(fn ($i) => ['actividad' => "Actividad {$i}"], range(1, 8)),
    ])->assertStatus(422)->assertJsonStructure(['errores' => ['actividades']]);
});

test('el mismo diagnóstico no se repite', function () {
    $cie = DiagnosticoCie10::create([
        'codigo' => 'E660', 'descripcion' => 'OBESIDAD', 'categoria' => 'E66', 'activo' => true,
    ]);

    postFichaValidacionFemo($this, $this->medico, $this->solicitud, [
        'diagnosticos' => [
            ['diagnostico_cie10_id' => $cie->id, 'tipo' => 'presuntivo', 'orden' => 1],
            ['diagnostico_cie10_id' => $cie->id, 'tipo' => 'definitivo', 'orden' => 2],
        ],
    ])->assertStatus(422);
});

test('las gestas no pueden ser menos que partos, cesáreas y abortos', function () {
    postFichaValidacionFemo($this, $this->medico, $this->solicitud, [
        'antecedente_reproductivo' => ['gestas' => 1, 'partos' => 1, 'cesareas' => 1],
    ])->assertStatus(422)->assertJsonStructure(['errores' => ['antecedente_reproductivo.gestas']]);
});

test('un empleo no termina antes de empezar', function () {
    postFichaValidacionFemo($this, $this->medico, $this->solicitud, [
        'empleos_anteriores' => [[
            'centro_trabajo' => 'GADPE', 'tipo_evento_laboral' => 'ninguno',
            'fecha_inicio' => '2020-01-01', 'fecha_fin' => '2019-01-01',
        ]],
    ])->assertStatus(422)->assertJsonStructure(['errores' => ['empleos_anteriores.0.fecha_fin']]);
});

test('los datos de retiro no se guardan en una evaluación que no es de retiro', function () {
    $id = postFichaValidacionFemo($this, $this->medico, $this->solicitud, [
        'ficha' => [
            'fecha_ultimo_dia_laboral' => '2026-09-30',
            'se_realiza_evaluacion_retiro' => true,
            'observacion_retiro' => 'Texto que el PDF no imprime',
            'fecha_reintegro' => '2026-09-01',
        ],
    ])->assertCreated()->json('datos.id');

    $ficha = FichaSaludOcupacional::findOrFail($id);
    expect($ficha->fecha_ultimo_dia_laboral)->toBeNull()
        ->and($ficha->se_realiza_evaluacion_retiro)->toBeNull()
        ->and($ficha->observacion_retiro)->toBeNull()
        ->and($ficha->fecha_reintegro)->toBeNull();
});
