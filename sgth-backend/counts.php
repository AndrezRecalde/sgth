<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'grupos_ocupacionales: '.App\Models\Estructura\GrupoOcupacional::count().PHP_EOL;
echo 'unidades_administrativas: '.App\Models\Estructura\UnidadAdministrativa::count().PHP_EOL;
echo 'partidas_presupuestarias: '.App\Models\Estructura\PartidaPresupuestaria::count().PHP_EOL;
