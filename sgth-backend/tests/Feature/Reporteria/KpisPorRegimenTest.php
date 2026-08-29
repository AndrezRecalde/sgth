<?php

use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Services\Reporteria\ReporteriaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * El KPI de servidores por régimen contaba con 'LOSEP' y 'CODIGO_TRABAJO' en
 * mayúsculas contra una columna que guarda minúsculas, así que devolvía cero
 * en los dos desde siempre. Y solo tenía dos claves: el tercer régimen no
 * aparecía en ninguna parte.
 */
beforeEach(function () {
    Cache::flush();
    Servidor::unguard();

    $crear = fn (RegimenLaboral $regimen, string $cedula) => Servidor::create([
        'cedula'   => $cedula,
        'nombre'   => 'Prueba',
        'apellido' => 'KPI',
        'regimen_laboral' => $regimen,
        'estado'   => true,
    ]);

    $crear(RegimenLaboral::LOSEP, '0800000201');
    $crear(RegimenLaboral::LOSEP, '0800000202');
    $crear(RegimenLaboral::CODIGO_TRABAJO, '0800000203');
    $crear(RegimenLaboral::SERVICIOS_PROFESIONALES, '0800000204');
});

test('el KPI cuenta los tres regímenes', function () {
    $kpis = app(ReporteriaService::class)->obtenerKpisDashboard();

    expect($kpis['personal']['servidores_por_regimen'])->toBe([
        'losep'                   => 2,
        'codigo_trabajo'          => 1,
        'servicios_profesionales' => 1,
    ]);
});

test('un régimen nuevo aparecería solo', function () {
    // El KPI se arma recorriendo el enum: la lista de claves no se mantiene a
    // mano y no puede quedarse corta otra vez.
    $kpis = app(ReporteriaService::class)->obtenerKpisDashboard();

    expect(array_keys($kpis['personal']['servidores_por_regimen']))
        ->toBe(array_column(RegimenLaboral::cases(), 'value'));
});
