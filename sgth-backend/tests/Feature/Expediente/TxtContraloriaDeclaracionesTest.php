<?php

use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\DeclaracionJuramentada;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * El TXT de declaraciones juramentadas para Contraloría lleva, en cada línea,
 * el vínculo y el cargo de ESA declaración.
 *
 * Hasta el 2026-10-03 usaba el contrato vigente hoy para todas —una de fin de
 * gestión de quien ya salió iba sin nombramiento ni contrato— y el cargo salía
 * siempre vacío: leía `puesto->nombre`, una columna que no existe.
 */

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $uath = User::factory()->create();
    $uath->assignRole('admin-uath');
    $this->actingAs($uath, 'sanctum');

    $unidad = unidadDePrueba(['nombre' => 'Unidad Contraloría']);
    $this->servidor = Servidor::forceCreate([
        'cedula' => '0804040401', 'nombre' => 'Inés', 'apellido' => 'Declarante',
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => 'losep', 'estado' => false,
    ]);

    // Dos vínculos sucesivos, y ninguno vigente hoy: ya salió.
    $vinculo = fn (string $modalidad, string $cargo, string $desde, string $hasta) => ContratoServidor::create([
        'servidor_id' => $this->servidor->id, 'tipo_nombramiento' => $modalidad,
        'unidad_administrativa_id' => $unidad->id,
        'puesto_id' => puestoDePrueba($unidad, $cargo)->id,
        'fecha_inicio' => $desde, 'fecha_fin' => $hasta, 'estado' => 'terminado',
    ]);
    $vinculo('nombramiento_permanente', 'Analista de Talento Humano', '2018-01-01', '2022-12-31');
    $vinculo('servicios_ocasionales', 'Técnico de Archivo', '2023-01-01', '2025-12-31');

    $declarar = fn (string $tipo, string $fecha, string $codigo) => DeclaracionJuramentada::create([
        'servidor_id' => $this->servidor->id, 'tipo_declaracion' => $tipo,
        'fecha_declaracion' => $fecha, 'codigo_barras' => $codigo,
    ]);
    $declarar('inicio_gestion', '2017-12-20', 'A1');   // antes de entrar
    $declarar('periodica', '2020-05-10', 'A2');        // durante el primero
    $declarar('fin_gestion', '2026-01-10', 'B9');      // después de salir
});

test('cada línea lleva el vínculo y el cargo de su declaración', function () {
    $txt = $this->get(
        "/api/v1/expediente/servidores/{$this->servidor->id}/declaraciones-juramentadas/exportar"
            . '?fecha_inicio=2017-01-01&fecha_fin=2026-12-31&formato=txt',
    )->assertOk()->getContent();

    $lineas = array_map(fn ($l) => explode('|', $l), explode("\n", $txt));
    // [3] nombramiento, [4] contrato, [5] tipo, [6] cargo, [7] código
    $campos = fn (array $l) => [$l[3], $l[4], $l[5], $l[6], $l[7]];

    // Con tilde: `strtoupper` dejaba «INéS».
    expect($lineas[0][2])->toBe('INÉS');

    expect(array_map($campos, $lineas))->toBe([
        ['PERMANENTE', '', 'INICIO DE GESTION', 'ANALISTA DE TALENTO HUMANO', 'A1'],
        ['PERMANENTE', '', 'PERIODICA', 'ANALISTA DE TALENTO HUMANO', 'A2'],
        ['', 'SERVICIOS OCASIONALES', 'FIN DE GESTION', 'TÉCNICO DE ARCHIVO', 'B9'],
    ]);
});
