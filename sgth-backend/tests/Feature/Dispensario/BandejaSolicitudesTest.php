<?php

use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| La bandeja del médico ocupacional (`/salud/sso`). Arrancaba en «pendiente» y
| escondía las evaluaciones en curso, ordenaba por fecha de creación en vez de
| por lo que vence primero y no dejaba buscar a nadie.
*/

function solicitudBandeja(User $quien, string $cedula, string $nombre, string $estado, ?string $limite): SolicitudCertificacionMedica
{
    return SolicitudCertificacionMedica::create([
        'tipo_evento' => 'periodica', 'origen' => 'expediente',
        'cedula_paciente' => $cedula, 'nombres_paciente' => $nombre,
        'solicitado_por' => $quien->id, 'estado' => $estado,
        'fecha_limite' => $limite,
    ]);
}

beforeEach(function () {
    $this->medico = User::factory()->create();
    $this->medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));
});

test('«activas» trae lo pendiente y lo que está en curso, por lo que vence primero', function () {
    solicitudBandeja($this->medico, '0800000001', 'Ana Vence Tarde', 'pendiente', '2026-12-01');
    solicitudBandeja($this->medico, '0800000002', 'Luis En Curso', 'en_proceso', '2026-10-05');
    solicitudBandeja($this->medico, '0800000003', 'Eva Cerrada', 'completada', '2026-09-01');
    solicitudBandeja($this->medico, '0800000004', 'Iván Sin Fecha', 'pendiente', null);

    $nombres = collect($this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/solicitudes-certificacion?estado=activas&orden=fecha_limite')
        ->assertOk()
        ->json('datos.data'))->pluck('nombres_paciente')->all();

    expect($nombres)->toBe(['Luis En Curso', 'Ana Vence Tarde', 'Iván Sin Fecha']);
});

test('se busca por cédula o por nombre', function () {
    solicitudBandeja($this->medico, '0804258986', 'Francis Quinde', 'pendiente', '2026-10-10');
    solicitudBandeja($this->medico, '0802704171', 'Cristhian Recalde', 'pendiente', '2026-10-10');

    $porNombre = $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/solicitudes-certificacion?buscar=quinde')
        ->assertOk()->json('datos.data');
    $porCedula = $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/solicitudes-certificacion?buscar=0802704')
        ->assertOk()->json('datos.data');

    expect($porNombre)->toHaveCount(1)
        ->and($porNombre[0]['cedula_paciente'])->toBe('0804258986')
        ->and($porCedula)->toHaveCount(1)
        ->and($porCedula[0]['nombres_paciente'])->toBe('Cristhian Recalde');
});
