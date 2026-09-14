# 03 · Sistema de diseño

Tres capas, y cada una tiene un único dueño:

```
config/design.tokens.ts   escalas crudas (los hex viven SOLO aquí)
        ↓
config/mantine.theme.ts   el tema, que las publica como variables CSS
        ↓
styles/tokens.css         tokens semánticos --sgth-* que consume el CSS
```

Un componente **nunca** importa `design.tokens.ts`. Consume variables CSS.

## Color

### Escalas

| Escala | Uso |
|---|---|
| `primario` | El principal **del subsistema**: verde, azul o violeta según dónde se pinte |
| `emerald` | Verde institucional, principal de SGTH y color del éxito |
| `ocean` | Principal del Dispensario Médico y color de lo informativo |
| `amethyst` | Principal del Portal del Servidor |
| `slate` | Neutro: superficies, bordes y texto en modo claro |
| `dark` | Neutro de modo oscuro (escala NIGHT, sustituye a la de Mantine) |
| `amber` | El único ámbar del sistema: advertencia |
| `red` | Error y acciones destructivas (la de Mantine) |

`primaryColor: 'primario'`, `primaryShade: { light: 6, dark: 8 }`. En claro, el
verde institucional usa el tono 7 para el relleno (ver abajo).

### Semánticos

El color se elige por **significado**, no por gusto:

```ts
success  → emerald      warning → amber
danger   → red          info    → ocean       neutral → slate
```

Se consumen a través de `StatusBadge`, `notificar` o el mapa `SEMANTIC_COLOR`.

### Solo la paleta

**ESLint rechaza cualquier color escrito a mano que no sea de la paleta**, en
`color`, `c` y `bg`, en propiedades `color:` de un objeto y en
`var(--mantine-color-*)` dentro de estilos:

```
permitido   primario emerald ocean amethyst amber red slate dark  (con tono: amber.7)
            dimmed inherit white currentColor
            var(--sgth-*)  var(--mantine-color-{esas escalas}-*)
rechazado   blue orange gray teal violet grape cyan yellow green …
```

Antes el mismo gesto salía de cinco colores: guardar en verde, azul, naranja,
turquesa o violeta; un aviso en naranja o amarillo; un gris que se veía bien
en claro y desaparecía en oscuro. Que un nombre esté permitido no lo vuelve
libre: se sigue eligiendo por significado.

| Qué | Color |
|---|---|
| Botón principal, `Switch`, `Chip`, `Tabs`, `Stepper`, `Loader` | Ninguno: lo pone el tema, con el principal del subsistema |
| Texto que actúa como enlace («Registrar otra», «Crear medicina nueva») | `primario` |
| Botón o acción de fila que destruye | `red` |
| `ActionIcon` que no destruye | Ninguno: el tema lo pone gris sutil |
| `Alert` | `ocean` informa, `amber` advierte, `red` error, `emerald` éxito, `slate` neutro |
| Montos | `emerald` ingreso, `red` descuento o egreso |
| `ThemeIcon` o `Avatar` decorativo | **Ninguno**: toma el acento del subsistema |
| `ThemeIcon` que es un dato | Su color: ingreso/egreso en el kardex, oro/plata/bronce del ranking |
| Resaltar una selección o un fondo | `var(--sgth-accent-light)`, `var(--sgth-surface-sunken)` |
| Bordes en estilos en línea | `var(--sgth-border)`, `var(--sgth-border-strong)` |

### Principal y acento por subsistema

`SGTHAppShell` escribe `data-subsistema` en su raíz **y en `<html>`** —para
que modales, drawers y menús, que se montan en un portal fuera del shell,
también lo hereden—.

El tema declara `primaryColor: 'primario'`, una escala con los valores de
emerald. `tokens.css` reapunta sus variables (`--mantine-color-primario-*`) a
la escala de cada subsistema. Todo control sin `color` sale verde en SGTH,
azul en el Dispensario y violeta en el Portal, sin que la pantalla lo diga.
Fuera del shell (login, páginas públicas) queda el verde institucional.

**Cambian los controles, no los significados.** Un «Aprobado» es emerald y
un error es red en los tres subsistemas: si el éxito fuera violeta en el
Portal, el color dejaría de decir qué pasó. Por eso el principal se pide
omitiendo `color` (o con `primario`), y nunca escribiendo `emerald` para
decir «el color de la pantalla».

Contraste: el blanco sobre emerald-6 da 3.77:1 y no llega al AA (4.5:1); el
verde institucional usa emerald-7 (5.48:1) para el relleno en modo claro.
Azul (5.17:1) y violeta (5.70:1) llegan con el tono 6.

Del principal cuelgan seis tokens para el CSS propio. Todo lo que signifique
"el color de este subsistema" los usa:

