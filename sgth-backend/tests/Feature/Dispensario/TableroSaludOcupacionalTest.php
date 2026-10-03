<?php

use App\Models\Dispensario\DiagnosticoCie10;
use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Dispensario\SolicitudConstantesVitales;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| El tablero de salud ocupacional del Dispensario: la bandeja en cifras, la
| aptitud emitida en el año, los diagnósticos y los factores de riesgo.
*/

function usuarioTableroSso(string $rol): User
{
    $u = User::factory()->create();
    $u->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));

    return $u;
}

function solicitudTableroSso(User $quien, string $estado, string $tipo = 'periodica', ?string $limite = null, int $diasAtras = 0): SolicitudCertificacionMedica
{
    $s = SolicitudCertificacionMedica::create([
        'tipo_evento' => $tipo, 'origen' => 'expediente',
        'cedula_paciente' => (string) random_int(1000000000, 1999999999),
        'nombres_paciente' => 'Paciente', 'solicitado_por' => $quien->id,
        'estado' => $estado, 'fecha_limite' => $limite,
    ]);
    $s->forceFill(['created_at' => Carbon::today()->subDays($diasAtras)])->save();

    return $s;
}

function fichaCerradaTableroSso(User $medico, string $aptitud, string $fecha): FichaSaludOcupacional
{
    Servidor::unguard();
    $servidor = Servidor::create([
        'cedula' => (string) random_int(1000000000, 1999999999), 'nombre' => 'A', 'apellido' => 'B',
    ]);
    $ficha = FichaSaludOcupacional::create([
        'servidor_id' => $servidor->id, 'evaluador_id' => $medico->id,
        'fecha_evaluacion' => $fecha, 'tipo_ficha' => 'periodica',
        'aptitud' => $aptitud, 'estado' => true,
    ]);
    $s = solicitudTableroSso($medico, 'completada');
    $s->update(['ficha_femo_id' => $ficha->id, 'dictamen' => $aptitud]);

    return $ficha;
}

beforeEach(function () {
    $this->medico = usuarioTableroSso('medico');
});

test('la bandeja en cifras: por atender, vencidas, sin triaje y retiros', function () {
    $conTriaje = solicitudTableroSso($this->medico, 'pendiente', 'retiro', Carbon::yesterday()->toDateString(), 2);
    SolicitudConstantesVitales::create([
        'solicitud_id' => $conTriaje->id, 'enfermera_id' => $this->medico->id,
        'peso_kg' => 70, 'talla_cm' => 170,
    ]);
    solicitudTableroSso($this->medico, 'pendiente', 'periodica', null, 10);
    solicitudTableroSso($this->medico, 'en_proceso', 'ingreso', Carbon::tomorrow()->toDateString(), 20);
    solicitudTableroSso($this->medico, 'cancelada', 'retiro', Carbon::yesterday()->toDateString());

    $datos = $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/salud-ocupacional/tablero')
        ->assertOk()
        ->json('datos');

    expect($datos['bandeja'])->toBe([
        'pendientes' => 2, 'en_proceso' => 1, 'vencidas' => 1, 'sin_triaje' => 1, 'retiros' => 1,
    ])
        ->and($datos['antiguedad'])->toBe(['0_3' => 1, '4_7' => 0, '8_15' => 1, 'mas_15' => 1])
        ->and($datos['abiertas_por_tipo'])->toEqual(['retiro' => 1, 'periodica' => 1, 'ingreso' => 1]);
});

test('la aptitud, los diagnósticos y los factores son los del año pedido', function () {
    $f1 = fichaCerradaTableroSso($this->medico, 'apto', '2026-03-10');
    $f2 = fichaCerradaTableroSso($this->medico, 'en_observacion', '2026-05-10');
    fichaCerradaTableroSso($this->medico, 'no_apto', '2025-12-31');

    $cie = DiagnosticoCie10::create(['codigo' => 'E660', 'descripcion' => 'OBESIDAD', 'categoria' => 'E66', 'activo' => true]);
    foreach ([$f1, $f2] as $f) {
        $f->diagnosticos()->create(['diagnostico_cie10_id' => $cie->id, 'tipo' => 'definitivo', 'orden' => 1]);
        $f->factoresRiesgo()->create(['categoria' => 'fisico', 'factor' => 'Ruido', 'presente' => true]);
    }

    $datos = $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/salud-ocupacional/tablero?anio=2026')
        ->assertOk()
        ->json('datos');

    expect($datos['aptitud'])->toBe([
        'apto' => 1, 'en_observacion' => 1, 'apto_con_restricciones' => 0, 'no_apto' => 0,
    ])
        ->and($datos['diagnosticos'][0])->toBe(['codigo' => 'E660', 'descripcion' => 'OBESIDAD', 'total' => 2])
        ->and($datos['factores_riesgo'][0])->toBe(['categoria' => 'fisico', 'factor' => 'Ruido', 'fichas' => 2]);
});

test('Talento Humano no entra al tablero clínico', function () {
    $this->actingAs(usuarioTableroSso('admin-uath'), 'sanctum')
        ->getJson('/api/v1/dispensario/salud-ocupacional/tablero')
        ->assertStatus(403);
});
