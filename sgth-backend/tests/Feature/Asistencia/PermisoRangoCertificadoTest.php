<?php

/*
| El permiso de un certificado médico guarda solo el primer día del reposo.
| Los listados traen del certificado el rango, para que la tabla enseñe
| «19/11 al 21/11 · 3 días» y no «19/11 · 23h 59m», y nada más: los ven
| Recepción, Trabajo Social y los jefes, que no deben leer el diagnóstico.
*/

use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
use App\Enums\TipoPermiso;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    Servidor::unguard();
    PermisoServidor::unguard();
    ConsultaMedica::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba(['nombre' => 'Dirección Rango Certificado']);
    $this->servidor = Servidor::create([
        'cedula' => '0802704171', 'nombre' => 'Cristhian', 'apellido' => 'Recalde',
        'puesto_id' => puestoDePrueba($unidad)->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);

    $this->permiso = PermisoServidor::create([
        'servidor_id' => $this->servidor->id, 'unidad_administrativa_id' => $unidad->id,
        'tipo' => TipoPermiso::ENFERMEDAD->value, 'fecha' => '2026-11-19',
        'hora_inicio' => '00:00', 'hora_fin' => '23:59', 'estado' => EstadoPermiso::ACTIVO->value,
        'folio' => 'PER-2026-96001', 'vence_en' => '2026-11-24', 'confirmado_en' => '2026-11-19 09:00:00',
    ]);

    $medico = User::factory()->create();
    $historia = HistoriaClinica::create([
        'numero_historia' => '0802704171', 'cedula_paciente' => '0802704171',
        'tipo_paciente' => 'servidor', 'servidor_id' => $this->servidor->id, 'estado' => true,
    ]);
    $consulta = ConsultaMedica::create([
        'historia_clinica_id' => $historia->id, 'medico_id' => $medico->id, 'especialidad' => 'medicina_general',
        'fecha_consulta' => '2026-11-19', 'hora_consulta' => '09:00:00',
        'motivo_consulta' => 'Control', 'diagnostico_detallado' => 'Reservado',
    ]);
    DB::table('certificados_medicos')->insert([
        'consulta_medica_id' => $consulta->id, 'emitido_por' => $medico->id, 'dias_reposo' => 3,
        'fecha_inicio' => '2026-11-19', 'fecha_fin' => '2026-11-21', 'permiso_servidor_id' => $this->permiso->id,
        'observaciones' => 'Reposo absoluto por lumbalgia', 'folio' => 'CM-2026-96001',
        'tipo_paciente' => 'servidor', 'created_at' => now(), 'updated_at' => now(),
    ]);
});

/** Solo el rango: ni diagnóstico, ni observaciones, ni quién lo emitió. */
function esSoloElRangoDelCertificado(array $certificado): void
{
    expect(array_keys($certificado))->toEqualCanonicalizing(['id', 'permiso_servidor_id', 'fecha_inicio', 'fecha_fin', 'dias_reposo'])
        ->and(substr($certificado['fecha_inicio'], 0, 10))->toBe('2026-11-19')
        ->and(substr($certificado['fecha_fin'], 0, 10))->toBe('2026-11-21')
        ->and($certificado['dias_reposo'])->toBe(3);
}

test('el listado de Talento Humano trae el rango del reposo y nada más del certificado', function () {
    $uath = User::create([
        'email' => 'rango-cert@example.com', 'usuario_ti' => 'rango_cert',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $uath->assignRole('admin-uath');

    $res = $this->actingAs($uath, 'sanctum')->getJson('/api/v1/asistencia/permisos')->assertOk();

    $fila = collect($res->json('datos.data'))->firstWhere('folio', 'PER-2026-96001');
    esSoloElRangoDelCertificado($fila['certificado_medico']);
});

test('«Mis permisos» trae el mismo rango', function () {
    $propio = User::create([
        'email' => 'rango-cert-propio@example.com', 'usuario_ti' => 'rango_cert_propio',
        'password' => bcrypt('123456'), 'primer_login' => false, 'servidor_id' => $this->servidor->id,
    ]);
    $propio->assignRole('servidor');

    $res = $this->actingAs($propio, 'sanctum')->getJson('/api/v1/autoservicio/mis-permisos')->assertOk();

    esSoloElRangoDelCertificado($res->json('datos.data.0.certificado_medico'));
});

test('un permiso sin certificado viene con el certificado en null', function () {
    $this->permiso->certificadoMedico()->delete();

    $uath = User::create([
        'email' => 'rango-cert-sin@example.com', 'usuario_ti' => 'rango_cert_sin',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $uath->assignRole('admin-uath');

    $fila = collect($this->actingAs($uath, 'sanctum')->getJson('/api/v1/asistencia/permisos')->json('datos.data'))
        ->firstWhere('folio', 'PER-2026-96001');

    expect($fila)->toHaveKey('certificado_medico')
        ->and($fila['certificado_medico'])->toBeNull();
});
