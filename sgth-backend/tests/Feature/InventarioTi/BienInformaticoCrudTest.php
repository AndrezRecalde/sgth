<?php

use App\Enums\RegimenLaboral;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\OrigenBien;
use App\Models\InventarioTi\TipoBien;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * `index`, `show`, `update` y `destroy` respondían éxito sin tocar la base:
 * `[]` con «Bienes listados», el eco del id con «Detalle de bien», y «Bien
 * actualizado» y «Bien dado de baja» sobre un registro intacto.
 *
 * Es el registro central del módulo: sin listado no hay inventario que
 * consultar, y un `update` que dice haber guardado es peor que uno que falla.
 */
beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    BienInformatico::unguard();
    AsignacionBien::unguard();
    TipoBien::unguard();
    Marca::unguard();
    OrigenBien::unguard();

    $this->admin = User::create([
        'email' => 'dtic2@example.com', 'usuario_ti' => 'dtic2',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->admin->assignRole(Role::firstOrCreate(
        ['name' => 'admin-ti', 'guard_name' => 'sanctum']
    ));

    $this->tipo   = TipoBien::create(['nombre' => 'Laptop', 'anios_vida_util' => 5]);
    $this->marca  = Marca::create(['nombre' => 'HP']);
    $this->origen = OrigenBien::create([
        'tipo_origen'             => 'compra',
        'identificador_documento' => 'OC-2024-090',
        'entidad_origen'          => 'GADPE',
        'fecha_adquisicion'       => '2024-01-15',
    ]);

    $this->bien = BienInformatico::create([
        'codigo_qr'            => 'GADPE-100-QR',
        'codigo_institucional' => 'GADPE-100',
        'tipo_bien_id'         => $this->tipo->id,
        'marca_id'             => $this->marca->id,
        'origen_bien_id'       => $this->origen->id,
        'modelo'               => 'ProBook 450',
        'numero_serie'         => 'SN-100',
        'estado_operativo'     => 'activo',
        'condicion_fisica'     => 'bueno',
    ]);

    $this->comoDtic = fn () => test()->actingAs($this->admin, 'sanctum');
});

/** Un bien más, con los campos que el test necesite distinguir. */
function otroBien(array $atributos = []): BienInformatico
{
    static $n = 200;
    $n++;

    return BienInformatico::create(array_merge([
        'codigo_qr'            => "GADPE-{$n}-QR",
        'codigo_institucional' => "GADPE-{$n}",
        'tipo_bien_id'         => test()->tipo->id,
        'marca_id'             => test()->marca->id,
        'origen_bien_id'       => test()->origen->id,
        'modelo'               => 'EliteDesk 800',
        'numero_serie'         => "SN-{$n}",
        'estado_operativo'     => 'activo',
        'condicion_fisica'     => 'bueno',
    ], $atributos));
}

// ── El listado ─────────────────────────────────────────────────────────────

test('el_listado_trae_los_bienes_y_no_una_lista_vacia', function () {
    otroBien();

    $respuesta = ($this->comoDtic)()->getJson('/api/v1/inventario/bienes');

    $respuesta->assertOk();
    expect($respuesta->json('datos'))->toHaveCount(2);
    expect($respuesta->json('meta.total'))->toBe(2);
});

test('el_listado_trae_el_tipo_y_la_marca_ya_resueltos', function () {
    // Sin esto la pantalla vería solo los ids y tendría que pedirlos uno a uno.
    $respuesta = ($this->comoDtic)()->getJson('/api/v1/inventario/bienes');

    expect($respuesta->json('datos.0.tipo.nombre'))->toBe('Laptop');
    expect($respuesta->json('datos.0.marca.nombre'))->toBe('HP');
});

