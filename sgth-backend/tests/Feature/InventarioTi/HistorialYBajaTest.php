<?php

use App\Enums\RegimenLaboral;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\MantenimientoBien;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\OrigenBien;
use App\Models\InventarioTi\TipoBien;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    BienInformatico::unguard();
    AsignacionBien::unguard();
    MantenimientoBien::unguard();
    TipoBien::unguard();
    Marca::unguard();
    OrigenBien::unguard();

    $unidad = unidadDePrueba(['nombre' => 'Direccion DTIC']);
    $puesto = puestoDePrueba($unidad);

    $this->servidor = Servidor::create([
        'cedula' => '0801234588', 'nombre' => 'Pedro', 'apellido' => 'Gomez',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);

    $this->admin = User::create([
        'email' => 'adminti@example.com', 'usuario_ti' => 'adminti',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->admin->assignRole(Role::firstOrCreate(
        ['name' => 'admin-ti', 'guard_name' => 'sanctum']
    ));

    $this->tipo   = TipoBien::create(['nombre' => 'Laptop', 'anios_vida_util' => 5]);
    $this->marca  = Marca::create(['nombre' => 'Lenovo']);
    $this->origen = OrigenBien::create([
        'tipo_origen'             => 'compra',
        'identificador_documento' => 'OC-2024-001',
        'entidad_origen'          => 'GADPE',
        'fecha_adquisicion'       => now()->subYears(2),
    ]);

    $this->bien = BienInformatico::create([
        'codigo_qr'            => 'GADPE-001-QR',
        'codigo_institucional' => 'GADPE-001',
        'tipo_bien_id'         => $this->tipo->id,
        'marca_id'             => $this->marca->id,
        'origen_bien_id'       => $this->origen->id,
        'modelo'               => 'ThinkPad T14',
        'numero_serie'         => 'SN-0001',
        'estado_operativo'     => 'activo',
        'condicion_fisica'     => 'bueno',
    ]);
});

test('el_historial_del_bien_ya_no_revienta_y_trae_su_vida', function () {
    // La ruta declaraba `historial()` y el controlador no lo tenía; el servicio
    // devolvía un molde vacío con el bien a null.
    AsignacionBien::create([
        'bien_informatico_id' => $this->bien->id,
        'servidor_id'         => $this->servidor->id,
        'fecha_asignacion'    => now()->subYear(),
        'estado'              => 'activa',
    ]);
    MantenimientoBien::create([
        'bien_informatico_id' => $this->bien->id,
        'tipo_mantenimiento'  => 'preventivo',
        'fecha_mantenimiento' => now()->subMonths(3),
        'tecnico_id'          => $this->admin->id,
        'descripcion'         => 'Limpieza y cambio de pasta térmica',
        'costo'               => 15.00,
    ]);

    $respuesta = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/inventario/bienes/{$this->bien->id}/historial")
        ->assertOk();

    expect($respuesta->json('datos.bien.codigo_institucional'))->toBe('GADPE-001');
    expect($respuesta->json('datos.asignaciones'))->toHaveCount(1);
    expect($respuesta->json('datos.asignaciones.0.servidor.cedula'))
        ->toBe('0801234588');
    expect($respuesta->json('datos.mantenimientos'))->toHaveCount(1);
});

test('el_historial_de_un_bien_que_no_existe_da_404', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/inventario/bienes/999999/historial')
        ->assertNotFound();
});

test('las_asignaciones_salen_de_la_mas_reciente_a_la_mas_antigua', function () {
    foreach ([3, 1, 2] as $haceAnios) {
        AsignacionBien::create([
            'bien_informatico_id' => $this->bien->id,
            'servidor_id'         => $this->servidor->id,
            'fecha_asignacion'    => now()->subYears($haceAnios),
            'estado'              => 'finalizada',
        ]);
    }

    $respuesta = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/inventario/bienes/{$this->bien->id}/historial")
        ->assertOk();

    $fechas = collect($respuesta->json('datos.asignaciones'))
        ->pluck('fecha_asignacion')->all();

    expect($fechas)->toBe(collect($fechas)->sortDesc()->values()->all());
});

test('dar_de_baja_un_bien_lo_marca_de_verdad', function () {
    // El servicio escribía `estado`, que no existe en la tabla ni en el
    // `fillable`: Eloquent lo descartaba en silencio, la respuesta decía que
    // la baja se había hecho y el bien seguía activo.
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/inventario/bajas', [
            'bien_informatico_id' => $this->bien->id,
            'motivo'              => 'Obsolescencia tecnológica',
        ])
        ->assertCreated();

    expect($this->bien->fresh()->estado_operativo)->toBe('dado_de_baja');
});

test('el_listado_de_bajas_encuentra_lo_dado_de_baja', function () {
    // Consultaba por `estado`, columna que esta tabla no tiene, y reventaba.
    BienInformatico::create([
        'codigo_qr' => 'GADPE-002-QR', 'codigo_institucional' => 'GADPE-002',
        'tipo_bien_id' => $this->tipo->id, 'marca_id' => $this->marca->id,
        'origen_bien_id' => $this->origen->id, 'numero_serie' => 'SN-0002',
        'estado_operativo' => 'dado_de_baja', 'condicion_fisica' => 'malo',
    ]);

    $respuesta = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/inventario/bajas')
        ->assertOk();

    // El activo del beforeEach no sale; el dado de baja, sí.
    expect($respuesta->json('datos'))->toHaveCount(1);
    expect($respuesta->json('datos.0.codigo_institucional'))->toBe('GADPE-002');
});

test('la_baja_no_promete_un_acta_que_no_existe', function () {
    // Antes devolvía una ruta de PDF y una referencia de SGD inventadas, y el
    // mensaje afirmaba que el acta estaba «generada y archivada».
    $respuesta = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/inventario/bajas', [
            'bien_informatico_id' => $this->bien->id,
            'motivo'              => 'Equipo robado',
        ])
        ->assertCreated();

    expect($respuesta->json('datos.acta_pendiente'))->toBeTrue();
    expect($respuesta->json('datos'))->not->toHaveKey('acta_pdf');
    expect($respuesta->json('datos'))->not->toHaveKey('sgd_referencia');
});
