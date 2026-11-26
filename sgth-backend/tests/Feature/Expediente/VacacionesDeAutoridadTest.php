<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoVacacion;
use App\Enums\TipoNombramiento;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Asistencia\Vacacion;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 4.1d: las vacaciones de una autoridad electa.
|
| **Se las concede el Consejo Provincial por resolución, dentro de los 60 días**
| del COOTAD Art. 47 s (TH, 2026-11-26). Siguen siendo una solicitud de
| vacaciones y no un acto de autoridad [TH N11]: descuentan del mismo saldo. Lo
| que cambia es quién autoriza, y que la vía del jefe inmediato le queda
| cerrada para que no se salte al Consejo sin querer.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    // Sueltos y no con `RolPermisoSeeder`: sincronizar los permisos de cada rol
    // es lo que rompe las pruebas de otros módulos.
    foreach (['buscar-servidores', 'gestionar-vacaciones', 'aprobar-vacaciones'] as $permiso) {
        $this->director->givePermissionTo(
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'sanctum'])
        );
    }
    $this->actingAs($this->director, 'sanctum');

    $this->prefectura = UnidadAdministrativa::create([
        'codigo' => 'PREF', 'nombre' => 'Prefectura Provincial', 'nivel' => 1, 'es_maxima_autoridad' => true,
    ]);
    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $this->prefectura->id, 'plazas' => 1, 'es_jefe' => true,
        'grupo_ocupacional_id'     => GrupoOcupacional::create([
            'grado_codigo' => 'NJS-10', 'grado_numerico' => 10, 'grupo' => 'NJS',
            'denominacion_generica' => 'Prefecto/a', 'rmu' => 5060, 'regimen' => 'losep',
            'activo' => true, 'rol_puesto' => 'dignatario',
        ])->id,
    ]);

    $this->contador = 0;

    /** Una persona con vínculo vigente y 30 días de saldo del año en curso. */
    $this->conSaldo = function (
        TipoNombramiento $nombramiento = TipoNombramiento::ELECCION_POPULAR,
        float $saldo = 30,
    ): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'cedula'                   => str_pad((string) (8600000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'                   => 'Autoridad',
            'apellido'                 => 'Electa'.$this->contador,
            'regimen_laboral'          => 'losep',
            'unidad_administrativa_id' => $this->prefectura->id,
        ]);

        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => $nombramiento->value,
            'numero_contrato'          => 'CRED-'.$this->contador,
            'unidad_administrativa_id' => $this->prefectura->id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => '2023-05-15',
            'fecha_fin'                => now()->addYear()->toDateString(),
            'remuneracion'             => 5060,
            'estado'                   => 'vigente',
        ]);

        PeriodoVacacion::create([
            'servidor_id'          => $servidor->id,
            'anio'                 => now()->year,
            'fecha_inicio_periodo' => Carbon::create(now()->year, 1, 1),
            'fecha_fin_periodo'    => Carbon::create(now()->year, 12, 31),
            'regimen'              => 'losep',
            'anios_antiguedad'     => 3,
            'dias_generados'       => 30,
            'dias_utilizados'      => 30 - $saldo,
            'dias_saldo'           => $saldo,
            'saldo_acumulado'      => $saldo,
            'estado'               => 'abierto',
        ]);

        return $servidor->fresh('contratoVigente');
    };

    $this->conceder = fn (Servidor $s, string $desde, string $hasta, ?string $resolucion = 'RES-CP-2026-120') =>
        $this->postJson('/api/v1/expediente/autoridades-electas/vacaciones', [
            'servidor_id'       => $s->id,
            'fecha_inicio'      => $desde,
            'fecha_fin'         => $hasta,
            'resolucion_numero' => $resolucion,
        ]);

    /** La vía ordinaria: la que usa Talento Humano para cualquier servidor. */
    $this->porLaViaOrdinaria = fn (Servidor $s, string $desde, string $hasta) =>
        $this->postJson('/api/v1/asistencia/vacaciones', [
            'servidor_id'              => $s->id,
            'unidad_administrativa_id' => $this->prefectura->id,
            'motivo'                   => 'vacaciones_anuales',
            'fecha_inicio'             => $desde,
            'fecha_fin'                => $hasta,
            // El formulario los manda; el servicio los recalcula igual.
            'dias_solicitados'         => 10,
            'tipo_dias'                => 'calendario',
        ]);
});

test('el Consejo se las concede por resolución, y los días salen del saldo', function () {
    $prefecta = ($this->conSaldo)();
    // Un lunes y el viernes de esa semana, para que el conteo sea el mismo
    // cualquier año en que se corra la prueba.
    $desde = Carbon::create(now()->year, 7, 1)->next(Carbon::MONDAY);
    $hasta = $desde->copy()->addDays(4);

    $id = ($this->conceder)($prefecta, $desde->toDateString(), $hasta->toDateString())
        ->assertCreated()
        ->json('datos.vacacion_id');

    $vacacion = Vacacion::findOrFail($id);

    expect($vacacion->resolucion_numero)->toBe('RES-CP-2026-120')
        // No espera en ventanilla: la resolución es la autorización y el papel
        // firmado a la vez, así que nace registrada.
        ->and((string) $vacacion->estado)->toBe(EstadoVacacion::REGISTRADA->value)
        // Siete y no cinco: el conteo de vacaciones absorbe el descanso que
        // sigue, igual que a cualquier servidor. Que las conceda el Consejo no
        // cambia cómo se cuentan.
        ->and((float) $vacacion->dias_solicitados)->toBe(7.0)
        ->and((float) PeriodoVacacion::where('servidor_id', $prefecta->id)->sum('dias_saldo'))->toBe(23.0);
});

