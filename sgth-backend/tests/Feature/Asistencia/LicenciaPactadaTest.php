<?php

/*
| Fase 5.3 del diseño de Acciones de Personal (9.15): las licencias que añade
| el contrato colectivo de los obreros. Una cláusula amplía los días de una
| causa de la ley o crea una causa que la ley no tiene, con su tope por año si
| lo pone. Vale para los obreros, con el contrato que rige con el dictamen de
| Finanzas; sin él, valen los días de la ley.
|
| Hoy es el lunes 12 de octubre de 2026.
*/

use App\Enums\CausaLicencia;
use App\Enums\Permiso;
use App\Enums\RegimenLaboral;
use App\Enums\TipoNombramiento;
use App\Models\Asistencia\Licencia;
use App\Models\Expediente\ClausulaDeLicencia;
use App\Models\Expediente\ContratoColectivo;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    Servidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);
    Storage::fake('local');
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de Obras']);
    $this->obrero = servidorPactado($this->unidad, '0802704181', TipoNombramiento::CODIGO_TRABAJO);
    $this->losep = servidorPactado($this->unidad, '0802704182', TipoNombramiento::PERMANENTE);

    $this->uath = User::create([
        'email' => 'uath.pactada@example.com', 'usuario_ti' => 'uathpactada',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->uath->assignRole('admin-uath');
    $this->actingAs($this->uath, 'sanctum');

    $this->contrato = ContratoColectivo::create([
        'numero' => 'CC-GADPE-2026', 'vigencia_desde' => '2026-01-01', 'vigencia_hasta' => '2027-12-31',
        'dictamen_finanzas_numero' => 'MEF-2026-0042', 'dictamen_finanzas_fecha' => '2026-01-20',
    ]);

    $this->clausula = fn (array $datos, ?ContratoColectivo $de = null) => ClausulaDeLicencia::create([
        'contrato_colectivo_id' => ($de ?? $this->contrato)->id, ...$datos,
    ]);

    $this->registrar = fn (Servidor $servidor, array $datos) => $this->post('/api/v1/asistencia/licencias', [
        'servidor_id' => $servidor->id,
        'archivo'     => UploadedFile::fake()->create('respaldo.pdf', 80, 'application/pdf'),
        ...$datos,
    ], ['Accept' => 'application/json']);
});

function servidorPactado($unidad, string $cedula, TipoNombramiento $nombramiento): Servidor
{
    $servidor = Servidor::create([
        'cedula' => $cedula, 'nombre' => 'Servidor', 'apellido' => 'Pactado'.substr($cedula, -3),
        'puesto_id' => puestoDePrueba($unidad)->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => $nombramiento->esCodigoTrabajo() ? RegimenLaboral::CODIGO_TRABAJO : RegimenLaboral::LOSEP,
        'estado' => true,
    ]);

    ContratoServidor::create([
        'servidor_id' => $servidor->id, 'tipo_nombramiento' => $nombramiento->value,
        'unidad_administrativa_id' => $unidad->id, 'puesto_id' => $servidor->puesto_id,
        'fecha_inicio' => '2020-01-02', 'estado' => 'vigente',
    ]);

    return $servidor;
}

// ── La que amplía una causa de la ley ───────────────────────────

test('al obrero, la cláusula amplía el tope de la causa de la ley', function () {
    $clausula = ($this->clausula)(['clausula' => 'Cláusula 18', 'causa' => 'calamidad_domestica', 'alcance' => 'pariente_cercano', 'dias' => 10]);
    $calamidad = ['causa' => 'calamidad_domestica', 'alcance' => 'pariente_cercano', 'fecha_inicio' => '2026-10-19'];

    ($this->registrar)($this->obrero, [...$calamidad, 'fecha_fin' => '2026-10-29'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'La licencia por Calamidad doméstica admite hasta 10 días (LOSEP, Art. 27 i; Contrato colectivo N.º CC-GADPE-2026, Cláusula 18), y las fechas suman 11.');

    ($this->registrar)($this->obrero, [...$calamidad, 'fecha_fin' => '2026-10-28'])
        ->assertCreated()
        ->assertJsonPath('datos.tope', 10)
        ->assertJsonPath('datos.clausula_de_licencia_id', $clausula->id)
        ->assertJsonPath('datos.clausula.contrato', 'CC-GADPE-2026')
        ->assertJsonPath('datos.base_legal', 'LOSEP, Art. 27 i; Contrato colectivo N.º CC-GADPE-2026, Cláusula 18');
});

test('sin el dictamen de Finanzas, o a quien no es obrero, valen los días de la ley', function () {
    ($this->clausula)(['clausula' => 'Cláusula 18', 'causa' => 'calamidad_domestica', 'alcance' => 'pariente_cercano', 'dias' => 10]);
    $calamidad = ['causa' => 'calamidad_domestica', 'alcance' => 'pariente_cercano', 'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-27'];

    // Al servidor de la LOSEP, el contrato colectivo no lo alcanza.
    ($this->registrar)($this->losep, $calamidad)->assertStatus(422)->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'admite hasta 8 días'));

    // Sin dictamen, el contrato se tiene por inexistente.
    $this->contrato->update(['dictamen_finanzas_numero' => null, 'dictamen_finanzas_fecha' => null]);
    ($this->registrar)($this->obrero, $calamidad)->assertStatus(422)->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'admite hasta 8 días'));

    $this->getJson("/api/v1/asistencia/licencias/causas?servidor_id={$this->obrero->id}")
        ->assertOk()
        ->assertJsonPath('meta.obrero', true)
        ->assertJsonPath('meta.contrato_colectivo', null)
        ->assertJsonPath('meta.aviso', fn ($a) => str_contains($a, 'no tiene el dictamen del Ministerio de Finanzas'));

    ($this->registrar)($this->obrero, [...$calamidad, 'fecha_fin' => '2026-10-26'])
        ->assertCreated()
        ->assertJsonPath('datos.clausula_de_licencia_id', null)
        ->assertJsonPath('datos.tope', 8);
});

