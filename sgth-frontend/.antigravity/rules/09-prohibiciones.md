# 09 · Prohibiciones

Cada una tiene un motivo. Se aplican siempre, sin importar el módulo.

## Tipado

**Nunca `any`.**
Apaga el compilador justo donde más hace falta.

**Nunca `as unknown as T`.**
Es peor que `any`: hace creer que hay un tipo. Si aparece la necesidad, el
problema real es que `types/api.ts` no coincide con lo que devuelve el
backend — corrígelo ahí.

```ts
// no
(servidor as unknown as { id: number }).id
data as unknown as PuestoConRelaciones[]

// sí — arreglar el tipo en api.ts, y entonces
servidor.id
```

**No queda ninguno en el repositorio** (los 17 que había se eliminaron el
2026-08-27). Es una invariante, no una meta: `grep -rn "as unknown as" src`
debe salir vacío.

Lo que apareció debajo de aquellos 17, por si el patrón se repite:

- **Un tipo que Scramble no logró inferir.** `Servidor` es
  `components['schemas']['ServidorResource']`, y en el generado eso es
  literalmente `unknown[]`: no es un objeto. De ahí que `ServidorConRelaciones`
  esté escrito a mano. Le pasa a cuatro recursos —`ServidorResource`,
  `AccidenteTrabajoResource`, `EquipoProteccionResource` y
  `RiesgoLaboralResource`—, todos con el mismo patrón de `parent::toArray()`
  más `whenLoaded()` sueltos.
- **Una relación que el endpoint carga pero el tipo no declara.** Se agrega a
  `api.ts` leyendo la lista de `with()` del controlador, no adivinando.
- **Un accesor de Eloquent que no está en `$appends` y por lo tanto no viaja.**
  `Puesto::rmu` llevaba meses llegando `undefined` al frontend y la aserción lo
  tapaba: el cierre de vínculo nunca heredó la remuneración LOSEP. Aquí el
  arreglo va en el backend, no en `api.ts`.
- **Valores iniciales de formulario tipados con el tipo de _salida_ del esquema
  Zod.** Un `undefined as unknown as number` para un campo que todavía no se
  elige se resuelve con `DefaultValues<T>` de React Hook Form omitiendo la
  clave, y con `resetField()` en vez de `setValue(campo, undefined)`.

## Estilos

**Nunca colores en hexadecimal dentro de un componente.**
Se rompen en modo oscuro y quedan fuera del sistema. Los hex viven solo en
`config/design.tokens.ts`. En CSS se usan los tokens `--sgth-*`; en JSX, las
props de color de Mantine.

La **única excepción** son las opciones de ECharts, que pintan sobre canvas y
necesitan colores resueltos: van por `useEChartsColors()`
(ver [03](03-design-system.md)). El otro hexadecimal legítimo del repositorio
es el `themeColor` del viewport en `app/layout.tsx`, que consume el navegador
antes de que exista una hoja de estilos.

**Nunca Tailwind.** El sistema es Mantine + CSS Modules.

**Nunca `styles={{}}` para algo reutilizable.**
Se copia y pega, y la próxima vez que cambie hay que cazar diez copias. Va a un
CSS Module. `style={{}}` puntual para un valor calculado sí es aceptable.

**Nunca un color pensado para un solo esquema.** Ver [03](03-design-system.md).

## Componentes

**Nunca una tabla HTML ni `DataTable` directo.** Se usa `SgthTable`.

**Nunca iconos de acción sueltos en una fila.** Se usa `TableActions`.

**Nunca emojis como iconos.** No escalan, no heredan color, se ven distinto en
cada sistema operativo y no tienen nombre accesible. Todo icono sale de
`@tabler/icons-react`.

**Nunca `@mantine/charts` para gráficas de datos.** El estándar es ECharts.

**Nunca un campo sin `label` visible.**

**Nunca el `confirm()` del navegador.** Se usa `confirmar()`.

## Datos

**Nunca `fetch` nativo.** Se usa `axios` desde `@/lib/axios`.

**Nunca `useState` para datos del API.** Se usa TanStack Query.

**Nunca Context API para estado global.** Se usa Zustand.

**Nunca un `clearAuth()` que no borre las cookies de sesión.**

## Formularios

**Nunca `@mantine/form` en un formulario nuevo.** El estándar es React Hook
Form + Zod.

**Nunca importar desde `zod`.** Se importa desde `zod/v4`.

**Nunca `register` en un componente que no envuelva un input nativo.** Ahí va
`Controller`.

## Enrutado

**Nunca Pages Router.** Solo App Router.

**Nunca una URL escrita a mano en el JSX.** Va en `config/routes.ts`.

**Nunca `'use client'` en un `page.tsx` que exporte `metadata`.** Son
incompatibles; la lógica va a una vista hermana.

## Recursos

**Nunca un recurso servido desde un dominio externo.**
El logo del sidebar se cargaba desde el sitio web institucional: eso rompe la
aplicación si el sitio cambia la ruta, no funciona sin salida a internet y
esquiva la optimización de imágenes de Next. Los recursos van en `public/`.

**Nunca `import '@mantine/…/styles.css'` dentro de un componente.**
Estaba repetido en 53 archivos. Todas las hojas de paquete se importan una sola
vez, en `src/app/layout.tsx`, y son exactamente estas seis:

```
@mantine/core   ·  @mantine/dates  ·  @mantine/dropzone
@mantine/tiptap ·  @mantine/notifications  ·  mantine-datatable
```

Si falta una, el componente correspondiente aparece **sin estilo alguno** y no
da ningún error: el calendario se ve como una lista de números sueltos y la
zona de carga como un recuadro vacío. Comprobación rápida en el navegador —
cada paquete deja su propia variable CSS:

```js
// true en los seis casos si todas las hojas están cargadas
['--input-height','--day-size','--dropzone','--rte','--notification']
```

## Efectos

**Nunca `setState` en el cuerpo de un efecto.** Ver [08](08-datos-y-estado.md).

**Nunca un temporizador para esperar la hidratación.** Se usa `useHydrated()`.

## Shell

**Nunca listas de rutas de coincidencia exacta escritas a mano** para decidir
el ítem activo del menú. Ver [04](04-shell-y-navegacion.md).

**Nunca duplicar la configuración de subsistemas.** Vive en
`config/subsistemas.ts`.

## Sobre el código muerto

Un componente que ya nadie usa, un mapa de colores que nadie lee, un store que
nadie consulta: se borran en el mismo cambio en que se detectan. Todos los
ejemplos citados arriba estaban en el repositorio en agosto de 2026, y ninguno
se puso ahí a propósito.
