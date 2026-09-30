<?php

/*
| Un período generado por adelantado no cuenta contra el tope de acumulación.
|
| La pantalla de períodos deja elegir el año, y `saldoTotal()` sumaba TODOS los
| períodos abiertos, los de años futuros incluidos. Cada período generado por
| adelantado inflaba el acumulado, podía inventar un excedente sobre el tope, y
| vencer ese excedente quita días de los períodos MÁS ANTIGUOS: se perdían días
| reales por un período que todavía no se ha ganado, y vencer no se deshace.
|
| Es la misma regla que ya aplicaba `saldoHasta()` al aprobar una vacación, con
| su motivo escrito: «esos días todavía no se han ganado, aunque alguien haya
| generado el período por adelantado».
|
| Y el año que se pide generar se valida: `generar` y `generar-todos` hacían
| `(int) input('anio')`, de modo que «hola» entraba como el año 0 — en la
| operación que recorre la plantilla entera.
*/

use App\Enums\RegimenLaboral;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Asistencia\TopeAcumulacionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba(['nombre' => 'Dirección del Año']);
    $puesto = puestoDePrueba($unidad);

    $this->anio = now()->year;

    $this->servidor = Servidor::create([
        'cedula'                       => '0800007001',
        'nombre'                       => 'Aníbal',
        'apellido'                     => 'Anios',
        'puesto_id'                    => $puesto->id,
        'unidad_administrativa_id'     => $unidad->id,
        'regimen_laboral'              => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion'    => now()->subYears(12),
        'fecha_ingreso_sector_publico' => now()->subYears(12),
        'estado'                       => true,
    ]);

    $this->uath = User::create([
        'email'        => 'anios@example.com',
        'usuario_ti'   => 'anios',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $this->uath->assignRole('admin-uath');

    $this->periodo = fn (int $anio, float $saldo) => PeriodoVacacion::create([
        'servidor_id'          => $this->servidor->id,
        'anio'                 => $anio,
        'fecha_inicio_periodo' => Carbon::create($anio, 1, 1),
        'fecha_fin_periodo'    => Carbon::create($anio, 12, 31),
        'regimen'              => 'losep',
        'anios_antiguedad'     => 12,
        'dias_generados'       => 30,
        'dias_utilizados'      => 30 - $saldo,
        'dias_saldo'           => $saldo,
        'saldo_acumulado'      => $saldo,
        'estado'               => 'abierto',
    ]);

    $this->tope = app(TopeAcumulacionService::class);
});

test('el saldo de un período futuro no cuenta contra el tope', function () {
    // 55 ya ganados, más 30 de un período generado para el año que viene: 85
    // sumando todo, que pasaría el tope de 60 por 25 días.
    ($this->periodo)($this->anio - 1, 30);
    ($this->periodo)($this->anio, 25);
    ($this->periodo)($this->anio + 1, 30);

    $estado = $this->tope->estado($this->servidor->fresh());

    expect($estado['saldo'])->toBe(55.0)
        ->and($estado['tope'])->toBe(60.0)
        ->and($estado['excedente'])->toBe(0.0)
        ->and($estado['alerta'])->toBeTrue(); // 55 pasa el 75 % de 60
});

test('sin el período futuro no hay excedente que vencer', function () {
    ($this->periodo)($this->anio - 1, 30);
    ($this->periodo)($this->anio, 25);
    ($this->periodo)($this->anio + 1, 30);

    // Antes el excedente salía en 25 días y se los quitaba al período más
    // antiguo, que sí estaba ganado.
    expect(fn () => $this->tope->vencerExcedente($this->servidor->fresh(), $this->uath))
        ->toThrow(\App\Exceptions\ReglaNegocioException::class, 'no hay excedente que vencer');
});

