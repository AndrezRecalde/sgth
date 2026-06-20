php artisan migrate
php artisan db:seed --class=CatalogoServicioEnfermeriaSeeder

Write-Host "--- RUTAS ---"
php artisan route:list --path=dispensario | Select-String "atenciones-enfermeria|catalogo-servicios|agenda"

Write-Host "--- DOCKER DB ---"
docker exec sgth_postgres psql -U postgres -d sgth_desarrollo -c "SELECT id, nombre FROM catalogo_servicios_enfermeria;"

Write-Host "--- GIT ---"
git add .
git commit -m "feat(dispensario): cola de turnos sin horario fijo y atenciones de enfermeria

FASE 2 - Refactor AgendaMedica:
- folio TUR-AAAA-00000 autogenerado
- tipo_atencion (medicina_general/odontologia) en vez de
  filtrar por rol de personal
- elimina horario fijo, usa registrado_en para orden de cola
- motivo_solicitud ahora opcional

FASE 3 - Servicio de enfermeria sin turno medico:
- catalogo_servicios_enfermeria: catalogo predefinido
  (inyeccion, curacion, signos vitales, etc)
- atenciones_enfermeria: registro simple con folio ENF-AAAA-00000
- AtencionEnfermeriaService/Controller: registrar y listar
- Seeder con 10 servicios comunes"
git push
