echo "=== MIGRATE ==="
php artisan migrate

echo "=== SEEDERS ==="
php artisan db:seed --class=GrupoOcupacionalSeeder
php artisan db:seed --class=UnidadAdministrativaSeeder
php artisan db:seed --class=PartidaPresupuestariaSeeder
php artisan db:seed --class=RolPermisoSeeder

echo "=== COUNTS ==="
$script = "require __DIR__.'/vendor/autoload.php'; \$app = require_once __DIR__.'/bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo 'grupos_ocupacionales: '.App\Models\Estructura\GrupoOcupacional::count().PHP_EOL; echo 'unidades_administrativas: '.App\Models\Estructura\UnidadAdministrativa::count().PHP_EOL; echo 'partidas_presupuestarias: '.App\Models\Estructura\PartidaPresupuestaria::count().PHP_EOL;"
php -r $script

echo "=== SCRAMBLE ==="
php artisan scramble:export --path=storage/app/openapi.yaml

echo "=== GIT ==="
git add .
git commit -m "feat(estructura): grupos ocupacionales, reestructurar puestos, organigrama GADPE, partidas presupuestarias

- Enums: NivelComplejidadPuesto, RolPuesto
- GrupoOcupacional: tabla completa LOSEP (SP1-SP9, NJS, SPA, SPS) + CT referencial
- GrupoOcupacionalSeeder: 21 grupos con RMU real del Manual GADPE 2026
- Puestos reestructurada: FK grupo_ocupacional_id, plazas, rol_puesto,
  nivel_complejidad, regimen_laboral, mision
- Puestos: eliminados grupo_ocupacional(string), grado_rmu, rmu
- Modelo Puesto: plazasOcupadas(), plazasDisponibles(), tieneVacantes(), getRmu()
- UnidadAdministrativaSeeder: organigrama completo GADPE con subprocesos
- PartidaPresupuestaria: catálogo referencial grupo 51 + 53 (integración ERP)
- unidad_partida_presupuestaria: tabla pivote por año fiscal"

git push
