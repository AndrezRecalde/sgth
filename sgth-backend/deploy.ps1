php artisan route:list --path=historias-clinicas

Write-Host "--- GIT ---"
git add .
git commit -m "feat(dispensario): corregir relacion receta a hasMany y endpoint de contexto de consulta

PROBLEMA 1: ConsultaMedica.recetaMedica() era hasOne pero
clinicamente un medico puede emitir varias recetas en una
misma consulta. Renombrado a recetasMedicas() hasMany.

PROBLEMA 2: el frontend necesitaria 4 llamadas separadas
(historia, alergias, antecedentes, triaje) para mostrar
el contexto completo al medico durante la consulta.

SOLUCION: nuevo endpoint
GET /dispensario/historias-clinicas/{id}/contexto-consulta
devuelve en una sola llamada: datos del paciente, alergias,
antecedentes, triaje del turno actual (si se pasa
agenda_medica_id) y las ultimas 3 consultas anteriores."
git push
