php artisan migrate

Write-Host "--- RUTAS TRIAJE ---"
php artisan route:list --path=triaje

Write-Host "--- GIT ---"
git add .
git commit -m "fix(dispensario): agregar requiere_triaje y corregir bugs en TriajeController

- agendas_medicas: nueva columna requiere_triaje (default true)
- AgendaMedica: fillable/casts actualizados
- TriajeController: corrige referencia a beneficiario_id obsoleta,
  corrige estado_origen de programada a en_espera
- TriajeController@pendientes: lista turnos del dia que
  requieren triaje y aun no lo tienen"
git push
