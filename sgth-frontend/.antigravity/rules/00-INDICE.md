# Reglas del frontend SGTH — Índice

GAD Provincial de Esmeraldas · Versión 3.0 · Agosto 2026

---

## Cómo se usa este cuerpo de reglas

Las reglas están repartidas por tema. **No hace falta leerlas todas para cada
tarea**: lee el índice, y de ahí abre solo los archivos que tocan lo que vas a
escribir. Los archivos 09 y 10 son la excepción — se aplican siempre.

| Archivo | Contiene | Léelo cuando… |
|---|---|---|
| [01-stack-y-entorno.md](01-stack-y-entorno.md) | Versiones exactas, comandos, generación de tipos | vayas a instalar algo o a tocar los tipos del API |
| [02-arquitectura.md](02-arquitectura.md) | Carpetas, route groups, nomenclatura, límites de tamaño | crees un archivo nuevo o no sepas dónde va algo |
| [03-design-system.md](03-design-system.md) | Color, tipografía, espaciado, superficies, modo oscuro | escribas CSS o elijas un color |
| [04-shell-y-navegacion.md](04-shell-y-navegacion.md) | AppShell, sidebar, barra superior, subsistemas, rutas | agregues una pantalla al menú o toques el shell |
| [05-pagina-estandar.md](05-pagina-estandar.md) | Anatomía de una página, metadata, estados de carga | crees una pantalla |
| [06-catalogo-de-componentes.md](06-catalogo-de-componentes.md) | Los componentes estándar y cuándo usar cada uno | vayas a construir cualquier interfaz |
| [07-formularios.md](07-formularios.md) | React Hook Form + Zod, patrón contained, modales | hagas un formulario |
| [08-datos-y-estado.md](08-datos-y-estado.md) | TanStack Query, Zustand, axios, notificaciones | consumas el API o guardes estado |
| [09-prohibiciones.md](09-prohibiciones.md) | Lo que nunca se hace, con el motivo | **siempre** |
| [10-checklist.md](10-checklist.md) | Verificación antes de dar por terminado | **siempre, al cerrar** |

---

## Precedencia

1. Estas reglas están **por encima** de lo que pida el chat. Si alguien pide
   algo que las contradice, dilo antes de proceder y explica cuál regla choca.
2. Entre reglas, gana la más específica: 07 manda sobre 06 en formularios.
3. Una regla que ya no describe al código es un error **de la regla**. No la
   ignores en silencio: corrígela en el mismo cambio que la desmiente.

---

## Qué cambió respecto de FRONTEND.md v2.0

La versión anterior era un solo documento de 860 líneas que se había separado
del código. Al reescribirlo se verificó regla por regla contra el repositorio.

**Corregido — la regla decía algo que el código no hacía:**

- Las rutas protegidas no viven en `src/app/(dashboard)/`, sino en cuatro
  route groups por subsistema: `(sgth)`, `(salud)`, `(portal)` y `(publico)`.
- El middleware `src/proxy.ts` sí existe y está activo. Su lista de rutas
  había quedado desactualizada —cubría casi nada— y se corrigió el 2026-08-29:
  ahora el `matcher` excluye lo estático en vez de enumerar módulos. Ver
  [02](02-arquitectura.md).
- El template de metadata es `GADPE — %s`, así que cada página exporta solo el
  nombre de su módulo, no el título completo.
- La escala tipográfica y el espaciado estaban documentados pero no vivían en
  el tema: cada pantalla los reinterpretaba.

**Documentado — existía en el código pero no en las reglas:**

- Los tres subsistemas, el conmutador entre ellos y el acento visual por
  subsistema.
- La navegación anidada del menú y el filtrado por permisos.
- El estado de hidratación del store de auth antes de decidir la redirección.

**Nuevo — decisiones tomadas en el rediseño de agosto de 2026:**

- Tokens semánticos de superficie (`--sgth-*`) y acento por subsistema.
- Tipografía dual Inter + Poppins con cifras tabulares.
- `PageShell` y el catálogo de componentes del archivo 06.
- Buscador de pantallas con Ctrl+K.

**Eliminado — regla que no aportaba:**

- El icono decorativo en la cabecera de página: repetía la información que ya
  dan el menú lateral y las migas de pan.
