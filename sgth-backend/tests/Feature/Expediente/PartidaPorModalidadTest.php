<?php

namespace Tests\Feature\Expediente;

use App\Enums\PartidaPorModalidad;
use App\Enums\TipoNombramiento;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\PartidaPresupuestariaSeeder::class);

    $this->unidad = unidadDePrueba();
    $this->puesto = puestoDePrueba($this->unidad);

    $this->contador = 0;

    $this->contratoCon = function (TipoNombramiento $tipo, ?int $partidaId = null): ContratoServidor {
        $this->contador++;

        $servidor = Servidor::create([
            'cedula'   => str_pad((string) (9000000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'   => 'Servidor',
            'apellido' => 'Partida'.$this->contador,
            'fecha_ingreso_institucion' => '2020-01-01',
        ]);

        return ContratoServidor::create([
            'servidor_id'               => $servidor->id,
            'tipo_nombramiento'         => $tipo->value,
            'unidad_administrativa_id'  => $this->unidad->id,
            'puesto_id'                 => $this->puesto->id,
            'fecha_inicio'              => '2020-01-01',
            'fecha_fin'                 => $tipo === TipoNombramiento::SERVICIOS_PROFESIONALES
                ? '2026-12-31' : null,
            'estado'                    => 'vigente',
            'partida_presupuestaria_id' => $partidaId,
        ]);
    };
});

// ── La correspondencia que dio Financiera ───────────────────────

test('cada modalidad sugiere la partida que le corresponde', function (string $modalidad, string $codigo) {
    $sugerida = PartidaPorModalidad::sugerirPara(TipoNombramiento::from($modalidad));

    expect($sugerida?->codigo)->toBe($codigo);
})->with([
    'empleado de carrera'     => ['nombramiento_permanente', '510105'],
    'nombramiento provisional' => ['nombramiento_provisional', '510105'],
    'libre nombramiento'      => ['libre_nombramiento_remocion', '510105'],
    'elección popular'        => ['eleccion_popular', '510105'],
    'contrato ocasional'      => ['servicios_ocasionales', '510510'],
    'obrero'                  => ['codigo_trabajo', '710106'],
    'contrato profesional'    => ['servicios_profesionales', '530606'],
]);

/**
 * El clasificador separa el mismo concepto en gasto corriente y de inversión.
 * Cuál aplica depende del fondo que financia el contrato — un dato que no vive
 * en el expediente —, así que el sistema ofrece las dos y no elige por nadie.
 */
test('las modalidades con doble imputación exigen que alguien elija', function () {
    expect(PartidaPorModalidad::exigeEleccion(TipoNombramiento::SERVICIOS_PROFESIONALES))->toBeTrue()
        ->and(PartidaPorModalidad::codigosPara(TipoNombramiento::SERVICIOS_PROFESIONALES))
        ->toBe(['530606', '730606'])
        ->and(PartidaPorModalidad::exigeEleccion(TipoNombramiento::PERMANENTE))->toBeFalse();
});

// ── Por qué la partida no puede vivir en el puesto ──────────────

/**
 * El caso que motivó mover la columna: un mismo puesto de carrera ocupado por
 * dos modalidades distintas se imputa a dos partidas distintas. Con una sola
 * partida en el puesto, una de las dos quedaba mal.
 */
test('dos vínculos sobre el mismo puesto pueden llevar partidas distintas', function () {
    $permanente = ($this->contratoCon)(
        TipoNombramiento::PERMANENTE,
        PartidaPresupuestaria::where('codigo', '510105')->value('id'),
    );

    $ocasional = ($this->contratoCon)(
        TipoNombramiento::SERVICIOS_OCASIONALES,
        PartidaPresupuestaria::where('codigo', '510510')->value('id'),
    );

    expect($permanente->puesto_id)->toBe($ocasional->puesto_id)
        ->and($permanente->partidaPresupuestaria->codigo)->toBe('510105')
        ->and($ocasional->partidaPresupuestaria->codigo)->toBe('510510');
});

// ── Subrogaciones y encargos ────────────────────────────────────

test('la subrogación y el encargo tienen partida propia', function () {
    expect(PartidaPorModalidad::SUBROGACION)->toBe('510512')
        ->and(PartidaPorModalidad::ENCARGO)->toBe('510513');

    expect(PartidaPresupuestaria::where('codigo', '510512')->exists())->toBeTrue()
        ->and(PartidaPresupuestaria::where('codigo', '510513')->exists())->toBeTrue();
});

test('los códigos inventados de subrogación ya no están en el catálogo', function () {
    expect(PartidaPresupuestaria::whereIn('codigo', ['510901', '510902'])->exists())
        ->toBeFalse();
});
