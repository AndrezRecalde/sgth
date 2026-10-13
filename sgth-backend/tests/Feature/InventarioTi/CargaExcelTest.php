<?php

use App\Enums\CondicionFisicaBien;
use App\Enums\EstadoBien;
use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\ContratoVigencia;
use App\Models\InventarioTi\MantenimientoBien;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/*
| La carga del Excel de TIC (docs/diseno-subsistema-tic.md, sección 7; fase
| 1.4). El Excel se arma aquí con la forma de la pestaña «Equipos»: dos filas
| de encabezado y las columnas en su sitio (B PISO, E ACTUAL, F ASIGNADO,
| M Serie, N Fecha de Compra…).
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

/** Dónde dejar el informe: fuera de `storage`, que es de la aplicación. */
function informeTemporalTic(): string
{
    return tempnam(sys_get_temp_dir(), 'inf') . '.xlsx';
}

/** @param  list<array<int, mixed>>  $filas  columna (0 = A) => valor */
function excelDeCargaTic(array $filas, array $encabezado = []): string
{
    $libro = new Spreadsheet;
    $hoja = $libro->getActiveSheet()->setTitle('Equipos');
    $titulos = $encabezado ?: [0 => 'N°', 1 => 'PISO', 2 => 'ANTERIOR', 4 => 'ACTUAL', 5 => 'ASIGNADO', 6 => 'AREA',
        8 => 'TIPO', 9 => 'SUBTIPO', 10 => 'Marca ', 11 => 'Modelo', 12 => 'Serie', 13 => 'Fecha de Compra'];
    foreach ($titulos as $col => $texto) {
        $hoja->setCellValue([$col + 1, 2], $texto);
    }
    foreach ($filas as $i => $fila) {
        foreach ($fila as $col => $valor) {
            $hoja->setCellValue([$col + 1, $i + 3], $valor);
        }
    }
    $ruta = tempnam(sys_get_temp_dir(), 'tic') . '.xlsx';
    (new Xlsx($libro))->save($ruta);

    return $ruta;
}

beforeEach(function () {
    Servidor::unguard();
    $nuevo = fn (string $cedula, string $nombre, ?string $segundo, string $apellido, ?string $segundoApellido, bool $activo = true) => Servidor::create([
        'cedula' => $cedula, 'nombre' => $nombre, 'segundo_nombre' => $segundo, 'apellido' => $apellido,
        'segundo_apellido' => $segundoApellido, 'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => $activo,
    ]);
    $this->gabriela = $nuevo('0807777771', 'Gabriela', null, 'Mero', 'Bermeo');
    $this->paola = $nuevo('0807777772', 'Paola', 'Andrea', 'Benavides', 'Montenegro');
    $this->omar = $nuevo('0807777773', 'Omar', 'Humberto', 'Vega', 'Macías');
    $nuevo('0807777774', 'Luis', null, 'Pérez', 'Gómez');
    $nuevo('0807777775', 'Luis', 'Alberto', 'Pérez', 'Gómez');
    $nuevo('0807777776', 'Efrén', null, 'Zatizabal', 'Cabeza', false);
});

function filaCompletaDeCargaTic(): array
{
    return [
        1 => '6TO', 2 => '007-01-010-04047', 4 => 1774, 5 => 'Vega Macias Omar Humberto', 6 => 'ASESORIA LEGAL',
        8 => 'COMPUTADOR', 9 => 'TODO EN UNO', 10 => 'DELL ', 11 => 'OPTIPLEX 7480', 12 => '1KC6NF3', 13 => 44473,
        14 => 45062, 15 => 'Loor', 16 => 'LEG-GMERO01', 17 => 'gmero',
        18 => 'VIGENCIA TECNOLOGICA, PROCESO SIE-GADPE-018-2021; CRONOGRAMA DE MANTENIMIENTO Entre el 15 y 30 de octubre del 2022 / 2023 / 2024; ADMINISTRADOR: Paola Benavides.',
        19 => 'BENAVIDES MONTENEGRO PAOLA ANDREA', 20 => 'SSD', 21 => 45496, 22 => 48618,
        23 => 'Memorando Nº 066 GADPE-HAP-GTIC-2026', 24 => 'ZATIZABAL CABEZA EFREN', 25 => 41708,
        26 => 'MONITOR DADO DE BAJA MEMO 547', 27 => 45207, 28 => 'AYOVI', 29 => 'Equipo en quininde',
        30 => 'SIN DOMINIO', 31 => 'REGULAR',
    ];
}

test('la simulacion no guarda nada y deja el informe', function () {
    $informe = tempnam(sys_get_temp_dir(), 'inf') . '.xlsx';

    $this->artisan('inventario:cargar-excel', ['archivo' => excelDeCargaTic([filaCompletaDeCargaTic()]), '--simular' => true, '--informe' => $informe])
        ->expectsOutputToContain('Simulación')
        ->assertSuccessful();

    expect(BienInformatico::count())->toBe(0)
        ->and(ContratoVigencia::count())->toBe(0)
        ->and(filesize($informe))->toBeGreaterThan(0);
});

test('sin simular pide quien autorizo la carga', function () {
    $this->artisan('inventario:cargar-excel', ['archivo' => excelDeCargaTic([filaCompletaDeCargaTic()]), '--informe' => informeTemporalTic()])->assertFailed();
    expect(BienInformatico::count())->toBe(0);
});

