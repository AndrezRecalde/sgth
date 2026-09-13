<?php

/*
| La revisión de Financiero sobre cada comprobante de la liquidación.
|
| Decidido con el usuario:
| - cada comprobante se acepta u observa con motivo; solo se contabiliza con
|   todos aceptados;
| - al volver a guardar los comprobantes, los que no cambiaron conservan su
|   revisión;
| - RUC, fecha y duplicados solo avisan: no impiden guardar.
*/

use App\Enums\EstadoViatico;
use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Models\Viatico\CategoriaFactura;
use App\Models\Viatico\FacturaViatico;
use App\Models\Viatico\LiquidacionViatico;
use App\Models\Viatico\Viatico;
use App\Support\RucEcuador;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba();
    $cedula = 800009000;
    $this->servidor = function () use ($unidad, &$cedula) {
        return Servidor::create([
            'cedula'                   => '0'.(++$cedula),
            'nombre'                   => 'Raúl',
            'apellido'                 => 'Recibo',
            'puesto_id'                => puestoDePrueba($unidad)->id,
            'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral'          => RegimenLaboral::LOSEP,
            'estado'                   => true,
        ]);
    };
    $this->usuario = function (string $rol) {
        $u = User::factory()->create(['servidor_id' => ($this->servidor)()->id]);
        $u->assignRole($rol);

        return $u;
    };

    $this->titular = ($this->usuario)('servidor');
    $this->financiero = ($this->usuario)('financiero');

    $this->hospedaje = CategoriaFactura::create(['nombre' => 'Hospedaje', 'codigo' => 'HOSP', 'grupo' => 'viatico', 'activo' => true]);

    $this->viatico = function (EstadoViatico $estado, ?User $de = null) {
        $viatico = Viatico::create([
            'servidor_id'        => ($de ?? $this->titular)->servidor_id,
            'zona'               => 'dentro_provincia',
            'datetime_salida'    => '2026-10-05 08:00',
            'datetime_llegada'   => '2026-10-07 18:00',
            'total_dias'         => 3,
            'justificacion'      => 'Supervisión de obras',
            'estado'             => $estado,
            'monto_calculado'    => 240,
            'monto_anticipo'     => 0,
            'modalidad_anticipo' => 'total',
        ]);
        LiquidacionViatico::create([
            'viatico_id' => $viatico->id, 'total_facturas' => 0,
            'diferencia_devolver' => 0, 'fecha_liquidacion' => now()->toDateString(),
        ]);

        return $viatico;
    };

    $this->comprobante = fn (Viatico $v, array $extra = []) => FacturaViatico::create(array_merge([
        'liquidacion_viatico_id' => $v->liquidacion()->firstOrFail()->id,
        'categoria_factura_id'   => $this->hospedaje->id,
        'tipo_comprobante'       => 'factura',
        'numero_factura'         => '001-001-000000123',
        'ruc_proveedor'          => '1790016919001',
        'nombre_proveedor'       => 'Hotel Quinindé',
        'fecha_factura'          => '2026-10-06',
        'monto'                  => 80,
    ], $extra));

    $this->revisar = fn (User $quien, Viatico $v, FacturaViatico $f, array $datos) =>
        $this->actingAs($quien, 'sanctum')->postJson("/api/v1/viaticos/{$v->id}/liquidacion/facturas/{$f->id}/revision", $datos);
});

// ── Revisar ──────────────────────────────────────────────────────────

it('Financiero acepta u observa cada comprobante, y observar pide motivo', function () {
    $viatico = ($this->viatico)(EstadoViatico::LIQUIDADO);
    $bien = ($this->comprobante)($viatico);
    $mal = ($this->comprobante)($viatico, ['numero_factura' => '001-001-000000999']);

    ($this->revisar)($this->financiero, $viatico, $bien, ['decision' => 'aceptada'])
        ->assertOk()->assertJsonPath('datos.estado_revision', 'aceptada');

    ($this->revisar)($this->financiero, $viatico, $mal, ['decision' => 'observada'])
        ->assertStatus(422)->assertJsonStructure(['errores' => ['observacion']]);

    ($this->revisar)($this->financiero, $viatico, $mal, ['decision' => 'observada', 'observacion' => 'La factura está a nombre de otra persona'])
        ->assertOk();

    expect($bien->fresh())
        ->estado_revision->toBe('aceptada')
        ->revisado_por->toBe($this->financiero->id)
        ->and($mal->fresh())
        ->estado_revision->toBe('observada')
        ->observacion_revision->toBe('La factura está a nombre de otra persona');
});

it('revisa quien revisa liquidaciones, con la liquidación presentada y no la propia', function () {
    $pendiente = ($this->viatico)(EstadoViatico::PENDIENTE_LIQUIDACION);
    $propio = ($this->viatico)(EstadoViatico::LIQUIDADO, $this->financiero);
    $ajeno = ($this->viatico)(EstadoViatico::LIQUIDADO);

    ($this->revisar)($this->titular, $ajeno, ($this->comprobante)($ajeno), ['decision' => 'aceptada'])->assertForbidden();
    ($this->revisar)($this->financiero, $pendiente, ($this->comprobante)($pendiente), ['decision' => 'aceptada'])->assertStatus(422);
    ($this->revisar)($this->financiero, $propio, ($this->comprobante)($propio), ['decision' => 'aceptada'])->assertStatus(422);

    // Un comprobante de otro viático no se revisa por esta ruta.
    ($this->revisar)($this->financiero, $ajeno, ($this->comprobante)($pendiente, ['numero_factura' => 'X']), ['decision' => 'aceptada'])
        ->assertNotFound();
});

// ── Contabilizar ─────────────────────────────────────────────────────

