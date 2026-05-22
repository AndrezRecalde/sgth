<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$u = App\Models\Estructura\UnidadAdministrativa::with(['tipoUnidad','hijas'])->whereNull('unidad_padre_id')->first();
if ($u) {
    echo json_encode(array_keys($u->toArray()));
} else {
    echo "null";
}
