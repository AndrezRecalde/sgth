<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo Illuminate\Support\Facades\Schema::hasColumn('servidores', 'correo_institucional') ? 'SI' : 'NO';
echo "\n";
