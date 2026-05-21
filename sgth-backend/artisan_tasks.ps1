echo "Migrando..."
php artisan migrate

echo "Seeding..."
php artisan db:seed --class=RolPermisoSeeder

echo "Exportando OpenAPI..."
php artisan scramble:export --path=storage/app/openapi.yaml

echo "Listando Rutas..."
php artisan route:list --path=expediente/servidores | Select-String -Pattern "historial|cargas|declaraciones"

echo "Commit y push..."
git add .
git commit -m "feat(expediente): historial académico, cargas familiares y declaraciones juramentadas

- Enums: TipoEstudio, NivelEstudio, NacionalidadEstudio,
  TipoParentesco, TipoDeclaracion
- Migraciones: historial_academico_servidor, cargas_familiares,
  discapacidades_carga_familiar, enfermedades_catastroficas_carga_familiar,
  declaraciones_juramentadas
- Modelos: HistorialAcademicoServidor, CargaFamiliar,
  DiscapacidadCargaFamiliar, EnfermedadCatastroficaCargaFamiliar,
  DeclaracionJuramentada
- Servidor: relaciones historialAcademico, cargasFamiliares,
  declaracionesJuramentadas
- Controladores: HistorialAcademicoController,
  CargaFamiliarController, DeclaracionJuramentadaController
- Exportación formato Contraloría Ecuador (txt y PDF)
- Vista Blade: exports/declaraciones-contraloria
- Permiso: GESTIONAR_CARGAS_FAMILIARES en ADMIN_UATH y ASISTENTE_UATH
- Rutas registradas bajo /expediente/servidores/{servidorId}/"

git push
