<?php

use App\Enums\ConceptoFactura;
use App\Enums\EstadoViatico;
use App\Models\Geografia\Ciudad;
use App\Models\Catalogo\EntidadFinanciera;
use App\Models\Geografia\Provincia;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\CuentaBancariaServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Models\Viatico\Comision;
use App\Models\Viatico\DestinoViatico;
use App\Models\Viatico\TransporteViatico;
use App\Models\Viatico\Viatico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    
    // Crear roles necesarios
    Role::firstOrCreate(['name' => 'maxima-autoridad', 'guard_name' => 'sanctum']);
    
    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'GTIC',
        'nombre' => 'Gerencia',
        'estado' => true,
        'nivel' => 1
    ]);

    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id,
        'codigo' => 'P-01',
        'grupo_ocupacional' => 'NJS-1',
        'grado_rmu' => 1,
        'rmu' => 1000,
        'nivel' => 1,
        'denominacion' => 'Analista',
        'estado' => true
    ]);

    $this->servidor = Servidor::create([
        'user_id' => $this->user->id,
        'puesto_id' => $this->puesto->id,
        'cedula' => '1234567890',
        'nombre' => 'Juan',
        'apellido' => 'Perez',
        'correo_institucional' => 'juan@test.com',
        'regimen_laboral' => 'losep',
        'estado' => true
    ]);

    $this->comision = Comision::create([
        'unidad_administrativa_id' => $this->unidad->id,
        'motivo' => 'Test de comisión',
        'fecha_inicio' => now()->toDateString(),
        'fecha_fin' => now()->addDays(2)->toDateString(),
        'creado_por' => $this->user->id,
        'estado' => 'borrador'
    ]);

    $this->provincia = Provincia::create(['nombre' => 'Pichincha', 'codigo' => '17']);
    $this->ciudad = Ciudad::create(['provincia_id' => $this->provincia->id, 'nombre' => 'Quito']);

    $this->viatico = Viatico::create([
        'servidor_id' => $this->servidor->id,
        'comision_id' => $this->comision->id,
        'zona' => 'dentro_provincia',
        'tipo' => 'con_pernocte',
        'fecha_inicio' => now(),
        'fecha_fin' => now()->addDays(2),
        'estado' => EstadoViatico::SOLICITADO,
        'monto_calculado' => 100,
        'monto_anticipo' => 0,
        'justificacion' => 'Testing',
        'created_by' => $this->user->id
    ]);

    Sanctum::actingAs($this->user);
});

// GRUPO 1 — Destinos con validación condicional
it('destino_nacional_requiere_provincia_y_ciudad', function () {
    $response = $this->postJson("/api/v1/viaticos/{$this->viatico->id}/destinos", [
        'tipo_destino' => 'nacional',
        'fecha_llegada' => now()->toDateString(),
        'fecha_salida' => now()->addDays(1)->toDateString()
    ]);

    $response->assertStatus(422);
    $response->assertJsonStructure(['errores' => ['provincia_id', 'ciudad_id']]);
});

it('destino_internacional_requiere_pais', function () {
    $response = $this->postJson("/api/v1/viaticos/{$this->viatico->id}/destinos", [
        'tipo_destino' => 'internacional',
        'fecha_llegada' => now()->toDateString(),
        'fecha_salida' => now()->addDays(1)->toDateString()
    ]);

    $response->assertStatus(422);
    $response->assertJsonStructure(['errores' => ['pais']]);
});