// ── La causa pactada ────────────────────────────────────────────

test('la causa pactada se registra con su cláusula, y la aprueba Talento Humano', function () {
    $clausula = ($this->clausula)(['clausula' => 'Cláusula 20', 'nombre' => 'Fallecimiento de parientes de tercer grado', 'dias' => 2]);
    $pactada = ['causa' => 'pactada', 'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-20'];

    ($this->registrar)($this->obrero, $pactada)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['clausula_de_licencia_id'], 'errores');

    ($this->registrar)($this->losep, [...$pactada, 'clausula_de_licencia_id' => $clausula->id])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Las licencias del contrato colectivo son de los obreros: este servidor no está bajo el Código del Trabajo.');

    ($this->registrar)($this->obrero, [...$pactada, 'fecha_fin' => '2026-10-21', 'clausula_de_licencia_id' => $clausula->id])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'La licencia por Fallecimiento de parientes de tercer grado admite hasta 2 días (Contrato colectivo N.º CC-GADPE-2026, Cláusula 20), y las fechas suman 3.');

    ($this->registrar)($this->obrero, [...$pactada, 'clausula_de_licencia_id' => $clausula->id])
        ->assertCreated()
        ->assertJsonPath('datos.causa', 'pactada')
        ->assertJsonPath('datos.causa_etiqueta', 'Fallecimiento de parientes de tercer grado')
        ->assertJsonPath('datos.base_legal', 'Contrato colectivo N.º CC-GADPE-2026, Cláusula 20')
        ->assertJsonPath('datos.tope', 2);

    expect(CausaLicencia::PACTADA->permisoParaAprobar())->toBe(Permiso::APROBAR_PERMISO_SIRHA7);
});

test('la cláusula tiene que ser del contrato que rige en la fecha de inicio', function () {
    $anterior = ContratoColectivo::create([
        'numero' => 'CC-GADPE-2024', 'vigencia_desde' => '2024-01-01', 'vigencia_hasta' => '2025-12-31',
        'dictamen_finanzas_numero' => 'MEF-2024-0010', 'dictamen_finanzas_fecha' => '2024-01-15',
    ]);
    $vieja = ($this->clausula)(['clausula' => 'Cláusula 9', 'nombre' => 'Día del trabajador municipal', 'dias' => 1], $anterior);

    ($this->registrar)($this->obrero, [
        'causa' => 'pactada', 'clausula_de_licencia_id' => $vieja->id, 'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-19',
    ])->assertStatus(422)->assertJsonPath('mensaje', fn ($m) => str_starts_with($m, 'Elija la cláusula del contrato colectivo'));

    // Y la que amplía una causa no se elige: la pone el sistema.
    ($this->registrar)($this->obrero, [
        'causa' => 'pactada', 'clausula_de_licencia_id' => ($this->clausula)([
            'clausula' => 'Cláusula 18', 'causa' => 'matrimonio', 'dias' => 5,
        ])->id, 'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-19',
    ])->assertStatus(422)->assertJsonPath('mensaje', fn ($m) => str_starts_with($m, 'Elija la cláusula del contrato colectivo'));
});

// ── El tope por año ─────────────────────────────────────────────

