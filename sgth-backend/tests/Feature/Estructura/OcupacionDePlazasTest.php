<?php

use App\Enums\TipoNombramiento;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Rules\GrupoOcupacionalDelRegimen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * Quién ocupa una plaza y quién solo se asigna al puesto.
 *
 * La plaza es una partida del distributivo. Servicios profesionales y
 * servicios ocasionales se asignan a un puesto —de ahí salen las funciones, el
 * EPP y el puesto de la ficha médica—, pero no descuentan de `plazas`: el
 * puesto sigue vacante para concurso mientras dure el contrato.
 */
beforeEach(function () {
    Servidor::unguard();

    $unidad = unidadDePrueba();
    $this->puesto = puestoDePrueba($unidad, 'Analista', ['plazas' => 1]);

    $this->contratar = function (TipoNombramiento $nombramiento, string $cedula, array $extra = []) {
        $servidor = Servidor::create([
            'cedula' => $cedula, 'nombre' => 'Prueba', 'apellido' => 'Plaza',
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

test('un nombramiento permanente ocupa la plaza', function () {
    ($this->contratar)(TipoNombramiento::PERMANENTE, '0800000001');

    $puesto = $this->puesto->fresh();

    expect($puesto->plazasOcupadas())->toBe(1)
        ->and($puesto->plazasDisponibles())->toBe(0)
        ->and($puesto->tieneVacantes())->toBeFalse();
});

test('un contrato de servicios ocasionales no ocupa la plaza', function () {
    ($this->contratar)(TipoNombramiento::SERVICIOS_OCASIONALES, '0800000002');

    $puesto = $this->puesto->fresh();

    // Está asignado al puesto, pero la plaza sigue libre para concurso.
    expect($puesto->contratosVigentes()->count())->toBe(1)
        ->and($puesto->plazasOcupadas())->toBe(0)
        ->and($puesto->tieneVacantes())->toBeTrue();
});

test('un contrato de servicios profesionales tampoco ocupa la plaza', function () {
    // La base exige fecha de vencimiento en los contratos civiles: es un
    // CHECK de la tabla, no una regla del modelo.
    ($this->contratar)(TipoNombramiento::SERVICIOS_PROFESIONALES, '0800000003', ['fecha_fin' => '2026-12-31']);

    expect($this->puesto->fresh()->plazasOcupadas())->toBe(0);
});

test('el obrero del Código del Trabajo sí ocupa plaza', function () {
    // Los obreros tienen sus propios puestos en el distributivo: no son la
    // excepción, aunque no sean LOSEP.
    ($this->contratar)(TipoNombramiento::CODIGO_TRABAJO, '0800000004');

    expect($this->puesto->fresh()->plazasOcupadas())->toBe(1);
});

test('el predicado y la lista para SQL dicen lo mismo', function () {
    // `valoresSinPlaza()` se usa en los `whereNotIn`. Si se desincronizara del
    // predicado, el conteo y la validación de vacante discreparían.
    $sinPlaza = TipoNombramiento::valoresSinPlaza();

    foreach (TipoNombramiento::cases() as $caso) {
        expect(in_array($caso->value, $sinPlaza, true))->toBe(! $caso->ocupaPlaza());
    }

    expect($sinPlaza)->toEqualCanonicalizing([
        TipoNombramiento::SERVICIOS_PROFESIONALES->value,
        TipoNombramiento::SERVICIOS_OCASIONALES->value,
    ]);
});

// ── El grupo ocupacional tiene que ser del régimen del puesto ────

test('un puesto de Código del Trabajo no admite un grado de la escala LOSEP', function () {
    $grupoLosep = GrupoOcupacional::where('regimen', 'losep')->firstOrFail();

    $validador = Validator::make(
        ['grupo_ocupacional_id' => $grupoLosep->id],
        ['grupo_ocupacional_id' => [new GrupoOcupacionalDelRegimen('codigo_trabajo')]]
    );

    expect($validador->fails())->toBeTrue()
        ->and($validador->errors()->first('grupo_ocupacional_id'))
        ->toContain('escala de LOSEP');
});

test('el grado del mismo régimen pasa, y un puesto sin grupo también', function () {
    $grupoLosep = GrupoOcupacional::where('regimen', 'losep')->firstOrFail();

    $conGrupo = Validator::make(
        ['grupo_ocupacional_id' => $grupoLosep->id],
        ['grupo_ocupacional_id' => [new GrupoOcupacionalDelRegimen('losep')]]
    );

    // Sin grupo es válido: bajo Código del Trabajo la remuneración se pacta en
    // cada contrato y el puesto no define ninguna.
    $sinGrupo = Validator::make(
        ['grupo_ocupacional_id' => null],
        ['grupo_ocupacional_id' => [new GrupoOcupacionalDelRegimen('codigo_trabajo')]]
    );

    expect($conGrupo->fails())->toBeFalse()
        ->and($sinGrupo->fails())->toBeFalse();
});

test('la plaza liberada por un ocasional se puede llenar con un nombramiento', function () {
    // La consecuencia práctica: el puesto de una sola plaza admite al ocasional
    // y además al titular del concurso, porque solo uno de los dos la ocupa.
    ($this->contratar)(TipoNombramiento::SERVICIOS_OCASIONALES, '0800000005');
    ($this->contratar)(TipoNombramiento::PERMANENTE, '0800000006');

    $puesto = $this->puesto->fresh();

    expect($puesto->contratosVigentes()->count())->toBe(2)
        ->and($puesto->plazasOcupadas())->toBe(1)
        ->and($puesto->plazasDisponibles())->toBe(0);
});
