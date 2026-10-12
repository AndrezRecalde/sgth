<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoColectivo;
use App\Models\Expediente\LoteAccion;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\ContratoServidorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 4.6 del diseño de Acciones de Personal (9.15; TH 14): el incremento de
| remuneración de los obreros en lote, por la tabla sectorial o el contrato
| colectivo. Un borrador por obrero, cada uno con su hoja de registro interno;
| desde el lote se suscriben y registran todos, y el que falla queda.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'asistente-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    $this->travelTo(Carbon::parse('2026-06-15 12:00'));

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    $this->actingAs($this->director, 'sanctum');

    $this->partida = PartidaPresupuestaria::create([
        'codigo' => '510106', 'descripcion' => 'Salarios unificados',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);

    $this->obras = UnidadAdministrativa::create(['codigo' => 'OOPP-L', 'nombre' => 'Obras Públicas L', 'nivel' => 1]);
    $this->vial = UnidadAdministrativa::create(['codigo' => 'VIAL-L', 'nombre' => 'Mantenimiento Vial L', 'nivel' => 1]);

    $puesto = fn (UnidadAdministrativa $u, string $regimen = 'codigo_trabajo') => Puesto::create([
        'unidad_administrativa_id'  => $u->id,
        'plazas'                    => 10,
        'regimen_laboral'           => $regimen,
        'partida_presupuestaria_id' => $this->partida->id,
        'activo'                    => true,
    ]);
    $this->enObras = $puesto($this->obras);
    $this->enVial = $puesto($this->vial);
    $this->deLaLosep = $puesto($this->obras, 'losep');

    $this->contador = 0;

    $this->vinculado = function (string $apellido, ?float $remuneracion, Puesto $puesto, TipoNombramiento $n = TipoNombramiento::CODIGO_TRABAJO): Servidor {
        $this->contador++;
        $servidor = Servidor::create([
            'cedula'          => str_pad((string) (8600000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Obrero',
            'apellido'        => $apellido,
            'regimen_laboral' => $n === TipoNombramiento::CODIGO_TRABAJO ? 'codigo_trabajo' : 'losep',
        ]);

        app(ContratoServidorService::class)->crear($servidor->id, [
            'tipo_nombramiento'         => $n->value,
            'numero_contrato'           => 'CT-'.$this->contador,
            'unidad_administrativa_id'  => $puesto->unidad_administrativa_id,
            'puesto_id'                 => $puesto->id,
            'fecha_inicio'              => '2021-06-01',
            'remuneracion'              => $remuneracion,
            'partida_presupuestaria_id' => $this->partida->id,
            'estado'                    => 'vigente',
        ]);

        return $servidor->fresh();
    };

    $this->datos = fn (array $extra = []) => [
        'modo'                        => 'porcentaje',
        'valor'                       => 5,
        'fecha_efectiva'              => '2026-06-01',
        'descripcion'                 => 'Incremento de la tabla sectorial 2026',
        'documento_origen'            => 'MDT-2026-015',
        'dictamen_presupuestario_ref' => 'DF-2026-310',
        ...$extra,
    ];
});

// ── Quiénes y cuánto ────────────────────────────────────────────

test('previsualiza a quiénes sube y a quiénes omite, y por qué', function () {
    ($this->vinculado)('Alava', 620, $this->enObras);
    ($this->vinculado)('Bravo', 700, $this->enVial);
    ($this->vinculado)('Cedeño', null, $this->enObras);
    $enTramite = ($this->vinculado)('Delgado', 650, $this->enObras);
    // De la LOSEP: no es obrero, no entra.
    ($this->vinculado)('Estupiñán', 1200, $this->deLaLosep, TipoNombramiento::PERMANENTE);

    $this->postJson("/api/v1/expediente/servidores/{$enTramite->id}/movimientos", [
        'clase' => 'incremento_remuneracion', 'descripcion' => 'Incremento individual', 'fecha_efectiva' => '2026-06-15',
        'remuneracion_propuesta' => 680, 'resolucion_numero' => 'ADENDA-1',
    ])->assertCreated();

    $vista = $this->postJson('/api/v1/expediente/lotes-accion/incremento-obreros/previsualizar', ($this->datos)())
        ->assertOk()
        ->json('datos');

    expect(collect($vista['incluidos'])->pluck('remuneracion_nueva', 'nombre')->all())
        ->toBe(['Alava Obrero' => 651, 'Bravo Obrero' => 735])
        ->and(collect($vista['omitidos'])->pluck('motivo', 'nombre')->all())->toBe([
            'Cedeño Obrero'  => 'Su contrato no tiene la remuneración registrada.',
            'Delgado Obrero' => 'Ya tiene un incremento en trámite.',
        ]);

    // Por unidad, y en monto fijo.
    $vial = $this->postJson('/api/v1/expediente/lotes-accion/incremento-obreros/previsualizar', ($this->datos)([
        'modo' => 'monto', 'valor' => 50, 'unidad_administrativa_id' => $this->vial->id,
    ]))->json('datos');

    expect($vial['incluidos'])->toHaveCount(1)
        ->and($vial['incluidos'][0]['remuneracion_nueva'])->toBe(750);
});

test('prepara un borrador por obrero, con su hoja RI y el documento de origen', function () {
    $alava = ($this->vinculado)('Alava', 620, $this->enObras);
    ($this->vinculado)('Bravo', 700, $this->enVial);

    $lote = $this->postJson('/api/v1/expediente/lotes-accion/incremento-obreros', ($this->datos)())
        ->assertCreated()
        ->assertJsonPath('datos.total', 2)
        ->assertJsonPath('datos.por_estado.borrador', 2)
        ->assertJsonPath('datos.documento_origen', 'MDT-2026-015')
        ->json('datos');

    $acto = MovimientoPersonal::where('servidor_id', $alava->id)->sole();

    expect($acto->tipo_movimiento)->toBe(TipoMovimientoPersonal::INCREMENTO_REMUNERACION)
        ->and($acto->instrumento->value)->toBe('documento_ct')
        ->and((float) $acto->remuneracion_propuesta)->toBe(651.0)
        ->and($acto->resolucion_numero)->toBe('MDT-2026-015')
        ->and($acto->dictamen_presupuestario_ref)->toBe('DF-2026-310')
        ->and($acto->lote_id)->toBe($lote['id']);

    // La bandeja los filtra por lote.
    $this->getJson("/api/v1/expediente/movimientos?lote_id={$lote['id']}")
        ->assertOk()
        ->assertJsonCount(2, 'datos.data');
});

test('sin nadie a quien subir no prepara nada, y el pactado exige un contrato vigente', function () {
    $this->postJson('/api/v1/expediente/lotes-accion/incremento-obreros', ($this->datos)())
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'Ningún obrero'));

    ($this->vinculado)('Alava', 620, $this->enObras);

    $this->postJson('/api/v1/expediente/lotes-accion/incremento-obreros', ($this->datos)(['por_contrato_colectivo' => true]))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'contrato colectivo vigente'));

    $contrato = ContratoColectivo::create([
        'numero' => 'CC-2025', 'vigencia_desde' => '2025-01-01', 'dictamen_finanzas_numero' => 'MEF-1',
        'dictamen_finanzas_fecha' => '2025-02-01', 'incremento_pactado_porcentaje' => 4,
    ]);

    $this->postJson('/api/v1/expediente/lotes-accion/incremento-obreros', ($this->datos)(['por_contrato_colectivo' => true]))
        ->assertCreated()
        ->assertJsonPath('datos.contrato_colectivo_id', $contrato->id);

    expect(LoteAccion::count())->toBe(1);
});