```css
--sgth-accent              relleno sólido
--sgth-accent-hover        relleno sólido al pasar el cursor
--sgth-accent-light        fondo tenue (ítem activo del menú)
--sgth-accent-light-hover  fondo tenue al pasar el cursor
--sgth-accent-text         texto sobre fondo tenue
--sgth-accent-border       borde
```

Un `ThemeIcon` o `Avatar` sin `color` toma el principal como cualquier otro
control: el icono de una tarjeta sale del color del subsistema.

Así un mismo CSS sirve para los tres subsistemas. Agregar un subsistema es un
bloque en `tokens.css` y una entrada en `config/subsistemas.ts`.

## Superficies

Cuatro niveles, con nombre por función y no por color:

```css
--sgth-canvas          fondo de la aplicación, detrás de todo
--sgth-surface         tarjetas, paneles, sidebar
--sgth-surface-raised  capas flotantes: menús, modales, popovers
--sgth-surface-sunken  cabeceras de tabla, zonas inertes
--sgth-surface-hover   realce de un interactivo en reposo
--sgth-border          borde estándar
--sgth-border-strong   borde de énfasis y pulgar de scroll
--sgth-text            texto principal
--sgth-text-muted      texto secundario
```

## Modo oscuro

Los tokens de arriba ya lo resuelven. Dos advertencias que cuestan tiempo:

1. **`light-dark()` no funciona dentro de `:root`.** La función de
   postcss-preset-mantine compila a un selector descendiente de
   `[data-mantine-color-scheme="dark"]`, y ese atributo vive en el propio
   `<html>`. En `tokens.css` los dos esquemas se escriben explícitos con
   `:root` y `:root[data-mantine-color-scheme='dark']`. En un CSS Module con
   selector de clase `light-dark()` sí funciona y es preferible.

2. **Ningún color se escribe pensando en un solo esquema.** Si una pantalla se
   ve bien en claro y se rompe en oscuro, el fallo es haber usado un color de
   escala en vez de un token semántico.

### Gráficas: la única excepción

ECharts pinta sobre canvas y no entiende `var(--sgth-accent)`: necesita un
color resuelto. Esa excepción se canaliza por `useEChartsColors()`, que lee los
tokens del documento y los devuelve concretos, recalculándolos al cambiar de
esquema.

```tsx
const c = useEChartsColors()

const option = {
  backgroundColor: 'transparent',
  textStyle: { color: c.texto },
  series: [{ itemStyle: { color: c.acento } }],
}
```

Una gráfica que declara su propia paleta en hexadecimal se ve bien en claro y
queda ilegible en oscuro. `features/estructura/components/OrganigramaChart.tsx`
todavía está así y es la única que queda pendiente de migrar.

## Tipografía

Dos familias, con reparto claro:

- **Poppins** — títulos (`Title`, `headings`). Aporta el carácter de marca.
- **Inter** — interfaz y datos. Legible a 12–13px, que es donde vive una
  tabla de nómina.

El cuerpo lleva `font-variant-numeric: tabular-nums`, de modo que cédulas,
montos y fechas se alinean en columna sin fuente monoespaciada. Los títulos lo
revierten a proporcional.

| Rol | Componente | Tamaño |
|---|---|---|
| Título de página | `<Title order={1}>` | 28px / 700 |
| Sección | `<Title order={2}>` | 22px / 650 |
| Subsección, tarjeta | `<Title order={3}>` | 18px / 600 |
| Cuerpo | `<Text>` | 14px / 400 |
| Denso, celdas | `<Text size="sm">` | 13px |
| Captions, etiquetas | `<Text size="xs">` | 12px |

## Espaciado y radios

```
xs 4px · sm 8px · md 16px · lg 24px · xl 32px
```

Siempre por props de Mantine (`p="md"`, `gap="sm"`, `mt="lg"`), nunca en
píxeles sueltos.

```
radius:  xs 4 · sm 6 · md 8 · lg 12 · xl 16      defaultRadius: 'md'
botones e inputs md · tarjetas y paneles lg · modales y drawers xl
```

## Elevación

**Dentro de la página la jerarquía es por borde, no por sombra.** Un tablero
con doce tarjetas con sombra es ruido. Las tarjetas llevan
`withBorder` y `shadow="none"` por defecto del tema.

**Las capas flotantes sí llevan sombra**, porque están físicamente separadas
del contenido: `Menu` y `Popover` con `shadow="md"`, `Modal` y `Drawer` con
`shadow="lg"`.

## Movimiento

```css
--sgth-motion-fast  120ms   realce, color
--sgth-motion-base  180ms   despliegues, transiciones del shell
--sgth-motion-slow  280ms   entradas de capas grandes
--sgth-easing       cubic-bezier(0.4, 0, 0.2, 1)
```

El tema tiene `respectReducedMotion: true` y `globals.css` anula las
animaciones cuando el sistema lo pide. No lo sobrescribas.
