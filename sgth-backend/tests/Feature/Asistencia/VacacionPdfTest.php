<?php

/*
| El PDF de la solicitud de vacaciones.
|
| Cada test es algo que el papel decía mal:
| - Reemplazo y Observaciones salían siempre con «—»: el servicio no los
|   guardaba y la observación no tenía columna.
| - «Días de derecho» y «Período de vac.» leían campos que la solicitud nunca
|   tuvo, así que también salían vacíos.
| - Una solicitud anulada o rechazada se imprimía igual que una aprobada.
|
| Como en `PermisoPdfVisualTest`, se comprueba el HTML de la vista con lo mismo
| que le pasa `exportar()`.
*/

use App\Enums\RegimenLaboral;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Asistencia\Vacacion;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Asistencia\PeriodoVacacionService;
use App\Services\Asistencia\VacacionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    Vacacion::unguard();

    $unidad = unidadDePrueba();
    $puesto = puestoDePrueba($unidad);

    $this->titular = Servidor::create([
        'cedula'                       => '0800001101',
        'nombre'                       => 'Paola',
        'apellido'                     => 'Papel',
        'puesto_id'                    => $puesto->id,
        'unidad_administrativa_id'     => $unidad->id,
        'regimen_laboral'              => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion'    => '2018-03-01',
        'fecha_ingreso_sector_publico' => '2018-03-01',
        'estado'                       => true,
    ]);

    $this->reemplazo = Servidor::create([
        'cedula'                   => '0800001102',
        'nombre'                   => 'Ramiro',
        'apellido'                 => 'Suplente',
        'puesto_id'                => $puesto->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);

    $this->inicio = now()->addDays(7)->startOfDay();
    $this->anio   = $this->inicio->year;

    $this->periodo = fn (int $anio, float $generados, float $utilizados) => PeriodoVacacion::create([
        'servidor_id'          => $this->titular->id,
        'anio'                 => $anio,
        'fecha_inicio_periodo' => Carbon::create($anio, 1, 1),
        'fecha_fin_periodo'    => Carbon::create($anio, 12, 31),
        'regimen'              => 'losep',
        'anios_antiguedad'     => 8,
        'dias_generados'       => $generados,
        'dias_utilizados'      => $utilizados,
        'dias_saldo'           => $generados - $utilizados,
        'saldo_acumulado'      => $generados - $utilizados,
        'estado'               => 'abierto',
    ]);

    $this->vacacion = fn (array $atributos = []) => Vacacion::create(array_merge([
        'servidor_id'      => $this->titular->id,
        'fecha_inicio'     => $this->inicio->toDateString(),
        'fecha_fin'        => $this->inicio->copy()->addDays(11)->toDateString(),
        'dias_solicitados' => 12,
        'tipo_dias'        => 'habiles',
        'estado'           => 'pendiente',
        'motivo'           => 'vacaciones_anuales',
        'folio'            => 'VAC-2099-00077',
    ], $atributos));

    $this->usuario = User::create([
        'email'        => 'uath-pdf@example.com',
        'usuario_ti'   => 'uathpdf',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
});

/** El HTML de la vista, con lo mismo que carga y le pasa `exportar()`. */
function htmlDelPdfDeVacacion(Vacacion $vacacion): string
{
    $vacacion->load(['servidor.puesto.cargo', 'jefe', 'personaReemplaza', 'unidadAdministrativa', 'creadoPor', 'aprobadoPor']);

    return view('vacaciones.vacacion-pdf', [
        'vacacion'  => $vacacion,
        'impresion' => app(PeriodoVacacionService::class)->paraImpresion($vacacion),
        'urlQr'     => Vacacion::urlVerificacion($vacacion->folio),
    ])->render();
}

test('la observación y la persona que reemplaza salen en el papel', function () {
    $html = htmlDelPdfDeVacacion(($this->vacacion)([
        'persona_reemplaza_id' => $this->reemplazo->id,
        'observacion'          => 'Cubre la ventanilla de atención',
    ]));

    expect($html)->toContain('SUPLENTE RAMIRO')
        ->toContain('CUBRE LA VENTANILLA DE ATENCIÓN');
});

test('pendiente: los días de derecho salen del período, y el período se asigna al aprobar', function () {
    ($this->periodo)($this->anio, 20, 0);

    expect(htmlDelPdfDeVacacion(($this->vacacion)()))
        ->toContain("20 DÍAS (PERÍODO {$this->anio})")
        ->toContain('SE ASIGNA AL APROBAR LA SOLICITUD');
});

test('aprobada: el período dice de qué años salieron los días', function () {
    ($this->periodo)($this->anio - 1, 20, 10);
    ($this->periodo)($this->anio, 20, 0);
    $vacacion = ($this->vacacion)();

    app(VacacionService::class)->resolver($vacacion->id, 'aprobada', $this->usuario);

    $anterior = $this->anio - 1;
    expect(htmlDelPdfDeVacacion($vacacion->fresh()))
        ->toContain("{$anterior}: 10 DÍAS")
        ->toContain("{$this->anio}: 2 DÍAS");
});

test('un motivo que no descuenta lo dice en vez de dejar el período vacío', function () {
    expect(htmlDelPdfDeVacacion(($this->vacacion)(['motivo' => 'matrimonio'])))
        ->toContain('NO DESCUENTA DEL SALDO DE VACACIONES');
});

test('una anulada sale marcada sin validez, con su motivo; una pendiente no', function () {
    $pendiente = ($this->vacacion)();
    expect(htmlDelPdfDeVacacion($pendiente))->not->toContain('SIN VALIDEZ');

    app(VacacionService::class)->anular($pendiente->id, 'Postergada por la unidad', $this->usuario);

    expect(htmlDelPdfDeVacacion($pendiente->fresh()))
        ->toContain('SOLICITUD ANULADA — SIN VALIDEZ')
        ->toContain('POSTERGADA POR LA UNIDAD');
});

test('el pie queda dentro de la hoja', function () {
    // En dompdf la página no tiene margen —el `*` de la vista lo pone en
    // cero—, así que el pie fijo con `bottom: -10px` quedaba fuera de la hoja y
    // no se veía nunca. Tiene que ir con `bottom` positivo.
    $html = htmlDelPdfDeVacacion(($this->vacacion)());

    expect($html)->toContain('Folio: VAC-2099-00077')
        ->toMatch('/\.pie\s*\{[^}]*bottom:\s*\d/');
});

test('una rechazada también sale sin validez', function () {
    expect(htmlDelPdfDeVacacion(($this->vacacion)(['estado' => 'rechazada'])))
        ->toContain('SOLICITUD RECHAZADA — SIN VALIDEZ');
});
