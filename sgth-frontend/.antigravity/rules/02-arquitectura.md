# 02 · Arquitectura y organización

## Route groups — cuatro áreas, no una

```
src/app/
├── (auth)/              público: login, cambio de contraseña
│   ├── login/
│   └── cambiar-password/
├── (publico)/           enlaces sin sesión enviados por correo
│   ├── assist/[codigo]/
│   └── psicosocial/[codigo]/
├── (sgth)/sgth/         subsistema Talento Humano       → acento esmeralda
├── (salud)/salud/       subsistema Dispensario Médico   → acento océano
└── (portal)/portal/     subsistema Portal del Servidor  → acento amatista
```

Los tres últimos montan `SGTHAppShell` en su `layout.tsx`. `(auth)` y
`(publico)` no llevan shell: quien los abre no tiene sesión.

**No existe** un route group `(dashboard)`. La versión anterior de estas reglas
decía que las rutas protegidas vivían ahí; nunca fue cierto.

## Protección de rutas — dos capas

1. **`src/proxy.ts`** (middleware de Next). Lee la cookie `sgth_token` y
   redirige antes de servir la página. Su `matcher` **excluye en vez de
   enumerar**: cubre todo salvo `_next`, las rutas de API y los archivos
   estáticos. Antes era una lista de rutas de primer nivel que dejó de existir
   al mover las pantallas bajo `/sgth`, `/salud` y `/portal`, y con ella el
   sistema entero quedaba fuera. Una lista blanca hay que acordarse de
   ampliarla cada vez que nace un módulo; la exclusión se mantiene sola.

   `RUTAS_ABIERTAS` (`/assist`, `/psicosocial`) se salta toda comprobación:
   son los enlaces de campaña que se envían por correo y los abre gente sin
   usuario del sistema. Van aparte de `RUTAS_AUTENTICACION` (`/login`) porque
   a quien ya tiene sesión no hay que desviarlo al panel al abrir su propia
   encuesta.

2. **`SGTHAppShell`** en el cliente. Segunda capa, no la única: redirige
   cuando el store no tiene sesión (ver [04](04-shell-y-navegacion.md)).

Las tres caducidades están alineadas en 24 horas: la cookie `sgth_token`, la
sesión persistida —que al rehidratar se limpia sola si la cookie ya no está— y
el token de Sanctum (`config/sanctum.php`).

## Estructura de `src`

```
src/
├── app/                    solo enrutado: page.tsx, layout.tsx, metadata
├── components/
│   ├── layout/             el shell — nadie más lo toca
│   └── ui/                 catálogo estándar (archivo 06)
├── features/{modulo}/
│   ├── components/         NombreForm, NombreModal, nombre.columns.tsx
│   ├── hooks/              useNombre, useNombreMutations
│   ├── schemas/            nombre.schema.ts
│   ├── services/           nombreService.ts
│   ├── constants/          catálogos y mapas de estado del módulo
│   └── utils/              helpers puros del módulo
├── config/
│   ├── design.tokens.ts    escalas crudas del sistema de diseño
│   ├── mantine.theme.ts    el tema, que consume las escalas
│   ├── subsistemas.ts      los tres subsistemas
│   ├── routes.ts           TODAS las rutas, tipadas
│   ├── nav.ts              el menú y sus derivados
│   └── env.ts
├── hooks/                  hooks transversales
├── lib/                    axios, queryClient, iconos del menú
├── store/                  Zustand
├── styles/                 tokens.css, globals.css, inputs contained
└── types/                  api.generated.ts (generado) y api.ts
```

### Dónde va cada cosa

- ¿Lo usan dos o más módulos? → `components/ui/`, `hooks/` o `lib/`.
- ¿Lo usa un solo módulo? → `features/{modulo}/`, aunque parezca genérico.
- ¿Es una ruta? → `config/routes.ts`. Nunca una URL escrita a mano en el JSX.
- ¿Es una pantalla del menú? → `config/nav.ts` (ver [04](04-shell-y-navegacion.md)).

## `page.tsx` es un archivo delgado

Un `page.tsx` exporta `metadata` y devuelve un componente. Nada más. La lógica
va en una vista cliente hermana, porque `metadata` y `'use client'` son
incompatibles en el mismo archivo.

```tsx
// src/app/(sgth)/sgth/nomina/page.tsx
import type { Metadata } from 'next'
import { NominaView } from './NominaView'

export const metadata: Metadata = {
  title: 'Nómina',
  description: 'Gestión de roles de pago y períodos de nómina',
}

export default function NominaPage() {
  return <NominaView />
}
```

Si la pantalla no necesita estado ni interacción, puede vivir entera en el
`page.tsx` sin `'use client'`.

## Nomenclatura

```
Componentes    PascalCase          ServidorCard.tsx
Vistas de ruta PascalCase + View   NominaView.tsx
Hooks          camelCase con use   useServidores.ts
Servicios      camelCase           servidorService.ts
Stores         nombre.store.ts     auth.store.ts
Esquemas Zod   nombre.schema.ts    servidor.schema.ts
Columnas       nombre.columns.tsx  servidor.columns.tsx
Tipos          PascalCase          ServidorFormData
Constantes     UPPER_SNAKE_CASE    MAX_POR_PAGINA
CSS Modules    Componente.module.css
```

El código se escribe **en español**: variables, funciones, comentarios y
mensajes. Las APIs de terceros conservan su nombre original (`useForm`,
`records`, `onChange`).

## Tamaño de archivo

Son señales, no una ley. Pasarse indica que el archivo hace más de una cosa;
revísalo antes de seguir engordándolo.

```
Páginas       ≤ 100      Componentes  ≤ 200
Hooks         ≤ 150      Servicios    ≤ 100
Esquemas      ≤  80      Columnas     ≤ 150
```
