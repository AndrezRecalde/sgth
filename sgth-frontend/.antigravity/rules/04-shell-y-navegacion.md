# 04 · Shell y navegación

El shell es `src/components/layout/`. **Es infraestructura**: un módulo nunca
lo modifica para resolver una necesidad propia.

```
AppShell.tsx           orquesta; publica data-subsistema
├── GuardaDePantalla.tsx abre solo las pantallas del menú del usuario
├── Sidebar.tsx        marca, conmutador, menú
│   ├── SubsistemaSwitcher.tsx
│   ├── NavItem.tsx
│   └── NavItemNested.tsx
├── Topbar.tsx         control del menú, migas, buscador, cuenta
│   ├── AppBreadcrumbs.tsx
│   └── UserMenu.tsx
└── CommandPalette.tsx buscador de pantallas (Ctrl+K)
```

## Responsabilidades de `SGTHAppShell`

Solo cuatro, y ninguna más:

1. **Guardar la ruta.** Sin sesión rehidratada, a la pantalla de acceso. Es la
   segunda capa: el `matcher` del middleware ya cubre todo el área autenticada
   (ver [02](02-arquitectura.md)).
2. **Guardar la pantalla** con `GuardaDePantalla`: solo se abre la que el menú
   le ofrece al usuario (ver «Permisos y roles», abajo).
3. **Publicar el subsistema activo** como `data-subsistema`, de donde cuelga
   todo el acento visual.
4. **Coordinar el estado del sidebar** entre la barra superior y el menú.

### La hidratación va antes que la redirección

El store de auth se persiste en `localStorage` y **se rehidrata de forma
asíncrona**. En el primer render del cliente `isAuthenticated` es `false`
aunque haya sesión. Redirigir en ese momento echa a la calle a un usuario
válido.

Por eso existe `useHydrated()`, que lee la rehidratación con
`useSyncExternalStore`. Mientras no haya respuesta se pinta un cargador a
pantalla completa: ni contenido ni redirección.

No lo sustituyas por un temporizador. Eso es lo que había antes, y funcionaba
por casualidad del orden del bucle de eventos.

## Medidas

```
Barra superior      56px
Sidebar expandido  264px
Sidebar plegado     64px   solo iconos, con tooltip
Ancho de contenido 1600px  máximo, centrado
```

Están en `config/design.tokens.ts` (`LAYOUT`) y en `tokens.css`. Si necesitas
una de estas medidas en CSS, usa la variable; no la reescribas.

## Comportamiento responsive

```
≥ md (62em)   sidebar fijo; el usuario puede plegarlo a iconos.
              La preferencia se recuerda entre sesiones (ui.store).
< md          sidebar oculto; el burger lo abre sobre el contenido
              y se cierra solo al navegar.
```

## Reparto entre sidebar y barra superior

**El sidebar tiene la identidad y la navegación.** Marca, conmutador de
subsistema y menú. El conmutador vive aquí y no en la barra superior porque el
subsistema determina todo el menú que hay debajo.

**La barra superior tiene la orientación y las acciones globales.** Control del
menú, migas de pan, buscador, notificaciones y cuenta. No lleva logo —ya está
en el sidebar— ni interruptor de tema suelto: el modo oscuro vive en el menú de
cuenta, junto al resto de preferencias del usuario.

## El menú se declara, no se programa

`config/nav.ts` es la fuente única. De ahí salen **cinco** cosas:

1. el menú lateral,
2. las migas de pan (`findNavTrail`),
3. el buscador Ctrl+K (`flattenNav`),
4. los subsistemas a los que entra cada quien (`getSubsistemasDisponibles`)
   y por qué pantalla (`inicioDelSubsistema`),
5. qué pantallas puede abrir (`puedeAbrirPantalla`).

Agregar una pantalla al menú la hace navegable, rastreable, buscable y
protegida de una sola vez.

```ts
{
  label:   'Entregas de EPP',
  href:    ROUTES.SGTH.RIESGOS_LABORALES_ENTREGAS_EPP,
  icon:    'IconTruckDelivery',   // nombre de @tabler/icons-react, como texto
  permiso: 'gestionar-sso',       // la guarda: la misma que pide el API
}
```

Pasos para una pantalla nueva:

1. Ruta en `config/routes.ts`.
2. Entrada en el grupo correspondiente de `config/nav.ts`, **con su guarda**.
3. Carpeta y `page.tsx` bajo el route group del subsistema, colgando de la ruta
   de su entrada: una ruta de detalle (`/expediente/123`) la protege la entrada
   del listado.

## Estado activo — no se codifica a mano

Un destino está activo si es la coincidencia **más larga** con la ruta actual.
Ese cálculo vive en `Sidebar.tsx` y baja a los ítems como `isActive`.

Antes había listas de "rutas de coincidencia exacta" escritas a mano dentro de
`NavItem` y `NavItemNested`: cada pantalla anidada nueva obligaba a acordarse
de registrar a su padre, y olvidarlo dejaba dos ítems encendidos a la vez. **No
vuelvas a introducir listas de ese tipo.**

## Permisos y roles

- `buildNav(subsistema, permisos, roles)` filtra por `permiso` y por `roles`,
  **en los ítems y en sus `children`**. Hasta el 2026-09-04 solo miraba el
  primer nivel, así que una pantalla anidada con restricción se ofrecía a todo
  el mundo y quien la abría se encontraba un 403.
- Se usa `roles` cuando el backend protege la ruta por rol y no por permiso,
  que es el caso de todo el Dispensario. Basta con tener uno de los listados.
- Un ítem padre que se queda sin hijos visibles desaparece con ellos.
- **Toda pantalla lleva guarda**, propia o de su padre, y es la misma que pide
  su API. Desde el 2026-10-26 (`docs/plan-roles-y-accesos.md`, PR 9) una
  entrada sin guarda no es un descuido menor: le abre el subsistema entero a
  cualquier usuario.
- `getSubsistemasDisponibles(permisos, roles)`: se entra a un subsistema si se
  ve al menos una de sus pantallas. Antes lo decidían listas de roles por
  subsistema (`ROLES_SGTH`…), escritas aparte del menú y desfasadas de él. **No
  las vuelvas a introducir**: el menú ya es la respuesta.
- `inicioDelSubsistema(subsistema, permisos, roles)`: el `home` si el usuario
  lo tiene en el menú, o si no es una entrada del menú (la portada del
  Portal); si no, la primera pantalla visible. El SGTH no tiene pantalla de
  inicio y siempre entra por la primera.
- `GuardaDePantalla` pinta «No tiene acceso» en una ruta cuyo destino del menú
  completo (el más largo que la contiene) no está en el menú del usuario. Una
  ruta que no cuelga de ninguno no se juzga. En el `home` de un subsistema
  lleva al inicio que corresponda.

Lo mismo vale para lo que el menú dispara de fondo: el contador de stock bajo
del sidebar se pedía en los tres subsistemas y devolvía 403 a quien no es del
dispensario. Va tras `ROLES_INVENTARIO_MED`.

Ni el menú ni la guarda de pantalla **son** control de acceso a los datos. El
backend sigue siendo quien autoriza cada petición.

## Subsistemas

`config/subsistemas.ts` es la única definición: nombre, descripción, icono,
color, ruta de inicio y prefijo. Antes esta tabla estaba duplicada en dos
componentes, y una de las dos copias estaba muerta.
