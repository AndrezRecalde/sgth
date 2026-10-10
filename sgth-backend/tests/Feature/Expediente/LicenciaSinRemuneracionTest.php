<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 2.2 del diseño de Acciones de Personal (4.4): la licencia sin
| remuneración lleva la causal de LOSEP Art. 28, siempre fechas y el tope de su
| causal, y es solo de permanentes [TH N9]. Obreros y autoridades conservan una
| causal transitoria hasta que lleguen sus bloques.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    $director = User::factory()->create();
    $director->assignRole('admin-uath');
    $this->actingAs($director, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-LIC', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-LIC', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 10,
    ]);

    $this->contador = 0;

    $this->servidorCon = function (TipoNombramiento $nombramiento, string $ingreso = '2015-01-05'): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'cedula'          => str_pad((string) (8600000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Servidor',
            'apellido'        => 'Licencia'.$this->contador,
            'regimen_laboral' => 'losep',
            'fecha_ingreso_institucion' => $ingreso,
        ]);

        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => $nombramiento->value,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => $ingreso,
            'fecha_fin'                => $nombramiento === TipoNombramiento::SERVICIOS_OCASIONALES ? now()->addYear()->toDateString() : null,
            'estado'                   => 'vigente',
        ]);

        return $servidor->fresh('contratoVigente');
    };

    $this->licencia = fn (Servidor $s, ?string $causal, ?string $desde, ?string $hasta) => $this->postJson(
        "/api/v1/expediente/servidores/{$s->id}/movimientos",
        array_filter([
            'clase'                    => 'licencia_sin_remuneracion',
            'causal'                   => $causal,
            'descripcion'              => 'Licencia de prueba',
            'fecha_efectiva'           => $desde ?? '2027-03-01',
            'fecha_inicio'             => $desde,
            'fecha_fin'                => $hasta,
            'requiere_dictamen_medico' => false,
        ], fn ($v) => $v !== null)
    );
});

// ── Causal, fechas y a quién ────────────────────────────────────

test('sin causal no se registra', function () {
    ($this->licencia)(($this->servidorCon)(TipoNombramiento::PERMANENTE), null, '2027-03-01', '2027-03-10')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'Indique la causal'));
});

test('sin fechas tampoco', function () {
    ($this->licencia)(($this->servidorCon)(TipoNombramiento::PERMANENTE), 'servicio_militar', null, null)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'lleva siempre fecha'));
});

test('las causales de la LOSEP son solo de permanentes', function () {
    ($this->licencia)(($this->servidorCon)(TipoNombramiento::PERMANENTE), 'servicio_militar', '2027-03-01', '2027-09-01')
        ->assertCreated()
        ->assertJsonPath('datos.causal_base_legal', 'LOSEP Art. 28 c');

    ($this->licencia)(($this->servidorCon)(TipoNombramiento::PROVISIONAL), 'servicio_militar', '2027-03-01', '2027-09-01')
        ->assertStatus(422);
});

test('obreros y autoridades conservan la suya, sin los topes de la LOSEP', function () {
    $obrero = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);

    ($this->licencia)($obrero, 'segun_su_regimen', '2027-03-01', '2027-12-31')->assertCreated();
    ($this->licencia)($obrero, 'asuntos_particulares', '2027-03-01', '2027-03-05')->assertStatus(422);
});

// ── Los topes ───────────────────────────────────────────────────

test('asuntos particulares: hasta 60 días por año, sumando las anteriores', function () {
    $permanente = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    ($this->licencia)($permanente, 'asuntos_particulares', '2027-02-01', '2027-03-02')->assertCreated(); // 30 días
    ($this->licencia)($permanente, 'asuntos_particulares', '2027-05-01', '2027-05-30')->assertCreated(); // 30 días

    ($this->licencia)($permanente, 'asuntos_particulares', '2027-08-01', '2027-08-01')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'en 2027 serían 61'));

    // El año siguiente empieza de cero.
    ($this->licencia)($permanente, 'asuntos_particulares', '2028-01-10', '2028-02-08')->assertCreated();
});

test('una licencia que cruza el año cuenta en cada uno lo que le toca', function () {
    $permanente = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    // 50 días en 2027 y 10 en 2028.
    ($this->licencia)($permanente, 'asuntos_particulares', '2027-11-12', '2028-01-10')->assertCreated();

    ($this->licencia)($permanente, 'asuntos_particulares', '2027-03-01', '2027-03-11')->assertStatus(422);
    ($this->licencia)($permanente, 'asuntos_particulares', '2028-03-01', '2028-03-30')->assertCreated();
});