it('solo se contabiliza con todos los comprobantes aceptados', function () {
    $viatico = ($this->viatico)(EstadoViatico::LIQUIDADO);
    $uno = ($this->comprobante)($viatico);
    $dos = ($this->comprobante)($viatico, ['numero_factura' => '001-001-000000456']);
    $contabilizar = fn () => $this->actingAs($this->financiero, 'sanctum')->postJson("/api/v1/viaticos/{$viatico->id}/contabilizar");

    $contabilizar()->assertStatus(422)->assertJsonPath('mensaje', 'Faltan 2 comprobante(s) por revisar antes de contabilizar.');

    $uno->update(['estado_revision' => 'aceptada']);
    $dos->update(['estado_revision' => 'observada', 'observacion_revision' => 'Ilegible']);
    $contabilizar()->assertStatus(422)->assertJsonPath('mensaje', 'Hay 1 comprobante(s) observado(s): devuelva la liquidación a corrección.');

    $dos->update(['estado_revision' => 'aceptada']);
    $contabilizar()->assertOk();
    expect($viatico->fresh()->estado)->toBe(EstadoViatico::CONTABILIZADO);
});

it('sin comprobantes no se contabiliza', function () {
    $viatico = ($this->viatico)(EstadoViatico::LIQUIDADO);

    $this->actingAs($this->financiero, 'sanctum')
        ->postJson("/api/v1/viaticos/{$viatico->id}/contabilizar")
        ->assertStatus(422);
});

// ── Reenvío tras la corrección ───────────────────────────────────────

it('al volver a guardar, lo que no cambió conserva su revisión y lo corregido vuelve a pendiente', function () {
    $viatico = ($this->viatico)(EstadoViatico::PENDIENTE_LIQUIDACION);
    ($this->comprobante)($viatico, ['estado_revision' => 'aceptada', 'revisado_por' => $this->financiero->id]);
    ($this->comprobante)($viatico, [
        'numero_factura' => '001-001-000000456', 'monto' => 30,
        'estado_revision' => 'observada', 'observacion_revision' => 'El monto no coincide',
    ]);

    $payload = fn (string $numero, float $monto) => [
        'categoria_factura_id' => $this->hospedaje->id, 'tipo_comprobante' => 'factura',
        'numero_factura' => $numero, 'ruc_proveedor' => '1790016919001',
        'nombre_proveedor' => 'Hotel Quinindé', 'fecha_factura' => '2026-10-06', 'monto' => $monto,
    ];

    $this->actingAs($this->titular, 'sanctum')
        ->postJson("/api/v1/viaticos/{$viatico->id}/liquidacion/facturas", ['facturas' => [
            $payload('001-001-000000123', 80),   // igual: sigue aceptado
            $payload('001-001-000000456', 35),   // corregido: vuelve a pendiente
            $payload('001-001-000000789', 20),   // nuevo: pendiente
        ]])
        ->assertOk();

    $estados = FacturaViatico::orderBy('numero_factura')->get()
        ->mapWithKeys(fn ($f) => [$f->numero_factura => [$f->estado_revision, $f->revisado_por]]);

    expect($estados->all())->toBe([
        '001-001-000000123' => ['aceptada', $this->financiero->id],
        '001-001-000000456' => ['pendiente', null],
        '001-001-000000789' => ['pendiente', null],
    ]);
});

// ── Controles automáticos ────────────────────────────────────────────

it('cada comprobante trae sus alertas, sin impedir guardarlo', function () {
    $viatico = ($this->viatico)(EstadoViatico::LIQUIDADO);
    $otro = ($this->viatico)(EstadoViatico::CONTABILIZADO);

    ($this->comprobante)($otro, ['numero_factura' => '001-001-000000777']);
    ($this->comprobante)($viatico);                                                    // limpio
    ($this->comprobante)($viatico, ['numero_factura' => 'A', 'ruc_proveedor' => '1234567890123']);
    ($this->comprobante)($viatico, ['numero_factura' => 'B', 'fecha_factura' => '2026-10-09']);
    ($this->comprobante)($viatico, ['numero_factura' => '001-001-000000777']);         // ya presentada
    ($this->comprobante)($viatico, ['numero_factura' => null, 'tipo_comprobante' => 'ticket', 'ruc_proveedor' => null, 'numero_ticket' => 'T-1']);

    $facturas = collect(
        $this->actingAs($this->financiero, 'sanctum')->getJson("/api/v1/viaticos/{$viatico->id}")->assertOk()->json('datos.liquidacion.detalles_factura')
    )->keyBy(fn ($f) => $f['numero_factura'] ?? $f['numero_ticket']);

    $codigos = fn (string $clave) => collect($facturas[$clave]['alertas'])->pluck('codigo')->all();

    expect($codigos('001-001-000000123'))->toBe([])
        ->and($codigos('A'))->toBe(['ruc'])
        ->and($codigos('B'))->toBe(['fecha'])
        ->and($codigos('001-001-000000777'))->toBe(['duplicado'])
        ->and($facturas['001-001-000000777']['alertas'][0]['mensaje'])->toContain($otro->codigo_viatico)
        ->and($codigos('T-1'))->toBe([]);
});

it('valida el RUC ecuatoriano por tipo de contribuyente', function (string $ruc, bool $valido) {
    expect(RucEcuador::esValido($ruc))->toBe($valido);
})->with([
    'sociedad privada'        => ['1790016919001', true],
    'entidad pública'         => ['1760001550001', true],
    'persona natural'         => ['1710034065001', true],
    'dígito verificador malo' => ['1790016918001', false],
    'provincia inexistente'   => ['9990016919001', false],
    'establecimiento 000'     => ['1710034065000', false],
    'longitud'                => ['179001691900', false],
]);
