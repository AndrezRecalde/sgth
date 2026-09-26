<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\MovimientoPersonalService;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
| El código de registro de una Acción de Personal (AP-AAAA-NNNN) se obtenía
| contando las filas del año en vez de mirar la última emitida. Con un solo
| hueco en la secuencia el correlativo apunta a un código ya usado, el INSERT
| choca contra el unique de la columna y, como la transición corre dentro de
| DB::transaction, se deshace entera: no se inserta nada, el conteo no avanza
| y el intento siguiente repite el mismo código. Talento Humano se quedaba sin
| poder registrar ninguna acción durante el resto del año.
*/

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-COR', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-COR', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 10,
    ]);

    $this->service      = app(MovimientoPersonalService::class);
    $this->stateService = app(MovimientoPersonalStateService::class);

    $this->contador = 0;

    /** Un servidor con vínculo vigente, listo para que le cesen funciones. */
    $this->servidorVinculado = function (): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'cedula'                    => str_pad((string) (7100000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'                    => 'Servidor',
            'apellido'                  => 'Correlativo'.$this->contador,
            'regimen_laboral'           => 'losep',
            'puesto_id'                 => $this->puesto->id,
            'unidad_administrativa_id'  => $this->unidad->id,
            'fecha_ingreso_institucion' => '2018-01-01',
        ]);

        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => 'nombramiento_permanente',
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => '2018-01-01',
            'estado'                   => 'vigente',
        ]);

        return $servidor->fresh('contratoVigente');
    };

    /** Registra una cesación y devuelve el movimiento ya en REGISTRADA. */
    $this->registrarCesacion = function (): MovimientoPersonal {
        $servidor = ($this->servidorVinculado)();

        $m = $this->service->registrar($servidor->id, [
            'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
            'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
            'descripcion'        => 'Renuncia voluntaria',
            'fecha_efectiva'     => '2026-08-01',
        ]);

        $m = $this->stateService->transicionar($m, EstadoAccionPersonal::SUSCRITA);

        return $this->stateService->transicionar($m->fresh(), EstadoAccionPersonal::REGISTRADA);
    };
});

test('la primera acción del año abre la secuencia en 0001', function () {
    $primera = ($this->registrarCesacion)();

    expect($primera->codigo_registro)->toBe('AP-'.now()->year.'-0001');
});

test('un hueco en la secuencia no hace que se repita un código ya emitido', function () {
    $anio = now()->year;

    $primera = ($this->registrarCesacion)();
    expect($primera->codigo_registro)->toBe("AP-{$anio}-0001");

    // Se abre un hueco: queda una sola fila, y su código es el 0002. Contar
    // filas daría 1 y propondría «AP-AAAA-0002», que ya está emitido. Así
    // está la base real, con 0012 y 0014 pero sin 0013.
    DB::table('movimientos_personal')
        ->where('id', $primera->id)
        ->update(['codigo_registro' => "AP-{$anio}-0002"]);

    $segunda = ($this->registrarCesacion)();

    expect($segunda->codigo_registro)->toBe("AP-{$anio}-0003");
});

test('tras un hueco se puede seguir registrando: el módulo no se bloquea', function () {
    $anio = now()->year;

    $primera = ($this->registrarCesacion)();

    DB::table('movimientos_personal')
        ->where('id', $primera->id)
        ->update(['codigo_registro' => "AP-{$anio}-0002"]);

    // Tres seguidas: con el conteo, la primera de estas ya reventaba y las
    // demás repetían el mismo código para siempre.
    $codigos = collect(range(1, 3))
        ->map(fn () => ($this->registrarCesacion)()->codigo_registro);

    expect($codigos->all())->toBe([
        "AP-{$anio}-0003", "AP-{$anio}-0004", "AP-{$anio}-0005",
    ])->and($codigos->unique())->toHaveCount(3);
});
