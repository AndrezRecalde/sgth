<?php

/*
| El PDF de la solicitud de vacaciones.
|
| Cada test es algo que el papel decía mal:
| - Reemplazo y Observaciones salían siempre con «—»: el servicio no los
|   guardaba y la observación no tenía columna.
| - «Días de derecho» y «Período de vac.» leían campos que la solicitud nunca
|   tuvo, así que también salían vacíos.
| - Una solicitud anulada se imprimía igual que una registrada.
| - Desde el 2026-11-25, en el estilo de casa: no imprimía la cédula, el cargo
|   ni la unidad, que el controlador ya cargaba, y el nombre institucional
|   estaba escrito a mano y no coincidía con el oficial.
| - Y desde el marco, la hoja tiene un alto que no se puede pasar. El último
|   test de este archivo es el que vigila eso.
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

    $unidad = unidadDePrueba(['nombre' => 'Dirección de Talento Humano']);
    $puesto = puestoDePrueba($unidad, 'Analista de Talento Humano');

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
        'estado'           => 'autorizada',
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
    $vacacion->load([
        'servidor.puesto.cargo', 'servidor.unidadAdministrativa',
        'jefe.puesto.cargo', 'personaReemplaza', 'unidadAdministrativa',
    ]);

    return view('vacaciones.vacacion-pdf', [
        'vacacion'  => $vacacion,
        'urlQr'     => Vacacion::urlVerificacion($vacacion->folio),
        // Sin organigrama anclado no hay quién firme: sale el cargo solo, que
        // es justo el caso que el papel tiene que soportar.
        'firmanteTh'        => '',
        'cargoTh'           => 'Director/a de Talento Humano',
        'firmanteAutoridad' => '',
        'cargoAutoridad'    => 'Prefecto/a Provincial',
    ])->render();
}

test('la observación y la persona que reemplaza salen en el papel', function () {
    $html = htmlDelPdfDeVacacion(($this->vacacion)([
        'persona_reemplaza_id' => $this->reemplazo->id,
        'observacion'          => 'Cubre la ventanilla de atención',
    ]));

    // El nombre va en mayúscula por CSS (`td.mayus`) y no con `strtoupper`,
    // que se come las tildes; y la observación, como la escribió el servidor:
    // antes se imprimía a gritos.
    expect($html)->toContain('Suplente Ramiro')
        ->toContain('class="mayus"')
        ->toContain('Cubre la ventanilla de atención');
});

test('salen la cédula, el cargo y la unidad, que el controlador ya cargaba', function () {
    expect(htmlDelPdfDeVacacion(($this->vacacion)()))
        ->toContain('0800001101')
        ->toContain('Analista de Talento Humano')
        ->toContain('Dirección de Talento Humano');
});

test('el motivo se lee junto a las fechas, no de subtítulo', function () {
    // Es un dato de la solicitud y se lee en su bloque; de subtítulo quedaba
    // suelto bajo el título, lejos de los días y las fechas.
    $html = htmlDelPdfDeVacacion(($this->vacacion)(['motivo' => 'permiso_cargo_vacaciones']));

    expect($html)->toContain('Permiso con Cargo a Vacaciones (máx. 5 días)')
        ->not->toContain('class="subtipo"');
});

test('el bloque de Talento Humano va en blanco, también si ya está registrada', function () {
    // La hoja se imprime antes de pasar por ventanilla: ahí los días no han
    // salido de ningún período y el saldo que quedará no existe. Las dos
    // casillas las escribe a mano quien registra. Y siguen en blanco en una
    // reimpresión, para que la casilla se llene siempre igual.
    ($this->periodo)($this->anio - 1, 20, 10);
    ($this->periodo)($this->anio, 20, 0);
    $vacacion = ($this->vacacion)();

    $autorizada = htmlDelPdfDeVacacion($vacacion);
    expect($autorizada)->toContain('Se descuenta de')
        ->toContain('Saldo restante')
        ->toContain('class="escribir"')
        // Lo que se retiró: eran los días que generó el período, no el saldo.
        ->not->toContain('Días de derecho');

    app(VacacionService::class)->registrar($vacacion->id, $this->usuario);

    $anterior = $this->anio - 1;
    expect(htmlDelPdfDeVacacion($vacacion->fresh()))
        ->not->toContain("{$anterior}: 10 días")
        ->not->toContain("{$this->anio}: 2 días");
});

test('una anulada sale marcada sin validez, con su motivo; una autorizada no', function () {
    $autorizada = ($this->vacacion)();
    expect(htmlDelPdfDeVacacion($autorizada))->not->toContain('Sin validez');

    app(VacacionService::class)->anular($autorizada->id, 'Postergada por la unidad', $this->usuario);

    expect(htmlDelPdfDeVacacion($autorizada->fresh()))
        ->toContain('Sin validez')
        ->toContain('Postergada por la unidad');
});

test('el papel no lleva el estado del sistema, y sí la fecha en que se imprimió', function () {
    // Se imprime una vez, recoge las cuatro firmas y se archiva: una etiqueta
    // de estado envejecería dentro del expediente y diría que está pendiente
    // de algo que el jefe ya concedió. La fecha de impresión deja constancia.
    $html = htmlDelPdfDeVacacion(($this->vacacion)());

    expect($html)->toContain('Impreso el '.now()->format('d/m/Y'))
        ->not->toContain('Aprobada por el jefe inmediato')
        ->not->toContain('Registrada por Talento Humano');
});

test('el pie dice el circuito como un hecho, y no envejece al reimprimirla', function () {
    // En imperativo («Entregue este documento… para su registro») solo valía
    // para la primera impresión: en una reimpresión, que saca Talento Humano
    // de una ya registrada, mandaba a hacer algo que ya ocurrió. Dicho como
    // un hecho es cierto en los dos momentos, y el papel sigue sin tener que
    // saber en qué estado está.
    ($this->periodo)($this->anio, 20, 0);
    $vacacion = ($this->vacacion)();
    $frase    = 'Este documento se entrega firmado por el servidor y su jefe inmediato';

    expect(htmlDelPdfDeVacacion($vacacion))
        ->toContain($frase)
        ->not->toContain('Entregue este documento');

    app(VacacionService::class)->registrar($vacacion->id, $this->usuario);

    expect(htmlDelPdfDeVacacion($vacacion->fresh()))->toContain($frase);
});

test('las cuatro firmas del circuito, con el cargo aunque no haya quién firme', function () {
    // El servidor la hace firmar por su jefe, la baja a ventanilla y ahí la
    // firman la Dirección de Talento Humano y Prefectura.
    expect(htmlDelPdfDeVacacion(($this->vacacion)()))
        ->toContain('Servidor solicitante')
        ->toContain('Jefe inmediato')
        ->toContain('Dirección de Talento Humano')
        ->toContain('Prefectura')
        ->toContain('Prefecto/a Provincial');
});

test('el membrete y el pie son los institucionales, no texto escrito a mano', function () {
    // El nombre estaba en la plantilla y ni siquiera coincidía con el oficial:
    // decía «de la Provincia de Esmeraldas».
    $html = htmlDelPdfDeVacacion(($this->vacacion)());

    expect($html)->toContain('class="membrete"')
        ->toContain('class="footer"')
        ->not->toContain('Gobierno Autónomo Descentralizado de la Provincia de Esmeraldas');
});

/*
| El tope de `observacion` en `StoreVacacionRequest` y el alto de su caja en la
| plantilla son la misma decisión tomada dos veces: en esa caja entran unos 100
| caracteres por renglón y la caja mide tres renglones. Este test lee el tope
| del propio request en vez de repetirlo, así que subirlo sin agrandar la caja
| —o sin recortar aire en otro sitio— falla aquí y no en la impresora.
|
| El caso es el peor que se imprime de verdad: anulada, que añade el aviso rojo
| de arriba, con la observación al tope. Importa desde que la hoja lleva marco:
| el marco es un elemento fijo, no crece con el texto, y lo que se pasa se le
| sale por abajo o se va a una segunda hoja. Pasó de verdad mientras se
| ajustaba el espacio para firmar: con 20 mm por firma en vez de 16, esta misma
| solicitud mandaba la nota del pie a una segunda hoja.
*/
test('la hoja aguanta en una sola con la observación al tope y el aviso de anulada', function () {
    preg_match('/max:(\d+)/', (new App\Http\Requests\Asistencia\StoreVacacionRequest)->rules()['observacion'], $m);
    $tope = (int) ($m[1] ?? 0);

    $relleno = str_repeat('Cubre la ventanilla de atención y deja el archivador ordenado por unidad. ', 10);

    $vacacion = ($this->vacacion)([
        'estado'               => 'anulada',
        'anulado_en'           => now(),
        'motivo_anulacion'     => 'El servidor desistió del período solicitado.',
        'observacion'          => mb_substr($relleno, 0, $tope),
        'persona_reemplaza_id' => $this->reemplazo->id,
    ]);

    $pdf = app('dompdf.wrapper')->setPaper('a4', 'portrait')->loadHTML(htmlDelPdfDeVacacion($vacacion));
    $pdf->getDomPDF()->render();

    expect($tope)->toBeGreaterThan(0)
        ->and(mb_strlen((string) $vacacion->observacion))->toBe($tope)
        ->and($pdf->getDomPDF()->getCanvas()->get_page_count())->toBe(1);
});
