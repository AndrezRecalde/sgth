<?php

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\AgendaMedica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Dispensario\TriajeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/*
 * La cola del médico ordenaba a los que esperan solo por llegada: quien llegó
 * con la presión disparada iba detrás de un resfriado que llegó antes.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $this->medico = User::create([
        'email' => 'cola-prioridad@example.com', 'usuario_ti' => 'colaprio',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));

    $unidad = unidadDePrueba(['nombre' => 'Direccion de la Cola']);
    $puesto = puestoDePrueba($unidad, 'Analista de la Cola');
    $this->pacienteDeCola = fn (string $cedula) => Servidor::create([
        'cedula' => $cedula, 'nombre' => "Paciente {$cedula}", 'apellido' => 'Cola',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);
});

function turnoEnCola(Servidor $paciente, User $medico, string $folio, int $minutosAtras): AgendaMedica
{
    return AgendaMedica::create([
        'folio' => $folio, 'tipo_atencion' => 'medicina_general',
        'servidor_id' => $paciente->id, 'medico_id' => $medico->id,
        'fecha' => now()->toDateString(), 'estado' => 'en_espera',
        'requiere_triaje' => true, 'registrado_en' => now()->subMinutes($minutosAtras),
    ]);
}

function tomarTriajeEnCola(AgendaMedica $turno, int $sistolica, int $diastolica, User $quien): void
{
    app(TriajeService::class)->registrar($turno->id, [
        'presion_sistolica' => $sistolica, 'presion_diastolica' => $diastolica,
        'frecuencia_cardiaca' => 76, 'frecuencia_respiratoria' => 16,
        'temperatura_c' => 36.6, 'saturacion_oxigeno' => 98,
        'peso_kg' => 70, 'talla_cm' => 170,
    ], $quien->id);
}

test('entre_los_que_esperan_va_primero_el_triaje_critico', function () {
    $resfriado = turnoEnCola(($this->pacienteDeCola)('0812349001'), $this->medico, 'TUR-2026-80001', 40);
    $hipertenso = turnoEnCola(($this->pacienteDeCola)('0812349002'), $this->medico, 'TUR-2026-80002', 10);

    tomarTriajeEnCola($resfriado, 118, 76, $this->medico);
    tomarTriajeEnCola($hipertenso, 210, 125, $this->medico);

    expect($hipertenso->triaje()->first()->nivel_alerta->value)->toBe('critico');

    $folios = $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/agenda/turnos-del-dia')
        ->assertOk()
        ->json('datos.*.folio');

    // Llegó media hora después, pero va delante.
    expect($folios)->toBe(['TUR-2026-80002', 'TUR-2026-80001']);
});

test('con_el_mismo_nivel_se_respeta_el_orden_de_llegada', function () {
    $primero = turnoEnCola(($this->pacienteDeCola)('0812349003'), $this->medico, 'TUR-2026-80003', 40);
    $segundo = turnoEnCola(($this->pacienteDeCola)('0812349004'), $this->medico, 'TUR-2026-80004', 10);

    tomarTriajeEnCola($primero, 118, 76, $this->medico);
    tomarTriajeEnCola($segundo, 120, 78, $this->medico);

    expect($this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/agenda/turnos-del-dia')
        ->json('datos.*.folio'))->toBe(['TUR-2026-80003', 'TUR-2026-80004']);
});
