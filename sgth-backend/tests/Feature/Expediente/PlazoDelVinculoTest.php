<?php

use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\ContratoServidorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * Las reglas del plazo de un vínculo, iguales al crearlo y al reprogramarlo.
 *
 * Hasta el 2026-10-03 cada camino tenía las suyas: un ocasional podía nacer o
 * quedarse sin término, y al aprobar un ingreso pasaba un término anterior al
 * inicio. El reemplazo está en AusenciaTemporalTest.
 */

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $uath = User::factory()->create();
    $uath->assignRole('admin-uath');
    $this->actingAs($uath, 'sanctum');

    $this->unidad = unidadDePrueba(['nombre' => 'Unidad Plazos']);
    $this->puesto = puestoDePrueba($this->unidad, 'Puesto Plazos', ['plazas' => 5]);
    $this->servidor = Servidor::forceCreate([
        'cedula' => '0803030301', 'nombre' => 'Raúl', 'apellido' => 'Plazo',
        'puesto_id' => $this->puesto->id, 'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => 'losep', 'estado' => true,
    ]);
    $this->contratos = app(ContratoServidorService::class);
    $this->datos = fn (array $extra) => [
        'tipo_nombramiento' => 'servicios_ocasionales',
        'unidad_administrativa_id' => $this->unidad->id, 'puesto_id' => $this->puesto->id,
        'fecha_inicio' => '2026-01-01', 'estado' => 'vigente', ...$extra,
    ];
});

test('un ocasional no nace sin término', function () {
    expect(fn () => $this->contratos->crear($this->servidor->id, ($this->datos)(['fecha_fin' => null])))
        ->toThrow(ReglaNegocioException::class, 'no puede quedarse sin fecha de vencimiento');
});

test('un vínculo no nace con el término antes del inicio', function () {
    expect(fn () => $this->contratos->crear($this->servidor->id, ($this->datos)(['fecha_fin' => '2025-12-31'])))
        ->toThrow(ReglaNegocioException::class, 'anterior a la fecha de inicio');
});

test('un ocasional no se reprograma a «sin plazo»; un nombramiento sí puede quedar indefinido', function () {
    $ocasional = $this->contratos->crear($this->servidor->id, ($this->datos)(['fecha_fin' => '2026-12-31']));

    expect(fn () => $this->contratos->reprogramarPlazo($ocasional, ['fecha_fin' => null, 'motivo' => 'Quitar plazo']))
        ->toThrow(ReglaNegocioException::class, 'Servicios Ocasionales');

    $permanente = ContratoServidor::create(($this->datos)([
        'servidor_id' => Servidor::forceCreate([
            'cedula' => '0803030302', 'nombre' => 'Eva', 'apellido' => 'Plazo',
            'regimen_laboral' => 'losep', 'estado' => true,
        ])->id,
        'tipo_nombramiento' => 'nombramiento_provisional', 'fecha_fin' => '2026-12-31',
    ]));
    $this->contratos->reprogramarPlazo($permanente, ['fecha_fin' => null, 'motivo' => 'Corrección: no lleva plazo']);
    expect($permanente->fresh()->fecha_fin)->toBeNull();
});