test('las anuladas no cuentan, y corregir el borrador no se cuenta a sí mismo', function () {
    $permanente = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $anulada = MovimientoPersonal::find(
        ($this->licencia)($permanente, 'asuntos_particulares', '2027-02-01', '2027-03-22')->json('datos.id') // 50 días
    );
    $anulada->update(['estado' => EstadoAccionPersonal::ANULADA]);

    $borrador = ($this->licencia)($permanente, 'asuntos_particulares', '2027-05-01', '2027-06-19')->assertCreated(); // 50 días

    $this->putJson("/api/v1/expediente/movimientos/{$borrador->json('datos.id')}", [
        'fecha_fin' => '2027-06-29', // 60 días
    ])->assertOk();

    $this->putJson("/api/v1/expediente/movimientos/{$borrador->json('datos.id')}", [
        'fecha_fin' => '2027-06-30', // 61
    ])->assertStatus(422);
});

test('estudios de posgrado: 2 años de servicio a la fecha de inicio', function () {
    ($this->licencia)(($this->servidorCon)(TipoNombramiento::PERMANENTE, '2026-01-01'), 'estudios_posgrado', '2027-03-01', '2028-03-01')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, '2 años de servicio'));

    ($this->licencia)(($this->servidorCon)(TipoNombramiento::PERMANENTE, '2025-02-01'), 'estudios_posgrado', '2027-03-01', '2028-03-01')
        ->assertCreated();
});

test('cuidado de hijos: hasta 12 meses y dentro de los primeros 15 de vida de un hijo registrado', function () {
    $madre = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    // Sin el hijo en Cargas familiares no hay contra qué medir.
    ($this->licencia)($madre, 'cuidado_hijos', '2027-03-01', '2027-08-31')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'registrado en Cargas familiares'));

    CargaFamiliar::create([
        'servidor_id' => $madre->id, 'apellidos' => 'Licencia', 'nombres' => 'Bebé',
        'parentesco' => 'hijo', 'fecha_nacimiento' => '2027-01-15', 'estado' => true,
    ]);

    ($this->licencia)($madre, 'cuidado_hijos', '2027-03-01', '2027-08-31')->assertCreated();

    // Más de 12 meses.
    ($this->licencia)($madre, 'cuidado_hijos', '2027-03-01', '2028-03-01')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'hasta 12 meses'));

    // Termina después de los 15 meses de vida (15/04/2028).
    ($this->licencia)($madre, 'cuidado_hijos', '2027-06-01', '2028-05-01')->assertStatus(422);
});

// ── Lo que se ve ────────────────────────────────────────────────

test('con fechas, la licencia registrada sale en ausencias', function () {
    $permanente = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    MovimientoPersonal::create([
        'servidor_id'        => $permanente->id,
        'tipo_movimiento'    => TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION->value,
        'subtipo_movimiento' => 'servicio_militar',
        'estado'             => EstadoAccionPersonal::REGISTRADA,
        'codigo_registro'    => 'AP-2026-0970',
        'descripcion'        => 'Servicio militar',
        'fecha_efectiva'     => now()->subDay()->toDateString(),
        'fecha_inicio'       => now()->subDay()->toDateString(),
        'fecha_fin'          => now()->addMonths(3)->toDateString(),
    ]);

    $ausencias = $this->getJson('/api/v1/expediente/ausencias-temporales')->assertOk()->json('datos');

    expect(json_encode($ausencias, JSON_UNESCAPED_UNICODE))->toContain('Licencia sin Remuneración (Servicio militar)');
});

test('la licencia anterior a las causales dice que no la tiene', function () {
    $permanente = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $historica = MovimientoPersonal::create([
        'servidor_id'     => $permanente->id,
        'tipo_movimiento' => TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION->value,
        'estado'          => EstadoAccionPersonal::BORRADOR,
        'descripcion'     => 'Licencia de antes',
        'fecha_efectiva'  => '2026-01-10',
    ]);

    $this->getJson("/api/v1/expediente/movimientos/{$historica->id}")
        ->assertOk()
        ->assertJsonPath('datos.causal_etiqueta', 'No indicada (histórico)');
});

test('el formulario pide el período de la licencia', function () {
    $licencia = collect($this->getJson('/api/v1/expediente/acciones-personal/catalogo')->json('datos.clases'))
        ->firstWhere('codigo', 'licencia_sin_remuneracion');

    expect($licencia['pide_periodo'])->toBeTrue()
        ->and(array_column($licencia['causales'], 'codigo'))->toBe([
            'asuntos_particulares', 'estudios_posgrado', 'servicio_militar', 'reemplazo_dignatario',
            'candidatura', 'cuidado_hijos', 'segun_su_regimen',
        ]);
});
