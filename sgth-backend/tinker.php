<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$response = app()->make(\App\Contracts\Estructura\EstructuraServiceInterface::class);
$paginador = $response->listarPuestos([]);
echo json_encode([
    'current_page' => $paginador->currentPage(),
    'total'        => $paginador->total(),
    'per_page'     => $paginador->perPage(),
    'items_count'  => count($paginador->items()),
    'first_item'   => $paginador->items()[0] ?? null,
], JSON_PRETTY_PRINT);
echo "\n";
