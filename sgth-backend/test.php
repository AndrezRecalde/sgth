<?php $user = App\Models\User::find(1); echo json_encode($user->toArray()['nombre_completo'] ?? 'NO_APARECE') . PHP_EOL;
