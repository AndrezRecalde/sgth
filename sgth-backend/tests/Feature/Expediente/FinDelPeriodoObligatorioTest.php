<?php

namespace Tests\Feature\Expediente;

use App\Enums\TipoEventoVinculo;
use App\Enums\TipoNombramiento;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\EventoVinculo;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| El fin del período de las autoridades electas es obligatorio (2026-10-21).
| Lo exige la base, y a las de la carga inicial, que entraron sin él, la
| migración se lo fijó con el período legal de cuatro años: el que corre hoy,
| aunque el inicio cargado sea de uno anterior.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    $director = User::factory()->create();
    $director->assignRole('admin-uath');
    $this->actingAs($director, 'sanctum');

    $this->migracion = require database_path('migrations/2026_11_15_000000_exigir_fin_del_periodo_de_autoridades.php');

    $prefectura = UnidadAdministrativa::create([
        'codigo' => 'PREF', 'nombre' => 'Prefectura Provincial', 'nivel' => 1, 'es_maxima_autoridad' => true,
    ]);
    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $prefectura->id, 'plazas' => 1, 'es_jefe' => true,
        'grupo_ocupacional_id'     => GrupoOcupacional::create([
            'grado_codigo' => 'NJS-10', 'grado_numerico' => 10, 'grupo' => 'NJS', 'denominacion_generica' => 'Prefecto/a',
            'rmu' => 5060, 'regimen' => 'losep', 'activo' => true,
        ])->id,
    ]);

    $this->contador = 0;
    /** Una autoridad de la carga inicial. Sin fin, solo cabe con la regla quitada. */
    $this->autoridad = function (string $desde, ?string $hasta, string $estado = 'vigente'): ContratoServidor {
        $this->contador++;
        $servidor = Servidor::create([
            'cedula'          => str_pad((string) (8800000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Autoridad',
            'apellido'        => 'Cargada'.$this->contador,
            'regimen_laboral' => 'losep',
        ]);

        return ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => TipoNombramiento::ELECCION_POPULAR->value,
            'numero_contrato'          => 'CRED-'.$this->contador,
            'unidad_administrativa_id' => $this->puesto->unidad_administrativa_id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => $desde,
            'fecha_fin'                => $hasta,
            'remuneracion'             => 5060,
            'estado'                   => $estado,
        ]);
    };
});

test('la base no admite una autoridad electa sin fin del período', function () {
    expect(fn () => ($this->autoridad)('2023-05-15', null))
        ->toThrow(QueryException::class, 'contratos_eleccion_popular_con_fin_del_periodo_check');
});

test('a la de la carga inicial se le fija el fin con el período legal, y queda en la bitácora', function () {
    $this->migracion->down();
    $contrato = ($this->autoridad)('2023-05-15', null);

    $this->migracion->up();

    expect($contrato->fresh()->fecha_fin->toDateString())->toBe('2027-05-14')
        ->and(EventoVinculo::where('contrato_servidor_id', $contrato->id)
            ->where('tipo', TipoEventoVinculo::FIN_DEL_PERIODO_FIJADO->value)
            ->value('descripcion'))
        ->toContain('14/05/2027')
        ->toContain('período legal de cuatro años desde el 15/05/2023')
        ->toContain('Revíselo contra la credencial');
});

test('si el inicio cargado es de un período anterior, se toma el que corre hoy', function () {
    $this->migracion->down();
    $inicio = now()->subYears(5)->startOfDay();
    $reelegida = ($this->autoridad)($inicio->toDateString(), null);
    // La que ya terminó no se mueve al período de hoy: ese día no está en funciones.
    $terminada = ($this->autoridad)(now()->subYears(9)->toDateString(), null, 'terminado');

    $this->migracion->up();

    expect($reelegida->fresh()->fecha_fin->toDateString())
        ->toBe($inicio->copy()->addYearsNoOverflow(8)->subDay()->toDateString())
        ->and(EventoVinculo::where('contrato_servidor_id', $reelegida->id)->value('descripcion'))
        ->toContain('es de un período anterior')
        ->and($terminada->fresh()->fecha_fin->toDateString())
        ->toBe(now()->subYears(9)->addYearsNoOverflow(4)->subDay()->toDateString());
});

test('el fin que corrige Talento Humano se mide contra el período que corre hoy', function () {
    $inicio = now()->subYears(5)->startOfDay();
    $finLegal = $inicio->copy()->addYearsNoOverflow(8)->subDay();
    $contrato = ($this->autoridad)($inicio->toDateString(), $finLegal->toDateString());
    $url = "/api/v1/expediente/contratos/{$contrato->id}/fin-del-periodo";

    // Contra el inicio cargado pasaría de cuatro años; contra el período de hoy, no.
    $this->postJson($url, ['fecha_fin' => $finLegal->copy()->subDay()->toDateString()])->assertOk();

    $this->postJson($url, ['fecha_fin' => $finLegal->copy()->addMonth()->toDateString()])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'cuatro años'));
});
