<?php

use App\Enums\TipoNombramiento;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Estructura\PlantillaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * El tablero de plantilla tiene que contar lo mismo que el validador que
 * impide contratar sobre un puesto lleno. Si contara distinto, mostraría
 * vacantes que después no se pueden llenar —o al revés—, y sería peor que no
 * tener tablero.
 */
beforeEach(function () {
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de Prueba']);
    $this->puesto = puestoDePrueba($this->unidad, 'Analista', ['plazas' => 3]);

    $this->servicio = app(PlantillaService::class);

    $this->contratar = function (TipoNombramiento $nombramiento, string $cedula, array $extra = []) {
        $servidor = Servidor::create([
            'cedula' => $cedula, 'nombre' => 'Prueba', 'apellido' => 'Plantilla',
            'estado' => true,
        ]);

        return ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => $nombramiento->value,
            'unidad_administrativa_id' => $this->puesto->unidad_administrativa_id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => '2026-01-01',
            'estado'                   => 'vigente',
            'origen'                   => 'accion_personal',
            ...$extra,
        ]);
    };
});

test('sin nadie contratado, todas las plazas están vacantes', function () {
    $resumen = $this->servicio->resumen();

    expect($resumen['plazas']['total'])->toBe(3)
        ->and($resumen['plazas']['ocupadas'])->toBe(0)
        ->and($resumen['plazas']['vacantes'])->toBe(3)
        ->and($resumen['plazas']['ocupacion'])->toBe(0.0);
});

test('un nombramiento ocupa plaza y un ocasional no', function () {
    ($this->contratar)(TipoNombramiento::PERMANENTE, '0800000401');
    ($this->contratar)(TipoNombramiento::SERVICIOS_OCASIONALES, '0800000402');

    $resumen = $this->servicio->resumen();

    expect($resumen['plazas']['ocupadas'])->toBe(1)
        ->and($resumen['plazas']['vacantes'])->toBe(2)
        // El ocasional existe y se cuenta: no ocupa plaza, pero es personal.
        ->and($resumen['sin_plaza']['servicios_ocasionales'])->toBe(1)
        ->and($resumen['sin_plaza']['total_vigentes'])->toBe(2)
        ->and($resumen['sin_plaza']['porcentaje_ocasionales'])->toBe(50.0);
});

test('el tablero cuenta igual que el modelo', function () {
    // `PlantillaService` cuenta en SQL para no disparar una consulta por
    // puesto, así que replica el criterio de `scopeQueOcupanPlaza()`. Este test
    // ata las dos formas: si una cambia sin la otra, falla.
    ($this->contratar)(TipoNombramiento::PERMANENTE, '0800000403');
    ($this->contratar)(TipoNombramiento::SERVICIOS_PROFESIONALES, '0800000404', ['fecha_fin' => '2026-12-31']);
    ($this->contratar)(TipoNombramiento::CODIGO_TRABAJO, '0800000405');

    $resumen = $this->servicio->resumen();

    expect($resumen['plazas']['ocupadas'])->toBe($this->puesto->fresh()->plazasOcupadas())
        ->and($resumen['plazas']['vacantes'])->toBe($this->puesto->fresh()->plazasDisponibles());
});

test('un reemplazo no ocupa plaza: la sigue teniendo el titular', function () {
    $titular = ($this->contratar)(TipoNombramiento::PERMANENTE, '0800000406');

    // La ausencia del titular, que es lo que el suplente cubre.
    $ausencia = MovimientoPersonal::create([
        'servidor_id'     => $titular->servidor_id,
        'tipo_movimiento' => 'cambio_administrativo',
        'descripcion'     => 'Comisión de servicios',
        'fecha_efectiva'  => '2026-02-01',
    ]);

    $suplente = ($this->contratar)(TipoNombramiento::PERMANENTE, '0800000408');
    $suplente->update(['cubre_movimiento_id' => $ausencia->id]);

    // Dos contratos permanentes vigentes sobre el mismo puesto, pero solo uno
    // consume plaza: la del titular, que sigue siendo suya.
    expect($this->servicio->resumen()['plazas']['ocupadas'])->toBe(1);
});

test('las modalidades sin nadie salen en cero, no desaparecen', function () {
    ($this->contratar)(TipoNombramiento::PERMANENTE, '0800000409');

    $modalidades = $this->servicio->resumen()['por_modalidad'];

    expect($modalidades)->toHaveCount(count(TipoNombramiento::cases()));

    $provisional = collect($modalidades)
        ->firstWhere('tipo_nombramiento', TipoNombramiento::PROVISIONAL->value);

    // «Ningún nombramiento provisional» es un dato; una fila ausente se lee
    // como un olvido del informe.
    expect($provisional['total'])->toBe(0)
        ->and($provisional['ocupa_plaza'])->toBeTrue();
});

test('el desglose por unidad ordena por vacantes', function () {
    $otra = unidadDePrueba(['nombre' => 'Otra Dirección']);
    puestoDePrueba($otra, 'Asistente', ['plazas' => 10]);

    ($this->contratar)(TipoNombramiento::PERMANENTE, '0800000410');

    $unidades = $this->servicio->resumen()['por_unidad'];

    expect($unidades[0]['unidad'])->toBe('Otra Dirección')
        ->and($unidades[0]['vacantes'])->toBe(10)
        ->and($unidades[1]['vacantes'])->toBe(2);
});

test('el endpoint responde el resumen', function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $usuario = User::factory()->create();
    $usuario->assignRole('admin-uath');

    $this->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/estructura/plantilla')
        ->assertOk()
        ->assertJsonPath('exito', true)
        ->assertJsonStructure([
            'datos' => [
                'plazas'      => ['total', 'ocupadas', 'vacantes', 'ocupacion'],
                'por_regimen',
                'por_modalidad',
                'por_unidad',
                'sin_plaza'   => ['servicios_ocasionales', 'servicios_profesionales', 'porcentaje_ocasionales'],
            ],
        ]);
});
