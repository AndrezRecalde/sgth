<?php

/*
| Folio único y fechas que no se pisan.
|
| El folio salía de contar las solicitudes del año. `count()` no ve las
| borradas —el modelo usa SoftDeletes—, así que al borrar una el siguiente folio
| repetía uno ya emitido y el índice único lo rechazaba. Dos altas simultáneas
| contaban lo mismo y chocaban igual. Ahora sale del mayor ya emitido,
| borradas incluidas, bajo un lock que serializa a quien lo calcula: el mismo
| arreglo que ya tienen AgendaService y AdquisicionService.
|
| Además, nada impedía registrar dos solicitudes con las mismas fechas para el
| mismo servidor.
*/

use App\Enums\RegimenLaboral;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\Vacacion;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Services\Asistencia\VacacionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    $unidad = unidadDePrueba();
    $puesto = puestoDePrueba($unidad);

    $this->nuevoServidor = fn (string $cedula) => Servidor::create([
        'cedula'                       => $cedula,
        'nombre'                       => 'Fanny',
        'apellido'                     => 'Folio',
        'puesto_id'                    => $puesto->id,
        'unidad_administrativa_id'     => $unidad->id,
        'regimen_laboral'              => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion'    => now()->subYears(3),
        'fecha_ingreso_sector_publico' => now()->subYears(3),
        'estado'                       => true,
    ]);

    $this->servidor = ($this->nuevoServidor)('0800000501');

    // «Matrimonio» no descuenta vacaciones: estas pruebas no necesitan períodos.
    $this->lunes = now()->addWeeks(2)->next(Carbon::MONDAY);
    $this->pedir = fn (int $desde, int $hasta, ?Servidor $servidor = null) => app(VacacionService::class)->solicitar([
        'motivo'       => 'matrimonio',
        'fecha_inicio' => $this->lunes->copy()->addDays($desde)->toDateString(),
        'fecha_fin'    => $this->lunes->copy()->addDays($hasta)->toDateString(),
    ], ($servidor ?? $this->servidor)->id);
});

test('borrar una solicitud no hace que el siguiente folio choque', function () {
    $a = ($this->pedir)(0, 1);
    $b = ($this->pedir)(7, 8);
    $a->delete();
    $c = ($this->pedir)(14, 15);

    $anio = now()->year;
    expect($a->folio)->toBe("VAC-{$anio}-00001")
        ->and($b->folio)->toBe("VAC-{$anio}-00002")
        // No reutiliza el número de la borrada: un folio ya impreso en un
        // papel no puede volver a nombrar otra solicitud.
        ->and($c->folio)->toBe("VAC-{$anio}-00003");
});

test('el folio sigue la numeración aunque la última emitida esté borrada', function () {
    ($this->pedir)(0, 1);
    $ultima = ($this->pedir)(7, 8);
    $ultima->delete();

    expect(($this->pedir)(14, 15)->folio)->toBe('VAC-'.now()->year.'-00003');
});

test('no se aceptan dos solicitudes que se cruzan', function () {
    $primera = ($this->pedir)(0, 3);

    // Mismas fechas, y un cruce parcial por cada extremo.
    foreach ([[0, 3], [2, 5], [-2, 0]] as [$desde, $hasta]) {
        expect(fn () => ($this->pedir)($desde, $hasta))
            ->toThrow(ReglaNegocioException::class, $primera->folio);
    }

    expect(Vacacion::count())->toBe(1);
});

test('una solicitud rechazada no ocupa las fechas', function () {
    $rechazada = ($this->pedir)(0, 1);
    $rechazada->update(['estado' => 'rechazada']);

    expect(($this->pedir)(0, 1)->estado)->toBe('pendiente');
});

test('fechas contiguas no son un cruce, y otro servidor puede usar las mismas', function () {
    ($this->pedir)(0, 1);

    expect(($this->pedir)(2, 3)->estado)->toBe('pendiente')
        ->and(($this->pedir)(0, 1, ($this->nuevoServidor)('0800000502'))->estado)->toBe('pendiente');
});
