<?php

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\AgendaMedica;
use App\Models\Dispensario\AtencionEnfermeria;
use App\Models\Dispensario\CatalogoServicioEnfermeria;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

function usuarioConRolValidaciones(string $usuario, string $rol): User
{
    $user = User::forceCreate([
        'email' => "{$usuario}@example.com", 'usuario_ti' => $usuario,
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));

    return $user;
}

beforeEach(function () {
    $this->enfermera = usuarioConRolValidaciones('enfa', 'enfermera');
    $this->otraEnfermera = usuarioConRolValidaciones('enfb', 'enfermera');
    $this->admin = usuarioConRolValidaciones('admdisp', 'admin-dispensario');
    $this->medico = usuarioConRolValidaciones('medv', 'medico');
    $this->odontologo = usuarioConRolValidaciones('odov', 'odontologo');

    $unidad = unidadDePrueba(['nombre' => 'Dirección Validaciones']);
    $this->paciente = Servidor::forceCreate([
        'cedula' => '0802222222', 'nombre' => 'Luis', 'apellido' => 'Mora',
        'puesto_id' => puestoDePrueba($unidad, 'Analista Validaciones')->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2),
        'estado' => true,
    ]);

    $this->servicio = CatalogoServicioEnfermeria::create(['nombre' => 'Curación', 'activo' => true]);
});

test('solo quien registró la atención, o la administración, la anula', function () {
    $this->actingAs($this->enfermera, 'sanctum');
    $atencion = $this->postJson('/api/v1/dispensario/atenciones-enfermeria', [
        'servidor_id' => $this->paciente->id,
        'catalogo_servicio_id' => $this->servicio->id,
    ])->assertCreated()->json('datos');

    $this->actingAs($this->otraEnfermera, 'sanctum');
    $this->patchJson("/api/v1/dispensario/atenciones-enfermeria/{$atencion['id']}/anular", [
        'motivo_anulacion' => 'No es mía',
    ])->assertUnprocessable();
    expect(AtencionEnfermeria::find($atencion['id'])->anulado_en)->toBeNull();

    $this->actingAs($this->admin, 'sanctum');
    $this->patchJson("/api/v1/dispensario/atenciones-enfermeria/{$atencion['id']}/anular", [
        'motivo_anulacion' => 'Paciente equivocado',
    ])->assertOk();
});

test('no se registra un servicio retirado del catálogo', function () {
    $this->actingAs($this->enfermera, 'sanctum');
    $retirado = CatalogoServicioEnfermeria::create(['nombre' => 'Nebulización', 'activo' => false]);

    $this->postJson('/api/v1/dispensario/atenciones-enfermeria', [
        'servidor_id' => $this->paciente->id,
        'catalogo_servicio_id' => $retirado->id,
    ])->assertUnprocessable()->assertJsonPath('errores.catalogo_servicio_id.0', 'El servicio elegido ya no está disponible en el catálogo.');
});

test('los filtros del listado se validan en vez de dar 500', function () {
    $this->actingAs($this->enfermera, 'sanctum');

    $this->getJson('/api/v1/dispensario/atenciones-enfermeria?fecha=abc')->assertUnprocessable();
    $this->getJson('/api/v1/dispensario/atenciones-enfermeria?per_page=100000')->assertUnprocessable();
    $this->getJson('/api/v1/dispensario/agenda?fecha=abc')->assertUnprocessable();
    $this->getJson('/api/v1/dispensario/atenciones-enfermeria?fecha=' . now()->toDateString())->assertOk();
});

test('el turno va a un profesional de esa especialidad', function () {
    $this->actingAs($this->enfermera, 'sanctum');

    $this->postJson('/api/v1/dispensario/agenda', [
        'servidor_id' => $this->paciente->id,
        'medico_id' => $this->medico->id,
        'tipo_atencion' => 'odontologia',
    ])->assertUnprocessable()->assertJsonPath('errores.medico_id.0', 'El profesional elegido no atiende odontología.');

    $this->postJson('/api/v1/dispensario/agenda', [
        'servidor_id' => $this->paciente->id,
        'medico_id' => $this->odontologo->id,
        'tipo_atencion' => 'odontologia',
    ])->assertCreated();
});

test('el triaje rechaza la diastólica mayor que la sistólica y admite una bradipnea', function () {
    $this->actingAs($this->enfermera, 'sanctum');
    $turno = AgendaMedica::forceCreate([
        'folio' => 'TUR-V-1', 'servidor_id' => $this->paciente->id,
        'medico_id' => $this->medico->id, 'tipo_atencion' => 'medicina_general',
        'fecha' => now()->toDateString(), 'estado' => 'en_espera',
        'requiere_triaje' => true, 'registrado_en' => now(), 'estado_registro' => true,
    ]);

    $signos = [
        'presion_sistolica' => 80, 'presion_diastolica' => 120,
        'frecuencia_cardiaca' => 70, 'frecuencia_respiratoria' => 8,
        'temperatura_c' => 36.5, 'saturacion_oxigeno' => 98,
        'peso_kg' => 70, 'talla_cm' => 170,
    ];

    $this->postJson("/api/v1/dispensario/agenda/{$turno->id}/triaje", $signos)
        ->assertUnprocessable()
        ->assertJsonPath('errores.presion_diastolica.0', 'La presión diastólica debe ser menor que la sistólica.')
        // FR 8 ya no se rechaza: es justo el caso crítico que hay que poder registrar.
        ->assertJsonMissingPath('errores.frecuencia_respiratoria');

    $this->postJson("/api/v1/dispensario/agenda/{$turno->id}/triaje", [...$signos, 'presion_sistolica' => 120, 'presion_diastolica' => 80])
        ->assertCreated()
        ->assertJsonPath('datos.nivel_alerta', 'critico');
});

test('los signos SSO: corregir responde 200 y una solicitud en proceso no aparece como pendiente', function () {
    $this->actingAs($this->enfermera, 'sanctum');
    $solicitud = SolicitudCertificacionMedica::forceCreate([
        'servidor_id' => $this->paciente->id, 'tipo_evento' => 'periodica', 'origen' => 'expediente',
        'cedula_paciente' => $this->paciente->cedula, 'nombres_paciente' => 'Luis Mora',
        'estado' => 'pendiente', 'fecha_limite' => now()->addWeek()->toDateString(),
        'solicitado_por' => $this->admin->id,
    ]);
    $signos = [
        'presion_sistolica' => 120, 'presion_diastolica' => 80,
        'frecuencia_cardiaca' => 70, 'frecuencia_respiratoria' => 16,
        'temperatura_c' => 36.5, 'saturacion_oxigeno' => 98,
        'peso_kg' => 70, 'talla_cm' => 170,
    ];

    $this->postJson("/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}/signos-vitales", $signos)->assertCreated();
    $this->postJson("/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}/signos-vitales", [...$signos, 'peso_kg' => 72])
        ->assertOk()->assertJsonPath('mensaje', 'Signos vitales corregidos.');

    $otra = SolicitudCertificacionMedica::forceCreate([
        'servidor_id' => $this->paciente->id, 'tipo_evento' => 'periodica', 'origen' => 'expediente',
        'cedula_paciente' => $this->paciente->cedula, 'nombres_paciente' => 'Luis Mora',
        'estado' => 'en_proceso', 'fecha_limite' => now()->addWeek()->toDateString(),
        'solicitado_por' => $this->admin->id,
    ]);

    $ids = collect($this->getJson('/api/v1/dispensario/solicitudes-certificacion/pendientes-triaje')->assertOk()->json('datos'))->pluck('id');
    expect($ids)->not->toContain($otra->id);
});