test('el saldo que se muestra es el que de verdad se puede pedir', function () {
    ($this->periodo)($this->anio, 25);
    ($this->periodo)($this->anio + 1, 30);

    $servicio = app(\App\Services\Asistencia\PeriodoVacacionService::class);

    // Antes el portal decía 55 y, al pedir vacaciones para hoy, la solicitud se
    // rechazaba por saldo insuficiente: quien aprueba mira `saldoHasta()`, que
    // nunca contó los períodos futuros.
    expect($servicio->saldoTotal($this->servidor->id))->toBe(25.0)
        ->and($servicio->saldoHasta($this->servidor->id, $this->anio))->toBe(25.0)
        // Para una vacación del año que viene sí cuentan los dos.
        ->and($servicio->saldoHasta($this->servidor->id, $this->anio + 1))->toBe(55.0);
});

test('el seguimiento del tope tampoco cuenta los períodos futuros', function () {
    ($this->periodo)($this->anio, 40);
    ($this->periodo)($this->anio + 1, 30);

    $filas = $this->tope->enSeguimiento();
    $fila  = $filas->firstWhere('servidor_id', $this->servidor->id);

    // 40 de 60 no llega al 75 %, así que no debe ni aparecer. Sumando el
    // período futuro habría dado 70 y se habría listado como excedido.
    expect($fila)->toBeNull();
});

test('el excedente se vence de lo más antiguo ganado, y el período futuro queda intacto', function () {
    $antiguo = ($this->periodo)($this->anio - 2, 30);
    $medio   = ($this->periodo)($this->anio - 1, 30);
    $actual  = ($this->periodo)($this->anio, 30);
    $futuro  = ($this->periodo)($this->anio + 1, 30);

    // 90 ganados contra un tope de 60: sobran 30.
    $resultado = $this->tope->vencerExcedente($this->servidor->fresh(), $this->uath);

    expect($resultado['dias_vencidos'])->toBe(30.0)
        ->and($resultado['saldo_antes'])->toBe(90.0)
        ->and($resultado['saldo_despues'])->toBe(60.0)
        ->and($resultado['tramos'])->toBe([['anio' => $this->anio - 2, 'dias' => 30.0]]);

    expect((float) $antiguo->fresh()->dias_saldo)->toBe(0.0)
        ->and((float) $antiguo->fresh()->dias_vencidos)->toBe(30.0)
        ->and((float) $medio->fresh()->dias_saldo)->toBe(30.0)
        ->and((float) $actual->fresh()->dias_saldo)->toBe(30.0)
        // El de más adelante ni se mira: no se ha ganado, así que no se vence.
        ->and((float) $futuro->fresh()->dias_saldo)->toBe(30.0)
        ->and((float) $futuro->fresh()->dias_vencidos)->toBe(0.0);
});

test('generar rechaza un año que no es un año', function () {
    $this->actingAs($this->uath, 'sanctum')
        ->postJson("/api/v1/asistencia/periodos-vacaciones/servidores/{$this->servidor->id}/generar", [
            'anio' => 'hola',
        ])
        ->assertStatus(422)
        // El manejador del proyecto envuelve los errores de validación en
        // `errores`, no en la forma de Laravel.
        ->assertJsonPath('errores.anio.0', 'El campo año debe ser un número entero.');

    expect(PeriodoVacacion::where('servidor_id', $this->servidor->id)->count())->toBe(0);
});

test('generar sin año usa el corriente, como antes', function () {
    $this->actingAs($this->uath, 'sanctum')
        ->postJson("/api/v1/asistencia/periodos-vacaciones/servidores/{$this->servidor->id}/generar")
        ->assertOk();

    expect(PeriodoVacacion::where('servidor_id', $this->servidor->id)->value('anio'))
        ->toBe($this->anio);
});

test('la generación masiva no abre el período del año cero a toda la plantilla', function () {
    $this->actingAs($this->uath, 'sanctum')
        ->postJson('/api/v1/asistencia/periodos-vacaciones/generar-todos', ['anio' => -5])
        ->assertStatus(422)
        ->assertJsonPath('errores.anio.0', 'El campo año debe ser al menos 2000.');

    expect(PeriodoVacacion::count())->toBe(0);
});
