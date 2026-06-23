php artisan tinker --execute="
`$turnos = app(App\Services\Dispensario\AgendaService::class)->listosParaConsulta(1);
echo json_encode(`$turnos->first()?->historia_clinica_id ?? 'SIN_TURNOS') . PHP_EOL;
"

Write-Host "--- GIT ---"
git add .
git commit -m "feat(dispensario): incluir historia_clinica_id resuelto en listosParaConsulta

El frontend necesita el ID de la historia clinica del
paciente para cargar el contexto de consulta (alergias,
antecedentes, triaje), pero AgendaMedica no lo expone
directamente (solo servidor_id o carga_familiar_id).
Se resuelve y se adjunta como atributo dinamico al turno
para evitar una llamada HTTP adicional en el frontend."
git push