test('sin la resolución del Consejo no se registran', function () {
    $prefecta = ($this->conSaldo)();

    ($this->conceder)($prefecta, '2026-07-01', '2026-07-10', null)
        ->assertStatus(422)
        ->assertJsonPath('errores.resolucion_numero.0', fn (string $m) => str_contains($m, 'resolución del Consejo'));
});

test('la vía ordinaria le queda cerrada: la manda al Consejo', function () {
    $prefecta = ($this->conSaldo)();

    ($this->porLaViaOrdinaria)($prefecta, '2026-07-01', '2026-07-10')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'Consejo Provincial por resolución')
            && str_contains($m, 'Autoridades electas'));

    expect(Vacacion::where('servidor_id', $prefecta->id)->count())->toBe(0);
});

test('a un servidor que no es autoridad la vía ordinaria le sigue abierta', function () {
    $servidor = ($this->conSaldo)(TipoNombramiento::PERMANENTE);

    ($this->porLaViaOrdinaria)($servidor, '2026-07-01', '2026-07-10')->assertCreated();

    // Y la pantalla de autoridades no es la suya.
    ($this->conceder)($servidor, '2026-08-01', '2026-08-05')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'autoridad electa en funciones'));
});

test('los 60 días del Consejo son uno solo para licencias y vacaciones', function () {
    $prefecta = ($this->conSaldo)(saldo: 30);
    $anio = now()->year;

    // 55 días de licencia del Consejo, dentro del tope.
    $this->postJson("/api/v1/expediente/servidores/{$prefecta->id}/movimientos", [
        'clase'             => 'licencia_consejo',
        'descripcion'       => 'Licencia concedida por el Consejo Provincial',
        'fecha_efectiva'    => Carbon::create($anio, 2, 1)->toDateString(),
        'fecha_inicio'      => Carbon::create($anio, 2, 1)->toDateString(),
        'fecha_fin'         => Carbon::create($anio, 3, 27)->toDateString(),
        'con_remuneracion'  => true,
        'resolucion_numero' => 'RES-CP-2026-001',
    ])->assertCreated();

    // Diez días de vacaciones ya no caben: 55 + 10 pasan de 60. Antes cada
    // contador iba por su lado y el Consejo podía conceder 120.
    ($this->conceder)($prefecta, Carbon::create($anio, 7, 1)->toDateString(), Carbon::create($anio, 7, 10)->toDateString())
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, '65 días concedidos por el Consejo')
            && str_contains($m, 'COOTAD Art. 47 s'));

    // Cinco sí: 55 + 5 son exactamente 60.
    ($this->conceder)($prefecta, Carbon::create($anio, 7, 1)->toDateString(), Carbon::create($anio, 7, 5)->toDateString())
        ->assertCreated();
});

test('que las conceda el Consejo no le regala días: el saldo sigue mandando', function () {
    // Tres días de saldo y una resolución que concede diez.
    $prefecta = ($this->conSaldo)(saldo: 3);
    $anio = now()->year;

    ($this->conceder)($prefecta, Carbon::create($anio, 7, 1)->toDateString(), Carbon::create($anio, 7, 10)->toDateString())
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'Saldo insuficiente'));
});

test('el buscador dice quién es autoridad electa, para no ofrecerla', function () {
    $prefecta = ($this->conSaldo)();
    $servidor = ($this->conSaldo)(TipoNombramiento::PERMANENTE);

    $encontrados = collect($this->getJson('/api/v1/expediente/servidores/buscar?search=Electa')
        ->assertOk()->json('datos'))
        ->keyBy('id');

    expect($encontrados[$prefecta->id]['es_autoridad_electa'])->toBeTrue()
        ->and($encontrados[$servidor->id]['es_autoridad_electa'])->toBeFalse();
});

test('el impreso dice que las concedió el Consejo, y no finge un jefe que firme', function () {
    $prefecta = ($this->conSaldo)();
    $desde = Carbon::create(now()->year, 9, 1)->next(Carbon::MONDAY);

    $id = ($this->conceder)($prefecta, $desde->toDateString(), $desde->copy()->addDays(4)->toDateString())
        ->assertCreated()
        ->json('datos.vacacion_id');

    // Se mira el HTML y no los bytes del PDF: dompdf los comprime, y lo que
    // importa es lo que la hoja dice.
    $html = view('vacaciones.vacacion-pdf', [
        'vacacion'          => Vacacion::with(['servidor.puesto.cargo', 'jefe.puesto.cargo'])->findOrFail($id),
        'urlQr'             => 'http://example.test',
        'firmanteTh'        => 'Directora de Talento Humano',
        'cargoTh'           => 'Dirección de Talento Humano',
        'firmanteAutoridad' => '',
        'cargoAutoridad'    => 'Prefectura',
        ...app(\App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface::class)
            ->paraDocumento(now()),
    ])->render();

    expect($html)->toContain('Resolución del Consejo Provincial N.º RES-CP-2026-120')
        ->and($html)->toContain('Consejo Provincial')
        // La línea de «Jefe inmediato» diría que alguien la concedió, y el
        // pie de siempre, que la hoja se baja a firmar a ventanilla.
        ->and($html)->not->toContain('Jefe inmediato')
        ->and($html)->not->toContain('su jefe inmediato en la')
        ->and($html)->toContain('no se baja a firmar');
});
