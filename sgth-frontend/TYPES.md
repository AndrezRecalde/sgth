# Regenerar tipos TypeScript

Los tipos TypeScript se generan automáticamente
desde el OpenAPI del backend con Scramble.

## Regeneración rápida (solo tipos)

Si ya tienes el openapi.yaml actualizado:

npm run types:generate

## Regeneración completa (backend + frontend)

Requiere tener el backend corriendo en Laragon:

npm run types:sync

## ¿Cuándo regenerar?

Regenera los tipos cuando:
- Se agrega un nuevo endpoint al backend
- Se modifica un Form Request existente
- Se agrega un nuevo modelo con ApiResource
- Se cambia la estructura de respuesta de un endpoint

## Archivo generado

src/types/api.generated.ts — NO editar manualmente
src/types/api.ts — tipos de conveniencia editables
