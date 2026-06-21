php artisan config:clear
php artisan cache:clear

Write-Host "--- TINKER NOW() ---"
php artisan tinker --execute="echo now()->toDateString() . PHP_EOL; echo now()->timezone . PHP_EOL; echo now()->format('Y-m-d H:i:s') . PHP_EOL;"

Write-Host "--- DOCKER PGSQL ---"
docker exec sgth_postgres psql -U postgres -d sgth_desarrollo -c "UPDATE agendas_medicas SET fecha = '2026-06-20' WHERE folio = 'TUR-2026-00003';"

Write-Host "--- GIT ---"
git add .
git commit -m "fix(core): configurar zona horaria America/Guayaquil en toda la aplicacion

PROBLEMA: Laravel usaba UTC por defecto. Como Ecuador es
UTC-5, despues de las 19:00 hora local el servidor ya
contaba como 'mañana', causando que los turnos del
dispensario se registraran con la fecha incorrecta.

SOLUCION:
- config/app.php: timezone lee de APP_TIMEZONE (env)
- .env: APP_TIMEZONE=America/Guayaquil
- Afecta a now(), today(), Carbon::now() en TODO el sistema
  (viaticos, permisos, asistencia, dispensario, etc) de
  forma centralizada"
git push
