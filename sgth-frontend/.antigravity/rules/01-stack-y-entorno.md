# 01 · Stack y entorno

## Versiones instaladas

Están fijadas en `package.json`. No se actualiza una dependencia mayor sin
acordarlo: Mantine v9 y Next 16 traen cambios incompatibles con su
documentación anterior, y buena parte de lo que circula en internet describe
las versiones viejas.

```
Next.js                16.2.6      App Router
React                  19.2.4
TypeScript             5.x         strict: true
@mantine/core          9.2.1       v9, NO v8
@mantine/hooks         9.2.1
@mantine/dates         9.2.1
@mantine/dropzone      9.2.1
@mantine/modals        9.2.1
@mantine/notifications 9.2.1
@mantine/tiptap        9.2.1
mantine-datatable      9.2.2
@tabler/icons-react    3.x
@tanstack/react-query  5.x
react-hook-form        7.x         estándar de formularios
@hookform/resolvers    5.x
zustand                5.x
zod                    4.x         se importa desde 'zod/v4'
axios                  1.x
echarts + echarts-for-react         estándar de gráficas
```

`@mantine/form` sigue instalado pero **ya no se usa en ninguna pantalla**. No
lo reintroduzcas: el estándar es React Hook Form (ver [07](07-formularios.md)).

`@mantine/charts` está instalado y sin usar. Las gráficas son con ECharts.

## Cambios de API que muerden

Vienes de entrenar con versiones anteriores. Estos son los que ya han causado
errores en este repositorio:

| Antes | Ahora (v9 / Next 16) |
|---|---|
| `<Grid gutter="md">` | `<Grid gap="md">` |
| `<Collapse in={abierto}>` | `<Collapse expanded={abierto}>` |
| `<html suppressHydrationWarning>` | `<html {...mantineHtmlProps}>` |

Ante la duda, **lee el tipo instalado** en vez de recordarlo:

```bash
grep -n "gutter\|gap" node_modules/@mantine/core/lib/components/Grid/Grid.d.ts
```

Para Next.js, la documentación de la versión exacta viene dentro del paquete,
en `node_modules/next/dist/docs/`. Es la fuente correcta, no la web.

## Comandos

```bash
npm run dev            # servidor de desarrollo (turbopack)
npm run build          # compilación de producción
npx tsc --noEmit       # verificación de tipos
npx eslint src --ext .ts,.tsx
```

El servidor de desarrollo se levanta **siempre** con `npm run dev`, nunca con
un comando de shell suelto.

## Tipos del API

Los tipos vienen del backend; no se escriben a mano.

```
sgth-backend/storage/app/openapi.yaml
        ↓ npm run types:sync
sgth-frontend/openapi.yaml
        ↓ npm run types:generate
src/types/api.generated.ts     ← generado, NO editar jamás
        ↓
src/types/api.ts               ← el único que se importa
```

```ts
// SIEMPRE
import type { Servidor, Canton } from '@/types/api'

// NUNCA — desde el generado
import type { components } from '@/types/api.generated'
```

Si un tipo no calza con lo que devuelve el backend, el error está en el
backend o en `api.ts`. Se corrige ahí. Nunca se tapa con una aserción de tipo
(ver [09](09-prohibiciones.md)).
