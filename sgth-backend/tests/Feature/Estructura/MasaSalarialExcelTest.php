<?php

namespace Tests\Feature\Estructura;

use App\Exports\MasaSalarial\HojaMasaSalarial;
use App\Exports\MasaSalarial\MasaSalarialExport;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Estructura\MasaSalarialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| El Excel de la masa salarial (docs/diseno-masa-salarial.md, 5.4; PR 3): el
| resumen con el aviso y la fecha de corte, la matriz de unidad por régimen y
| el detalle de cada vínculo. Hoy es el martes 17 de noviembre de 2026.
|
|   Prefectura ─ Dirección de Obras
|              └ Dirección Administrativa
*/
beforeEach(function () {
    foreach (['admin-uath', 'auditor', 'financiero', 'servidor'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    }
    (require database_path('migrations/2026_12_29_000000_permiso_masa_salarial.php'))->up();
    $this->travelTo(Carbon::parse('2026-11-17 10:00'));

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->prefectura = unidadDePrueba(['nombre' => 'Prefectura', 'nivel' => 1]);
    $this->obras = unidadDePrueba(['nombre' => 'Dirección de Obras', 'nivel' => 2, 'unidad_padre_id' => $this->prefectura->id]);
    $this->administrativa = unidadDePrueba(['nombre' => 'Dirección Administrativa', 'nivel' => 2, 'unidad_padre_id' => $this->prefectura->id]);

    $this->contador = 0;
    $this->servidor = function (UnidadAdministrativa $unidad, array $contrato, string $cedula): Servidor {
        $this->contador++;
        $puesto = puestoDePrueba($unidad, 'Analista '.$this->contador);
        $s = Servidor::create([
            'cedula' => $cedula, 'nombre' => 'Servidor', 'apellido' => 'Excel'.$this->contador,
            'regimen_laboral' => 'losep', 'fecha_ingreso_institucion' => '2018-01-02', 'estado' => true,
            'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        ]);
        ContratoServidor::create([
            'servidor_id' => $s->id, 'tipo_nombramiento' => 'nombramiento_permanente',
            'unidad_administrativa_id' => $unidad->id, 'puesto_id' => $puesto->id,
            'fecha_inicio' => '2018-01-02', 'estado' => 'vigente', 'remuneracion' => 1000,
            ...$contrato,
        ]);

        return $s;
    };

    // 2234.50 en LOSEP, 600 en Código del Trabajo y 1600 en honorarios.
    ($this->servidor)($this->obras, ['remuneracion' => 1234.5], '0812345607');
    ($this->servidor)($this->obras, ['tipo_nombramiento' => 'codigo_trabajo', 'remuneracion' => 600], '0812345608');
    ($this->servidor)($this->administrativa, ['tipo_nombramiento' => 'servicios_profesionales', 'remuneracion' => 1600, 'fecha_fin' => '2026-12-31'], '0812345609');
    ($this->servidor)($this->prefectura, ['remuneracion' => 1000], '0812345610');

    /** Las filas de cada hoja, por su nombre. */
    $this->hojas = fn (array $filtros = []) => collect(
        (new MasaSalarialExport(app(MasaSalarialService::class)->al(Carbon::parse('2026-11-17'), $filtros)))->sheets()
    )->mapWithKeys(fn (HojaMasaSalarial $h) => [$h->title() => $h->array()]);
});

test('el resumen lleva el aviso, la fecha de corte y las mismas cifras que la pantalla', function () {
    $hojas = ($this->hojas)();

    expect($hojas->keys()->all())->toBe(['Resumen', 'Unidad por régimen', 'Detalle']);

    $resumen = collect($hojas['Resumen']);
    expect($resumen[0][0])->toBe('Masa salarial referencial al 17/11/2026: resumen')
        ->and($resumen[1][0])->toBe(MasaSalarialService::AVISO)
        ->and($resumen[2][0])->toBe('Toda la institución, sin filtros.');

    $fila = fn (string $etiqueta) => $resumen->first(fn (array $f) => ($f[0] ?? null) === $etiqueta);
    expect($fila('Relación de dependencia'))->toEqual(['Relación de dependencia', 3, 2834.5, 34014, 63.9, 944.83])
        ->and($fila('Honorarios de Servicios Profesionales'))->toEqual(['Honorarios de Servicios Profesionales', 1, 1600, 19200, 36.1, 1600])
        ->and($fila('Total general'))->toEqual(['Total general', 4, 4434.5, 53214, 100, 1108.63])
        ->and($fila('Nombramiento Provisional'))->toEqual(['Nombramiento Provisional', 0, 0, 0, 0, 0])
        ->and($fila('LOSEP'))->toEqual(['LOSEP', 2, 2234.5, 26814, 50.4, 1117.25])
        ->and($fila('Servidores activos sin vínculo vigente'))->toBe(['Servidores activos sin vínculo vigente', 0]);
});

test('la matriz suma por régimen lo que cuelga de cada unidad, en el orden del árbol', function () {
    $matriz = ($this->hojas)()['Unidad por régimen'];

    expect($matriz[4])->toBe(['Unidad administrativa', 'LOSEP', 'Código del Trabajo', 'Servicios Profesionales', 'Total', 'Personas'])
        ->and(array_slice($matriz, 5))->toEqual([
            ['Prefectura', 2234.5, 600, 1600, 4434.5, 4],
            ['    Dirección de Obras', 1234.5, 600, 0, 1834.5, 2],
            ['    Dirección Administrativa', 0, 0, 1600, 1600, 1],
        ]);
});

test('los filtros se dicen en el encabezado', function () {
    $resumen = ($this->hojas)(['regimen' => \App\Enums\RegimenLaboral::LOSEP, 'unidad_id' => $this->obras->id])['Resumen'];

    expect($resumen[2][0])->toBe('Solo: régimen LOSEP · Dirección de Obras, con lo que cuelga de ella.');
});

test('se descarga con la fecha en el nombre, la cédula como texto y los montos como números', function () {
    $respuesta = $this->get('/api/v1/estructura/masa-salarial/excel?corte=2026-11-16')->assertOk();

    expect($respuesta->headers->get('content-disposition'))->toContain('masa_salarial_2026-11-16.xlsx');

    $libro = IOFactory::load($respuesta->baseResponse->getFile()->getPathname());
    $detalle = $libro->getSheetByName('Detalle');
    $cedula = $detalle->getCell('A2');

    expect($cedula->getValue())->toBe('0812345607')
        ->and($cedula->getDataType())->toBe(DataType::TYPE_STRING)
        ->and($detalle->getCell('H2')->getValue())->toEqual(1234.5)
        ->and($detalle->getStyle('H2')->getNumberFormat()->getFormatCode())->toBe('#,##0.00')
        ->and($libro->getSheetByName('Resumen')->getMergeCells())->toHaveKey('A2:F2');

    // Las negritas van por número de fila: con una fila en blanco que no se
    // escribía, caían en la de al lado. Se mira en el archivo, no en el arreglo.
    $resumen = $libro->getSheetByName('Resumen');
    $negrita = function (string $etiqueta) use ($resumen): bool {
        foreach ($resumen->getRowIterator() as $fila) {
            if ($resumen->getCell("A{$fila->getRowIndex()}")->getValue() === $etiqueta) {
                return $resumen->getStyle("A{$fila->getRowIndex()}")->getFont()->getBold();
            }
        }

        throw new \RuntimeException("No está la fila «{$etiqueta}».");
    };

    expect($negrita('Totales'))->toBeTrue()
        ->and($negrita('Total general'))->toBeTrue()
        ->and($negrita('Relación de dependencia'))->toBeFalse()
        ->and($negrita('Nombramiento Permanente'))->toBeFalse()
        ->and($negrita('Datos por completar'))->toBeTrue()
        ->and($resumen->getCell('A5')->getValue())->toBe('Totales');
});

test('el Excel lo protege el mismo permiso que la pantalla', function (string $rol, int $estado) {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol);

    $this->actingAs($usuario, 'sanctum')->get('/api/v1/estructura/masa-salarial/excel')->assertStatus($estado);
})->with([
    'financiero' => ['financiero', 200],
    'servidor'   => ['servidor', 403],
]);
