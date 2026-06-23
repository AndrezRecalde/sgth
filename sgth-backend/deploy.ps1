php artisan route:list --path=inventario

Write-Host "--- GIT ---"
git add .
git commit -m "feat(dispensario): reconstruir fundacion real del inventario de medicinas

PROBLEMA: InventarioMedicinasController era un stub
(index/update/destroy/kardex devolvian arrays vacios
hardcodeados), sin FormRequests de validacion, sin
busqueda real. La tabla estaba vacia y nada funcionaba.

SOLUCION:
- StoreInventarioMedicinaRequest/UpdateInventarioMedicinaRequest
- InventarioMedicinasService: listar con filtros (busqueda,
  estado, stock bajo), obtener, buscar (para autocompletado
  en recetas), actualizar, ingresarStock (kardex separado
  de edicion de datos), darDeBaja (toggle, no elimina)
- InventarioMedicinasController: todos los metodos reales
- Rutas: medicinas/buscar (antes del apiResource para evitar
  conflicto), medicinas/{id}/ingresar-stock"
git push
