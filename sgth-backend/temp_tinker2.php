<?php
$srv = App\Models\Expediente\Servidor::first(); 
if ($srv) { 
    echo 'Servidor ID: ' . $srv->id . PHP_EOL; 
    echo 'User ID: ' . ($srv->user_id ?? 'SIN user_id') . PHP_EOL; 
    echo 'Puesto: ' . ($srv->puesto?->denominacion ?? 'SIN puesto') . PHP_EOL; 
} else { 
    echo 'No hay servidores en BD' . PHP_EOL; 
}
