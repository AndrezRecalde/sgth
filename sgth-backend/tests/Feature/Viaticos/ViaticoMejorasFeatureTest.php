<?php

use App\Enums\EstadoViatico;
use App\Models\Viatico\CategoriaFactura;
use App\Models\Geografia\Canton;
use App\Models\Catalogo\EntidadFinanciera;
use App\Models\Geografia\Provincia;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\CuentaBancariaServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Models\Viatico\Viatico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    
    // Crear roles necesarios
    Role::firstOrCreate(['name' => 'maxima-autoridad', 'guard_name' => 'sanctum']);
    
    $this->unidad = unidadDePrueba(['codigo' => 'GTIC', 'nombre' => 'Gerencia']);
    $this->puesto = puestoDePrueba($this->unidad);

    $this->servidor = Servidor::create([
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'cedula' => '1234567890',
        'nombre' => 'Juan',
        'apellido' => 'Perez',
        'regimen_laboral' => 'losep',
        'estado' => true
    ]);

    // La FK va de users a servidores: servidores.user_id ya no existe.
    $this->user->update(['servidor_id' => $this->servidor->id]);

    $this->provincia = Provincia::create(['nombre' => 'Pichincha', 'codigo' => '17']);
    $this->canton = Canton::create(['provincia_id' => $this->provincia->id, 'nombre' => 'Quito', 'codigo' => '1701']);

    // Sin comisión ni `tipo`: el refactor del 05/06/2026 disolvió la Comisión
    // dentro del propio viático (justificación, fechas de salida y llegada,
    // número de resolución) y los acompañantes pasaron a ViaticoServidor.
    // Estos tests nunca dependieron de ella — la creaban por inercia.
    $this->viatico = Viatico::create([
        'servidor_id' => $this->servidor->id,
        'zona' => 'dentro_provincia',
        'datetime_salida' => now(),
        'datetime_llegada' => now()->addDays(2),
        'estado' => EstadoViatico::SOLICITADO,
        'monto_calculado' => 100,
        'monto_anticipo' => 0,
        'justificacion' => 'Testing',
        'created_by' => $this->user->id
    ]);

    Sanctum::actingAs($this->user);
});

// GRUPO 3 — Código secuencial
it('codigo_viatico_se_genera_automaticamente', function () {
    expect($this->viatico->codigo_viatico)->not->toBeNull();
    // CodigoViaticoService lo arma con %05d: GTIC-2026-00001.
    expect($this->viatico->codigo_viatico)->toMatch('/^[A-Z]+-\d{4}-\d{5}$/');
});

/*
| GRUPO 4 — Liquidación bajo la regla 70/30 del MRL
|
| Estos dos tests probaban una fórmula que ya no existe: diferencia =
| anticipo - gastos, que podía dar negativo. Hoy ViaticoService::liquidar()
| aplica el 70/30: el anticipo cubre el 70% y se justifica solo con facturas
| del grupo `viatico` (hospedaje y alimentación); el transporte y los demás
| gastos suman al total pero no descargan el anticipo. Y la diferencia nunca
| baja de cero — si el servidor gastó de más, la institución le paga el
| resto, no le queda debiendo un negativo.
*/

/** Hospedaje y alimentación descargan el anticipo; el resto, no. */
function categoriaViatico(string $nombre = 'Hospedaje'): CategoriaFactura
{
    return CategoriaFactura::firstOrCreate(
        ['nombre' => $nombre],
        ['grupo' => 'viatico', 'codigo' => strtoupper(substr($nombre, 0, 4)), 'activo' => true],
    );
}

function categoriaFueraDelViatico(string $nombre = 'Transporte'): CategoriaFactura
{
    return CategoriaFactura::firstOrCreate(
        ['nombre' => $nombre],
        ['grupo' => 'transporte', 'codigo' => strtoupper(substr($nombre, 0, 4)), 'activo' => true],
    );
}

function facturaDe(CategoriaFactura $categoria, float $monto): array
{
    return [
        'categoria_factura_id' => $categoria->id,
        'nombre_proveedor'     => 'Proveedor Test',
        // Un comprobante de tipo factura exige RUC y número: el ticket y el
        // recibo no, y por eso el tipo viaja explícito.
        'tipo_comprobante'     => 'factura',
        'numero_factura'       => '001-001-'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'ruc_proveedor'        => '1790016919001',
        'monto'                => $monto,
    ];
}