// ── Registrarlo ─────────────────────────────────────────────────

test('registrar el lote suscribe y registra cada uno; el que falla queda y se informa', function () {
    $alava = ($this->vinculado)('Alava', 620, $this->enObras);
    $bravo = ($this->vinculado)('Bravo', 700, $this->enVial);

    $id = $this->postJson('/api/v1/expediente/lotes-accion/incremento-obreros', ($this->datos)())->json('datos.id');

    // Bravo sube antes por su cuenta: su incremento del lote ya no le sube nada.
    $bravo->contratoVigente->update(['remuneracion' => 800]);

    $this->postJson("/api/v1/expediente/lotes-accion/{$id}/registrar")
        ->assertOk()
        ->assertJsonPath('datos.registrados', 1)
        ->assertJsonPath('datos.fallidos.0.nombre', 'Bravo Obrero')
        ->assertJsonPath('datos.lote.por_estado.registrada', 1);

    $deAlava = MovimientoPersonal::where('servidor_id', $alava->id)->sole();
    expect($deAlava->estado)->toBe(EstadoAccionPersonal::REGISTRADA)
        ->and($deAlava->codigo_registro)->toStartWith('RI-2026-')
        ->and((float) $alava->fresh()->contratoVigente->remuneracion)->toBe(651.0)
        ->and(MovimientoPersonal::where('servidor_id', $bravo->id)->sole()->estado)
        ->not->toBe(EstadoAccionPersonal::REGISTRADA);
});

test('descartar anula lo que sigue en trámite, con su motivo', function () {
    ($this->vinculado)('Alava', 620, $this->enObras);
    ($this->vinculado)('Bravo', 700, $this->enVial);

    $id = $this->postJson('/api/v1/expediente/lotes-accion/incremento-obreros', ($this->datos)())->json('datos.id');

    $this->postJson("/api/v1/expediente/lotes-accion/{$id}/descartar", ['motivo' => 'El porcentaje era otro.'])
        ->assertOk()
        ->assertJsonPath('datos.anulados', 2)
        ->assertJsonPath('datos.lote.por_estado.anulada', 2);

    expect(MovimientoPersonal::where('lote_id', $id)->first()->motivo_anulacion)
        ->toBe("Se descartó el lote #{$id}: El porcentaje era otro.");
});

test('el asistente prepara el lote, pero no lo registra', function () {
    ($this->vinculado)('Alava', 620, $this->enObras);

    $asistente = User::factory()->create();
    $asistente->assignRole('asistente-uath');
    $this->actingAs($asistente, 'sanctum');

    $id = $this->postJson('/api/v1/expediente/lotes-accion/incremento-obreros', ($this->datos)())
        ->assertCreated()
        ->json('datos.id');

    $this->postJson("/api/v1/expediente/lotes-accion/{$id}/registrar")->assertForbidden();
    // Por páginas desde la fase 7.2.
    $this->getJson('/api/v1/expediente/lotes-accion')->assertOk()->assertJsonPath('datos.data.0.id', $id);
});