test('la causa pactada respeta su tope por año, sin contar las anuladas', function () {
    $clausula = ($this->clausula)(['clausula' => 'Cláusula 21', 'nombre' => 'Cumpleaños', 'dias' => 1, 'dias_por_anio' => 1]);
    $cumple = fn (string $dia) => ['causa' => 'pactada', 'clausula_de_licencia_id' => $clausula->id, 'fecha_inicio' => $dia, 'fecha_fin' => $dia];

    $primera = ($this->registrar)($this->obrero, $cumple('2026-10-20'))->assertCreated()->json('datos.id');

    ($this->registrar)($this->obrero, $cumple('2026-11-20'))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'La Cláusula 21 del contrato colectivo da hasta 1 día al año por Cumpleaños: en 2026 ya se tomó 1 día, y con esta licencia serían 2 días.');

    // El año siguiente vuelve a contar.
    ($this->registrar)($this->obrero, $cumple('2027-01-15'))->assertCreated();

    // La anulada ya no cuenta.
    Licencia::whereKey($primera)->update(['anulado_en' => now(), 'motivo_anulacion' => 'Registrada por error.']);
    ($this->registrar)($this->obrero, $cumple('2026-11-20'))->assertCreated();
});

test('la que amplía una causa, pasado su tope por año, vuelve a los días de la ley', function () {
    $clausula = ($this->clausula)([
        'clausula' => 'Cláusula 18', 'causa' => 'calamidad_domestica', 'alcance' => 'pariente_cercano', 'dias' => 10, 'dias_por_anio' => 15,
    ]);
    $calamidad = fn (string $desde, string $hasta) => [
        'causa' => 'calamidad_domestica', 'alcance' => 'pariente_cercano', 'fecha_inicio' => $desde, 'fecha_fin' => $hasta,
    ];

    ($this->registrar)($this->obrero, $calamidad('2026-10-19', '2026-10-28'))
        ->assertCreated()->assertJsonPath('datos.clausula_de_licencia_id', $clausula->id);

    // Diez más cinco son quince: la segunda de ocho ya no cabe en la cláusula,
    // pero la ley da ocho por cada calamidad.
    ($this->registrar)($this->obrero, $calamidad('2026-11-09', '2026-11-17'))
        ->assertStatus(422)->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'admite hasta 8 días (LOSEP, Art. 27 i)'));

    ($this->registrar)($this->obrero, $calamidad('2026-11-09', '2026-11-16'))
        ->assertCreated()
        ->assertJsonPath('datos.clausula_de_licencia_id', null)
        ->assertJsonPath('datos.tope', 8);
});

// ── El catálogo del formulario ──────────────────────────────────

test('el catálogo del obrero dice lo que le da su contrato', function () {
    ($this->clausula)(['clausula' => 'Cláusula 18', 'causa' => 'calamidad_domestica', 'alcance' => 'pariente_cercano', 'dias' => 10]);
    ($this->clausula)(['clausula' => 'Cláusula 19', 'causa' => 'matrimonio', 'dias' => 5, 'dias_por_anio' => 5]);
    $nueva = ($this->clausula)(['clausula' => 'Cláusula 20', 'nombre' => 'Fallecimiento de parientes de tercer grado', 'dias' => 2]);

    $respuesta = $this->getJson("/api/v1/asistencia/licencias/causas?servidor_id={$this->obrero->id}&fecha=2026-10-19")
        ->assertOk()
        ->assertJsonPath('meta.obrero', true)
        ->assertJsonPath('meta.contrato_colectivo.numero', 'CC-GADPE-2026')
        ->assertJsonPath('meta.aviso', null);

    $causas = collect($respuesta->json('datos'))->keyBy(fn ($c) => $c['codigo'].($c['clausula_id'] ? ":{$c['clausula_id']}" : ''));

    expect($causas['matrimonio']['tope'])->toBe(5)
        ->and($causas['matrimonio']['tope_anual'])->toBe(5)
        ->and($causas['matrimonio']['base_legal'])->toBe('LOSEP, Art. 27 j; Contrato colectivo N.º CC-GADPE-2026, Cláusula 19')
        ->and(collect($causas['calamidad_domestica']['alcances'])->firstWhere('codigo', 'pariente_cercano')['tope'])->toBe(10)
        ->and(collect($causas['calamidad_domestica']['alcances'])->firstWhere('codigo', 'otro_pariente')['tope'])->toBe(3)
        ->and($causas["pactada:{$nueva->id}"]['etiqueta'])->toBe('Fallecimiento de parientes de tercer grado')
        ->and($causas["pactada:{$nueva->id}"]['tope'])->toBe(2)
        ->and($causas->has('pactada'))->toBeFalse();

    // Al de la LOSEP, la ley sola.
    $losep = collect($this->getJson("/api/v1/asistencia/licencias/causas?servidor_id={$this->losep->id}")->assertOk()->json('datos'));
    expect($losep->firstWhere('codigo', 'matrimonio')['tope'])->toBe(3)
        ->and($losep->where('codigo', 'pactada'))->toBeEmpty();

    // Sin servidor, para los filtros: la pactada como una causa más, sin cláusula.
    $todas = collect($this->getJson('/api/v1/asistencia/licencias/causas')->assertOk()->json('datos'));
    expect($todas->where('codigo', 'pactada')->pluck('clausula_id')->all())->toBe([null]);
});
