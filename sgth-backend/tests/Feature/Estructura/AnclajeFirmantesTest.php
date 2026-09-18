<?php

/*
| Las unidades de las que salen los firmantes se marcan al instalar.
|
| El seeder las infiere por el cargo del puesto de jefatura, que es un valor de
| arranque para verificar, no una verdad: quién firma no puede depender de una
| coincidencia de texto. Por eso no toca lo que ya está marcado y, ante la duda,
| prefiere no marcar nada.
|
| La financiera llegó con las firmas selladas de Viáticos: su migración también
| intenta marcarla, pero corre antes de que existan las unidades, así que en una
| instalación nueva el trabajo le toca al seeder.
*/

use App\Models\Estructura\UnidadAdministrativa;
use Database\Seeders\AnclajeFirmantesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->unidadCon = function (string $codigo, string $nombre, string $cargo) {
        $unidad = unidadDePrueba(['codigo' => $codigo, 'nombre' => $nombre]);
        puestoJefeDePrueba($unidad, $cargo);

        return $unidad;
    };

    $marcadas = fn () => UnidadAdministrativa::query()
        ->where(fn ($q) => $q->where('es_maxima_autoridad', true)
            ->orWhere('es_unidad_talento_humano', true)
            ->orWhere('es_unidad_financiera', true))
        ->get()
        ->mapWithKeys(fn (UnidadAdministrativa $u) => [$u->nombre => collect([
            'es_maxima_autoridad', 'es_unidad_talento_humano', 'es_unidad_financiera',
        ])->filter(fn ($b) => $u->{$b})->values()->all()])
        ->all();

    $this->marcadas = $marcadas;
});

it('marca las tres unidades por el cargo de su jefatura', function () {
    ($this->unidadCon)('APRE', 'Prefectura Provincial', 'Prefecto/a Provincial');
    ($this->unidadCon)('ATH', 'Gestión de Talento Humano', 'Director/a de Talento Humano');
    ($this->unidadCon)('AFIN', 'Gestión Financiera', 'Director/a Financiero/a');
    ($this->unidadCon)('AOBR', 'Obras Públicas', 'Director/a de Obras Públicas');

    $this->seed(AnclajeFirmantesSeeder::class);

    expect(($this->marcadas)())->toBe([
        'Prefectura Provincial'      => ['es_maxima_autoridad'],
        'Gestión de Talento Humano'  => ['es_unidad_talento_humano'],
        'Gestión Financiera'         => ['es_unidad_financiera'],
    ]);
});

it('no marca nada cuando hay más de una candidata', function () {
    ($this->unidadCon)('AFIN', 'Gestión Financiera', 'Director/a Financiero/a');
    ($this->unidadCon)('AFI2', 'Coordinación Financiera', 'Coordinador/a Financiero/a');

    $this->seed(AnclajeFirmantesSeeder::class);

    // Con dos candidatas no elige: la marca se pone a mano en Estructura.
    expect(($this->marcadas)())->toBe([]);
});

it('no toca la unidad que ya está marcada', function () {
    $aMano = ($this->unidadCon)('AFI2', 'Coordinación Financiera', 'Coordinador/a Financiero/a');
    $aMano->update(['es_unidad_financiera' => true]);

    ($this->unidadCon)('AFIN', 'Gestión Financiera', 'Director/a Financiero/a');

    $this->seed(AnclajeFirmantesSeeder::class);

    expect(($this->marcadas)())->toBe(['Coordinación Financiera' => ['es_unidad_financiera']]);
});
