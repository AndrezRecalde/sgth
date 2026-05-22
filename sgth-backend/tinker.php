<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "--- TAREA 1 ---\n";
App\Models\Estructura\UnidadAdministrativa::select('id','nombre','nivel','unidad_padre_id')
  ->orderBy('nivel')->orderBy('nombre')
  ->get()
  ->each(fn($u) => print($u->nivel.' | '.$u->nombre.' | padre:'.($u->unidad_padre_id ?? 'NULL').PHP_EOL));

echo "\n--- TAREA 2 ---\n";
$cols = Illuminate\Support\Facades\Schema::getColumnListing('puestos');
echo implode(', ', $cols);
echo "\n";