it('destino_nacional_se_crea_correctamente', function () {
    $response = $this->postJson("/api/v1/viaticos/{$this->viatico->id}/destinos", [
        'tipo_destino' => 'nacional',
        'provincia_id' => $this->provincia->id,
        'ciudad_id' => $this->ciudad->id,
        'fecha_llegada' => now()->toDateString(),
        'fecha_salida' => now()->addDays(1)->toDateString()
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('destinos_viatico', [
        'viatico_id' => $this->viatico->id,
        'tipo_destino' => 'nacional',
        'provincia_id' => $this->provincia->id,
        'ciudad_id' => $this->ciudad->id
    ]);
});

// GRUPO 2 — Transportes
it('transporte_avion_genera_autorizacion_pendiente', function () {
    $response = $this->postJson("/api/v1/viaticos/{$this->viatico->id}/transportes", [
        'tipo' => 'avion',
        'empresa_o_aerolinea' => 'LATAM',
        'numero_ticket_o_billete' => '12345',
        'fecha_viaje' => now()->toDateString(),
        'monto' => 150.00
    ]);

    $response->assertCreated();
    
    // Verificar que el Observer generó la autorización
    $transporte = TransporteViatico::first();
    $this->assertDatabaseHas('autorizaciones_vuelo', [
        'transporte_viatico_id' => $transporte->id,
        'viatico_id' => $this->viatico->id,
        'estado' => 'pendiente'
    ]);
});

it('transporte_vehiculo_propio_requiere_placa', function () {
    $response = $this->postJson("/api/v1/viaticos/{$this->viatico->id}/transportes", [
        'tipo' => 'vehiculo_propio',
        'fecha_viaje' => now()->toDateString(),
        'monto' => 50.00
    ]);

    $response->assertStatus(422);
    $response->assertJsonStructure(['errores' => ['placa_vehiculo', 'kilometraje', 'valor_kilometro']]);
});

// GRUPO 3 — Código secuencial
it('codigo_viatico_se_genera_automaticamente', function () {
    expect($this->viatico->codigo_viatico)->not->toBeNull();
    expect($this->viatico->codigo_viatico)->toMatch('/^[A-Z]+-\d{4}-\d{4}$/');
});

// GRUPO 4 — Liquidación correcta
it('liquidacion_calcula_diferencia_correctamente', function () {
    // Configurar viatico
    $this->viatico->update([
        'estado' => EstadoViatico::PENDIENTE_LIQUIDACION,
        'monto_anticipo' => 200.00
    ]);

    $response = $this->postJson("/api/v1/viaticos/{$this->viatico->id}/liquidar", [
        'fecha_retorno' => now()->toDateString(),
        'facturas' => [
            [
                'concepto' => ConceptoFactura::ALIMENTACION->value,
                'numero_factura' => '001-001-000000001',
                'ruc_proveedor' => '1790016919001',
                'nombre_proveedor' => 'Restaurante Test',
                'monto' => 100.00
            ],
            [
                'concepto' => ConceptoFactura::HOSPEDAJE->value,
                'numero_factura' => '001-001-000000002',
                'ruc_proveedor' => '1790016919001',
                'nombre_proveedor' => 'Hotel Test',
                'monto' => 75.00
            ]
        ]
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('liquidaciones_viatico', [
        'viatico_id' => $this->viatico->id,
        'total_facturas' => 175.00,
        'diferencia_devolver' => 25.00
    ]);
});

it('liquidacion_diferencia_negativa_cuando_gasto_mayor', function () {
    // Configurar viatico
    $this->viatico->update([
        'estado' => EstadoViatico::PENDIENTE_LIQUIDACION,
        'monto_anticipo' => 200.00
    ]);

    $response = $this->postJson("/api/v1/viaticos/{$this->viatico->id}/liquidar", [
        'fecha_retorno' => now()->toDateString(),
        'facturas' => [
            [
                'concepto' => ConceptoFactura::HOSPEDAJE->value,
                'numero_factura' => '001-001-000000003',
                'ruc_proveedor' => '1790016919001',
                'nombre_proveedor' => 'Hotel Test 2',
                'monto' => 215.00
            ]
        ]
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('liquidaciones_viatico', [
        'viatico_id' => $this->viatico->id,
        'total_facturas' => 215.00,
        'diferencia_devolver' => -15.00
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

it('no_puede_solicitar_con_autorizacion_vuelo_pendiente', function () {
    // 1. Agregar destino válido
    DestinoViatico::create([
        'viatico_id' => $this->viatico->id,
        'tipo_destino' => 'nacional',
        'provincia_id' => $this->provincia->id,
        'ciudad_id' => $this->ciudad->id,
        'fecha_llegada' => now(),
        'fecha_salida' => now()->addDays(1),
        'orden' => 1
    ]);

    // 2. Agregar vuelo (genera autorización pendiente)
    $this->postJson("/api/v1/viaticos/{$this->viatico->id}/transportes", [
        'tipo' => 'avion',
        'empresa_o_aerolinea' => 'LATAM',
        'numero_ticket_o_billete' => '123',
        'fecha_viaje' => now()->toDateString(),
        'monto' => 100
    ])->assertCreated();

    // 3. Intentar solicitar
    $response = $this->postJson("/api/v1/viaticos/{$this->viatico->id}/solicitar");

    $response->assertStatus(422);
    $response->assertJson(['exito' => false]);
});