it('no_hay_que_devolver_cuando_hospedaje_y_alimentacion_cubren_el_anticipo', function () {
    $this->viatico->update([
        'estado'          => EstadoViatico::PENDIENTE_LIQUIDACION,
        'monto_calculado' => 300.00,
        'monto_anticipo'  => 210.00, // el 70%
    ]);

    $response = $this->postJson("/api/v1/viaticos/{$this->viatico->id}/liquidar", [
        'fecha_retorno' => now()->toDateString(),
        'facturas' => [
            facturaDe(categoriaViatico('Hospedaje'), 150.00),
            facturaDe(categoriaViatico('Alimentación'), 80.00),
        ],
    ]);

    $response->assertOk();

    // 230 de hospedaje y alimentación superan los 210 anticipados.
    $this->assertDatabaseHas('liquidaciones_viatico', [
        'viatico_id'          => $this->viatico->id,
        'total_facturas'      => 230.00,
        'diferencia_devolver' => 0,
    ]);
});

it('devuelve_lo_no_justificado_y_el_transporte_no_descarga_el_anticipo', function () {
    $this->viatico->update([
        'estado'          => EstadoViatico::PENDIENTE_LIQUIDACION,
        'monto_calculado' => 300.00,
        'monto_anticipo'  => 210.00,
    ]);

    $response = $this->postJson("/api/v1/viaticos/{$this->viatico->id}/liquidar", [
        'fecha_retorno' => now()->toDateString(),
        'facturas' => [
            facturaDe(categoriaViatico('Hospedaje'), 60.00),
            // El pasaje suma al total pero no justifica el viático: es
            // justamente lo que distingue al grupo `viatico` del resto.
            facturaDe(categoriaFueraDelViatico('Transporte'), 120.00),
        ],
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('liquidaciones_viatico', [
        'viatico_id'          => $this->viatico->id,
        'total_facturas'      => 180.00,
        'diferencia_devolver' => 150.00, // 210 anticipados − 60 justificados
    ]);
});

// GRUPO 5 — Cuentas bancarias
it('solo_una_cuenta_principal_por_proposito', function () {
    $entidad = EntidadFinanciera::create([
        'nombre' => 'Banco Test',
        'tipo' => 'banco'
    ]);

    $cuenta1 = CuentaBancariaServidor::create([
        'servidor_id' => $this->servidor->id,
        'entidad_financiera_id' => $entidad->id,
        'tipo_cuenta' => 'ahorros',
        'numero_cuenta' => '111',
        'proposito' => 'ambos',
        'es_principal_viatico' => false
    ]);

    $cuenta2 = CuentaBancariaServidor::create([
        'servidor_id' => $this->servidor->id,
        'entidad_financiera_id' => $entidad->id,
        'tipo_cuenta' => 'ahorros',
        'numero_cuenta' => '222',
        'proposito' => 'viaticos',
        'es_principal_viatico' => false
    ]);

    // Set principal a cuenta 1
    $this->postJson("/api/v1/expediente/servidores/{$this->servidor->id}/cuentas-bancarias/{$cuenta1->id}/set-principal", [
        'proposito' => 'viatico'
    ])->assertOk();

    $this->assertDatabaseHas('cuentas_bancarias_servidor', ['id' => $cuenta1->id, 'es_principal_viatico' => true]);

    // Set principal a cuenta 2
    $this->postJson("/api/v1/expediente/servidores/{$this->servidor->id}/cuentas-bancarias/{$cuenta2->id}/set-principal", [
        'proposito' => 'viatico'
    ])->assertOk();

    // Validar unicidad
    $this->assertDatabaseHas('cuentas_bancarias_servidor', ['id' => $cuenta1->id, 'es_principal_viatico' => false]);
    $this->assertDatabaseHas('cuentas_bancarias_servidor', ['id' => $cuenta2->id, 'es_principal_viatico' => true]);
});

// GRUPO 6 — Validar para solicitar
it('no_puede_solicitar_sin_destinos', function () {
    // El viatico base no tiene destinos
    $response = $this->postJson("/api/v1/viaticos/{$this->viatico->id}/solicitar");

    $response->assertStatus(422);
    $response->assertJson(['exito' => false]);
});
