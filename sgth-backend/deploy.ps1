Write-Host "--- RUTAS ---"
php artisan route:list --path=triaje

Write-Host "--- GIT ---"
git add .
git commit -m "feat(dispensario): endpoint para consultar el ultimo triaje del paciente

- TriajeController@ultimoPorAgenda: busca el triaje mas
  reciente del paciente (por historia clinica) excluyendo
  la agenda actual, para mostrarlo como referencia al
  registrar un nuevo triaje"
git push