test('una fila completa del Excel queda como equipo, custodia, mantenimientos y contrato', function () {
    $this->artisan('inventario:cargar-excel', ['archivo' => excelDeCargaTic([filaCompletaDeCargaTic()]), '--responsable' => 'Jefe de TIC', '--informe' => informeTemporalTic()])
        ->assertSuccessful();

    $bien = BienInformatico::with(['tipo', 'subtipo', 'marca', 'ubicacion', 'contratoVigencia.cronograma'])->sole();

    expect($bien->codigo_institucional)->toBe('001774')
        ->and($bien->codigo_anterior)->toBe('007-01-010-04047')
        ->and($bien->tipo->nombre)->toBe('Computador')
        ->and($bien->subtipo->nombre)->toBe('Todo en uno')
        ->and($bien->marca->nombre)->toBe('DELL')
        ->and($bien->ubicacion->nombre)->toBe('Edificio principal · 6')
        ->and($bien->fecha_compra->toDateString())->toBe('2021-10-04')
        ->and($bien->condicion_fisica)->toBe(CondicionFisicaBien::REGULAR)
        ->and($bien->caracteristicas_tecnicas)->toBe(['tipo_disco' => 'SSD'])
        ->and($bien->estado_operativo)->toBe(EstadoBien::EN_USO)
        ->and($bien->observaciones)->toContain('Equipo en quininde', 'MONITOR DADO DE BAJA MEMO 547', 'Terremoto de abril de 2025');

    // «CUSTODIA ADMINISTRATIVO» es el custodio; el asignado, quien lo usa (D3).
    $custodia = AsignacionBien::sole();
    expect($custodia->servidor_id)->toBe($this->paola->id)
        ->and($custodia->usuario_servidor_id)->toBe($this->omar->id)
        ->and($custodia->memorando_numero)->toBe('Memorando Nº 066 GADPE-HAP-GTIC-2026')
        ->and($custodia->soporte_anterior)->toBe('48618')
        ->and($custodia->observaciones)->toContain('ZATIZABAL CABEZA EFREN');

    expect(MantenimientoBien::orderBy('id')->pluck('soporte_anterior', 'tipo_mantenimiento')->all())
        ->toBe(['preventivo' => '45062', 'repotenciacion' => '45496'])
        ->and(MantenimientoBien::whereNotNull('fecha_mantenimiento')->exists())->toBeFalse();

    expect($bien->contratoVigencia->proceso)->toBe('SIE-GADPE-018-2021')
        ->and($bien->contratoVigencia->administrador_id)->toBe($this->paola->id)
        ->and($bien->contratoVigencia->cronograma->pluck('fecha_desde')->map->toDateString()->all())
        ->toBe(['2022-10-15', '2023-10-15', '2024-10-15']);
});

test('los nombres ambiguos, desconocidos o de inactivos no se asignan y la carga se puede repetir', function () {
    $filas = [
        [1 => 'SM', 4 => 2001, 5 => 'PEREZ GOMEZ LUIS', 6 => 'SAN MATEO', 8 => 'PORTATIL', 9 => 'PORTATIL', 10 => 'HP', 12 => 'S/N'],
        [1 => 'PB', 4 => '2002', 5 => 'Persona Que No Existe', 6 => 'TTHH', 8 => 'COMPUTADOR', 9 => 'CPU', 10 => 'hp', 12 => 'S/N'],
        [1 => '3ER', 4 => 2003, 5 => 'ZATIZABAL CABEZA EFREN', 8 => 'COMPUTADOR', 10 => 'Lenovo'],
        [1 => '1ER', 4 => 2004, 5 => 'mero bermeo gabriela', 8 => 'COMPUTADOR', 10 => 'Lenovo', 12 => 'X1'],
        [4 => 'OJO', 5 => 'MERO BERMEO GABRIELA', 8 => 'COMPUTADOR', 10 => 'HP'],
    ];
    $archivo = excelDeCargaTic($filas);
    $talentoHumano = unidadDePrueba(['nombre' => 'Gestión de Talento Humano y Riesgos Laborales']);

    $this->artisan('inventario:cargar-excel', ['archivo' => $archivo, '--responsable' => 'Jefe de TIC', '--informe' => informeTemporalTic()])->assertSuccessful();

    expect(BienInformatico::count())->toBe(4)
        ->and(AsignacionBien::pluck('servidor_id')->all())->toBe([$this->gabriela->id])
        // «HP» y «hp» son la misma marca; «S/N» no es una serie.
        ->and(\App\Models\InventarioTi\Marca::count())->toBe(2)
        ->and(BienInformatico::whereNull('numero_serie')->count())->toBe(3)
        ->and(BienInformatico::where('codigo_institucional', '002001')->first()->ubicacion->sede)->toBe('San Mateo');

    // Sin custodio, el equipo queda en la unidad de su área («TTHH»).
    expect(BienInformatico::where('codigo_institucional', '002002')->first()->unidad_administrativa_id)->toBe($talentoHumano->id);

    // La segunda vez no duplica nada.
    $this->artisan('inventario:cargar-excel', ['archivo' => $archivo, '--responsable' => 'Jefe de TIC', '--informe' => informeTemporalTic()])->assertSuccessful();
    expect(BienInformatico::count())->toBe(4);
});

test('si las columnas se movieron la carga se detiene', function () {
    $archivo = excelDeCargaTic([[4 => 1, 5 => 'X Y', 8 => 'COMPUTADOR', 10 => 'HP']], [1 => 'PISO', 4 => 'ASIGNADO', 5 => 'ACTUAL', 12 => 'Serie', 13 => 'Fecha de Compra']);

    $this->artisan('inventario:cargar-excel', ['archivo' => $archivo, '--simular' => true, '--informe' => informeTemporalTic()])
        ->expectsOutputToContain('no tiene las columnas donde se esperan')
        ->assertFailed();
});
