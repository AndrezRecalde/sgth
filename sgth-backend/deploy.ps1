Write-Host "--- RUTAS ---"
php artisan route:list --path=agenda

Write-Host "--- GIT ---"
git add .
git commit -m "feat(dispensario): endpoint de turnos listos para consulta del medico

- AgendaService::listosParaConsulta: turnos del medico
  autenticado que estan en estado en_espera/en_sala y
  ya tienen su triaje (o no lo requieren)
- Cie10Seeder ejecutado: 8918 codigos CIE-10 cargados"
git push