test('el_listado_busca_por_codigo_serie_y_modelo', function () {
    otroBien(['modelo' => 'ThinkCentre M70']);

    $porModelo = ($this->comoDtic)()
        ->getJson('/api/v1/inventario/bienes?search=ThinkCentre');
    expect($porModelo->json('datos'))->toHaveCount(1);

    $porSerie = ($this->comoDtic)()
        ->getJson('/api/v1/inventario/bienes?search=SN-100');
    expect($porSerie->json('datos.0.codigo_institucional'))->toBe('GADPE-100');
});

test('el_listado_filtra_por_estado_operativo', function () {
    otroBien(['estado_operativo' => 'en_mantenimiento']);

    $respuesta = ($this->comoDtic)()
        ->getJson('/api/v1/inventario/bienes?estado_operativo=en_mantenimiento');

    expect($respuesta->json('datos'))->toHaveCount(1);
    expect($respuesta->json('datos.0.estado_operativo'))->toBe('en_mantenimiento');
});

test('el_listado_no_deja_pedir_el_inventario_entero_de_una_vez', function () {
    $respuesta = ($this->comoDtic)()
        ->getJson('/api/v1/inventario/bienes?per_page=5000');

    expect($respuesta->json('meta.por_pagina'))->toBe(100);
});

// ── El detalle ─────────────────────────────────────────────────────────────

test('el_detalle_devuelve_el_bien_y_no_el_eco_del_id', function () {
    // Devolvía `['id' => $id]`: el mismo número que se le pasaba.
    $respuesta = ($this->comoDtic)()
        ->getJson("/api/v1/inventario/bienes/{$this->bien->id}");

    $respuesta->assertOk();
    expect($respuesta->json('datos.numero_serie'))->toBe('SN-100');
    expect($respuesta->json('datos.tipo.nombre'))->toBe('Laptop');
});

test('el_detalle_de_un_bien_que_no_existe_da_404', function () {
    // Antes respondía 200 con el id inventado dentro.
    ($this->comoDtic)()
        ->getJson('/api/v1/inventario/bienes/999999')
        ->assertNotFound();
});

// ── El registro ────────────────────────────────────────────────────────────

test('registrar_sin_codigo_institucional_ya_no_pasa', function () {
    // Llegaba `$request->all()` al servicio, que componía el QR concatenando:
    // sin código el QR salía «-QR», y el segundo bien así chocaba contra el
    // índice único.
    ($this->comoDtic)()
        ->postJson('/api/v1/inventario/bienes', [
            'numero_serie'   => 'SN-999',
            'tipo_bien_id'   => $this->tipo->id,
            'marca_id'       => $this->marca->id,
            'origen_bien_id' => $this->origen->id,
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['codigo_institucional']]);
});

test('registrar_calcula_el_fin_de_vida_util_y_el_codigo_del_qr', function () {
    $respuesta = ($this->comoDtic)()
        ->postJson('/api/v1/inventario/bienes', [
            'codigo_institucional' => 'GADPE-500',
            'numero_serie'         => 'SN-500',
            'tipo_bien_id'         => $this->tipo->id,
            'marca_id'             => $this->marca->id,
            'origen_bien_id'       => $this->origen->id,
        ]);

    $respuesta->assertCreated();
    expect($respuesta->json('datos.codigo_qr'))->toBe('GADPE-500-QR');
    // Adquirido en enero de 2024, con cinco años de vida útil.
    expect($respuesta->json('datos.fecha_fin_vida_util'))->toContain('2029-01-15');
});

test('registrar_ya_no_anuncia_un_QR_que_el_sistema_no_produce', function () {
    // Decía «Bien registrado y QR generado». Lo que se genera es el código con
    // el que la auditoría busca el bien; la etiqueta física no.
    $respuesta = ($this->comoDtic)()
        ->postJson('/api/v1/inventario/bienes', [
            'codigo_institucional' => 'GADPE-501',
            'numero_serie'         => 'SN-501',
            'tipo_bien_id'         => $this->tipo->id,
            'marca_id'             => $this->marca->id,
            'origen_bien_id'       => $this->origen->id,
        ]);

    expect($respuesta->json('mensaje'))->not->toContain('QR generado');
});

