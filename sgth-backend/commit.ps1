git add .
git commit -m "feat(dispensario): disponibilidad de medicos en tiempo real

- disponibilidad_medicos: tabla con estado por usuario
- DisponibilidadService: obtener/alternar/marcar no disponible/listar
- DisponibilidadController: mi-estado y alternar
- AuthController@logout: marca automaticamente no disponible
  al cerrar sesion
- User: relacion disponibilidadMedico()"
git push