// ── La edición ─────────────────────────────────────────────────────────────

test('actualizar_guarda_de_verdad', function () {
    // Respondía «Bien actualizado» y el registro no cambiaba.
    ($this->comoDtic)()
        ->putJson("/api/v1/inventario/bienes/{$this->bien->id}", [
            'condicion_fisica' => 'regular',
            'modelo'           => 'ProBook 450 G9',
        ])
        ->assertOk();

    expect($this->bien->fresh())
        ->condicion_fisica->toBe('regular')
        ->modelo->toBe('ProBook 450 G9');
});

test('cambiar_el_codigo_institucional_arrastra_el_del_qr', function () {
    // Si uno cambia y el otro no, la etiqueta pegada al equipo deja de
    // encontrarlo al escanearla.
    ($this->comoDtic)()
        ->putJson("/api/v1/inventario/bienes/{$this->bien->id}", [
            'codigo_institucional' => 'GADPE-101',
        ])
        ->assertOk();

    expect($this->bien->fresh()->codigo_qr)->toBe('GADPE-101-QR');
});

test('la_baja_no_puede_colarse_por_una_edicion', function () {
    // Dar de baja exige motivo y tiene su propio flujo: por aquí el bien
    // quedaría fuera de servicio sin constancia de por qué.
    ($this->comoDtic)()
        ->putJson("/api/v1/inventario/bienes/{$this->bien->id}", [
            'estado_operativo' => 'dado_de_baja',
        ])
        ->assertStatus(422);

    expect($this->bien->fresh()->estado_operativo)->toBe('activo');
});

test('actualizar_un_bien_que_no_existe_da_404', function () {
    ($this->comoDtic)()
        ->putJson('/api/v1/inventario/bienes/999999', ['condicion_fisica' => 'malo'])
        ->assertNotFound();
});

// ── El borrado ─────────────────────────────────────────────────────────────

test('borrar_un_bien_sin_historial_lo_retira_del_inventario', function () {
    $bien = otroBien();

    ($this->comoDtic)()
        ->deleteJson("/api/v1/inventario/bienes/{$bien->id}")
        ->assertOk();

    expect(BienInformatico::find($bien->id))->toBeNull();
    expect(BienInformatico::withTrashed()->find($bien->id))->not->toBeNull();
});

test('un_bien_con_historial_no_se_borra_y_se_explica_por_que', function () {
    // Borrarlo se llevaría por delante el rastro de quién lo tuvo. Lo que
    // corresponde con un equipo que salió de servicio es la baja.
    $unidad   = unidadDePrueba(['nombre' => 'Direccion TIC']);
    $servidor = Servidor::create([
        'cedula' => '0801234500', 'nombre' => 'Ana', 'apellido' => 'Loor',
        'puesto_id' => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYear(), 'estado' => true,
    ]);

    AsignacionBien::create([
        'bien_informatico_id' => $this->bien->id,
        'servidor_id'         => $servidor->id,
        'fecha_asignacion'    => now()->subMonths(2),
        'estado'              => 'activa',
    ]);

    $respuesta = ($this->comoDtic)()
        ->deleteJson("/api/v1/inventario/bienes/{$this->bien->id}");

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('baja');
    expect(BienInformatico::find($this->bien->id))->not->toBeNull();
});

test('borrar_ya_no_dice_que_dio_de_baja_lo_que_no_dio', function () {
    // «Bien dado de baja» nombraba otra cosa: la baja retira el bien del
    // servicio con su motivo y lo deja en el inventario.
    $respuesta = ($this->comoDtic)()
        ->deleteJson('/api/v1/inventario/bienes/' . otroBien()->id);

    expect($respuesta->json('mensaje'))->not->toContain('dado de baja');
});
