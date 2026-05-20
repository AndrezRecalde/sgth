# SGTH Frontend — Documento de Contexto del Agente
# GAD Provincial de Esmeraldas
# Versión 1.0 — Mayo 2026

---

## 0. INSTRUCCIÓN DE USO

Este archivo es el contexto obligatorio del agente para el frontend del SGTH.
DEBES leer este archivo completo antes de escribir cualquier componente, página o hook.
Si alguna instrucción de este archivo contradice lo que el usuario pide en el chat,
prioriza este archivo y notifica la contradicción antes de proceder.

---

## 1. STACK TECNOLÓGICO — VERSIONES EXACTAS

```
Next.js          16.2.6       (App Router — OBLIGATORIO)
React            19.2.4
TypeScript       ~5.x
@mantine/core    9.2.1
@mantine/hooks   9.2.1
@mantine/form    9.2.1
@mantine/dates   9.2.1
@mantine/charts  9.2.1
@mantine/dropzone 9.2.1
@mantine/modals  9.2.1
@mantine/notifications 9.2.1
@mantine/schedule 9.2.1
mantine-datatable 9.2.2
@tabler/icons-react (versión instalada)
@tanstack/react-query  v5
zustand          v5
zod              v4
axios            (versión instalada)
echarts          (versión instalada)
echarts-for-react (versión instalada)
```

---

## 2. REGLAS ABSOLUTAS — NUNCA VIOLAR

### 2.1 Componentes

- **TODOS los componentes de UI vienen de @mantine/core**. Nunca usar HTML nativo para UI
  (Button, Input, Select, Modal, etc.). Solo se usan etiquetas HTML nativas dentro de
  componentes Mantine o cuando Mantine no tiene el componente exacto.
- **Todas las tablas usan mantine-datatable**. Nunca usar `<table>` HTML directo ni
  @mantine/core Table para datos tabulares listables.
- **Todas las gráficas estadísticas usan ECharts** a través de `echarts-for-react`.
  No usar @mantine/charts para gráficas de datos. @mantine/charts solo se permite para
  sparklines decorativas simples si aplica.
- **Todos los iconos usan @tabler/icons-react**. NUNCA usar emojis como iconos en la UI.
  Ejemplo correcto: `<IconHome size={18} />`. Ejemplo incorrecto: usar 🏠 en la UI.
- **Todos los formularios usan el patrón "Inputs with label inside input" (contained)**
  de Mantine. Ver sección 5 para implementación.

### 2.2 Routing y arquitectura

- Usar Next.js **App Router** exclusivamente. Nunca Pages Router.
- Las rutas protegidas viven bajo `app/(dashboard)/`.
- Las rutas públicas (login) viven bajo `app/(auth)/`.
- Middleware de Next.js maneja la protección de rutas.

### 2.3 Estado y datos

- **TanStack Query v5** para todo el estado servidor (fetching, caching, mutations).
- **Zustand v5** para estado cliente global (auth, preferencias UI).
- No usar useState para datos que vengan del API. Siempre useQuery/useMutation.
- No usar Context API para estado global. Solo Zustand.

### 2.4 Tipado

- **TypeScript estricto**. Nunca usar `any`. Si el tipo no existe, crearlo.
- Los tipos del API vienen de `src/types/api.ts` (generados desde OpenAPI).
- `tsconfig.json` tiene `"strict": true`. No deshabilitarlo.

### 2.5 Estilos

- **Solo Mantine** para estilos. No instalar Tailwind, Bootstrap, ni ningún otro framework CSS.
- Para estilos custom usar `style={{}}` inline con tokens de Mantine o
  CSS Modules (`.module.css`) en casos excepcionales.
- Usar `useMantineTheme()` para acceder a colores, espaciado y radios del tema.
- No hardcodear colores hexadecimales en componentes. Usar `theme.colors.emerald[6]` etc.

---

## 3. SISTEMA DE DISEÑO — TOKENS

### 3.1 Paleta de colores

Color primario institucional: **Verde Esmeralda**

```typescript
// En mantine.theme.ts
colors: {
  emerald: [
    '#ECFDF5', // [0] 50
    '#D1FAE5', // [1] 100
    '#A7F3D0', // [2] 200
    '#6EE7B7', // [3] 300
    '#34D399', // [4] 400
    '#10B981', // [5] 500
    '#059669', // [6] 600 ← PRIMARY (index 6)
    '#047857', // [7] 700
    '#065F46', // [8] 800
    '#064E3B', // [9] 900
  ],
}
primaryColor: 'emerald',
primaryShade: 6,
```

Colores adicionales del sistema:
```
Navy (Sidebar):   #0D1F2D
Navy hover:       #152636
Navy active:      #1E3347
Active border:    #10B981 (emerald[5])
Active bg:        rgba(16,185,129,0.18)

Semánticos:
Success:  emerald[6]  #059669
Warning:  #F59E0B     (Amber-500 Tailwind)
Danger:   #EF4444     (Red-500 Tailwind)
Info:     #3B82F6     (Blue-500 Tailwind)
```

### 3.2 Tipografía

```typescript
fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
```

Escala de tamaños:
```
Display:  28px / weight 700  — títulos de página grandes
H1:       20px / weight 600  — títulos de sección
H2:       16px / weight 600  — subtítulos de card
H3:       14px / weight 600  — labels de grupo
Body:     14px / weight 400  — texto corriente
Small:    12px / weight 400  — captions, metadata
Tiny:     10px / weight 500  — badges, tags uppercase
```

### 3.3 Espaciado

Usar siempre la escala de Mantine (rem):
```
xs:  4px    (0.25rem)
sm:  8px    (0.5rem)
md:  16px   (1rem)
lg:  24px   (1.5rem)
xl:  32px   (2rem)
```

En props de Mantine: `p="md"`, `gap="sm"`, `mt="lg"`, etc.

### 3.4 Radios de borde

```typescript
radius: {
  xs: '4px',
  sm: '6px',
  md: '8px',   // inputs, botones, badges
  lg: '10px',  // cards, panels
  xl: '12px',  // modals, drawers
}
defaultRadius: 'md',
```

### 3.5 Sombras

**No usar sombras decorativas**. La elevación se expresa con:
- Borde `0.5px` para nivel base
- Borde `1px` para hover
- Borde `1.5px` color primary para focus

```typescript
shadows: {
  none: 'none',
  // Solo para floating elements (dropdowns, tooltips — Mantine los maneja)
  sm: '0 1px 3px rgba(0,0,0,0.06)',
}
```

---

## 4. APPSHELL — ESTRUCTURA VISUAL

### 4.1 Layout

```
┌──────────────────────────────────────────────────────┐
│ SIDEBAR (220px fijo)  │  TOPBAR (52px altura)         │
│ Navy #0D1F2D          │  Blanco, border-bottom 0.5px  │
│                       │                               │
│ ┌─ System Selector ─┐ │  Breadcrumb    [toggle] [🔔] │
│ │ SGTH          v1.5│ │                               │
│ └───────────────────┘ ├───────────────────────────────┤
│                       │  CONTENT AREA                 │
│ ┌─ User Row ────────┐ │  bg: gray[0] o dark equiv.    │
│ │ Avatar  Nombre    │ │  padding: 18px 20px           │
│ │         Rol       │ │  overflow-y: auto             │
│ └───────────────────┘ │                               │
│                       │                               │
│ NAVIGATION (scroll)   │                               │
│ [Sección]             │                               │
│   Item                │                               │
│   Item activo ◄──╗    │                               │
│   Item            ║   │                               │
│                   ║   │                               │
│ [Sección]         ║ borde izq esmeralda               │
│   ...             ║   │                               │
│                       │                               │
│ BOTTOM (fijo)         │                               │
│   Configuración       │                               │
│   Cerrar sesión       │                               │
└───────────────────────┴───────────────────────────────┘
```

### 4.2 Sidebar — comportamiento por rol

El sidebar se construye dinámicamente desde los permisos del usuario autenticado.
No hardcodear items para un rol específico. Usar la función `buildNavItems(permisos)`.

Grupos de navegación y sus módulos:
```
Principal:      dashboard, servidores
Talento Humano: estructura, expediente, nomina, asistencia, viaticos
Bienestar:      dispensario, sso, capacitacion, bienestar
Procesos:       seleccion, evaluacion, disciplinario, inventario-ti, helpdesk, sgd
Análisis:       reporteria
```

Vista de servidor (autoservicio) — sidebar reducido:
```
Mi Cuenta:      mi-perfil
Mis Solicitudes: mis-permisos, mis-vacaciones, mis-marcaciones
Mis Actividades: mis-actividades
Soporte:        mis-tickets
```

### 4.3 Topbar

- Breadcrumb dinámico basado en la ruta actual
- Toggle modo claro/oscuro (Mantine ColorSchemeProvider)
- Notificaciones (ícono campana)
- Sin búsqueda global por ahora

### 4.4 Modo claro y oscuro

- Implementar con `localStorageColorSchemeManager` de Mantine
- El toggle en el topbar llama `toggleColorScheme()`
- Todos los componentes deben funcionar en ambos modos
- Nunca hardcodear colores que solo funcionen en un modo

---

## 5. FORMULARIOS — PATRÓN OBLIGATORIO

### 5.1 Inputs "contained" (label inside)

TODOS los formularios del sistema usan el estilo "contained" de Mantine donde el label
está dentro del input. Implementación:

```tsx
// ✅ CORRECTO — siempre así
import { TextInput, Select, Textarea } from '@mantine/core'

<TextInput
  label="Nombres"
  placeholder="Ingrese nombres"
  variant="filled"   // o el variant que produce el efecto contained
  // Mantine 9.x: usar classNames o styles para el efecto contained
  styles={{
    root: { },
    label: {
      fontSize: '10px',
      fontWeight: 500,
      color: 'var(--mantine-color-dimmed)',
      marginBottom: '2px',
    },
    input: {
      paddingTop: '18px',
      paddingBottom: '6px',
    },
    wrapper: {
      position: 'relative',
    },
  }}
/>

// ❌ INCORRECTO
<label>Nombres</label>
<input type="text" />
```

Referencia visual: https://ui.mantine.dev/category/inputs/#contained-inputs

### 5.2 Validación con Zod

```tsx
import { useForm } from '@mantine/form'
import { zodResolver } from 'mantine-form-zod-resolver'
import { z } from 'zod/v4'

const schema = z.object({
  nombres: z.string().min(2, 'Mínimo 2 caracteres'),
  cedula: z.string().length(10, 'La cédula debe tener 10 dígitos'),
})

const form = useForm({
  initialValues: { nombres: '', cedula: '' },
  validate: zodResolver(schema),
})
```

### 5.3 Estructura de formulario en modal

```tsx
<Modal title="Nuevo servidor" size="lg">
  <form onSubmit={form.onSubmit(handleSubmit)}>
    <Stack gap="sm">
      <Grid>
        <Grid.Col span={6}>
          <TextInput label="Nombres" {...form.getInputProps('nombres')} />
        </Grid.Col>
        <Grid.Col span={6}>
          <TextInput label="Apellidos" {...form.getInputProps('apellidos')} />
        </Grid.Col>
        <Grid.Col span={12}>
          <TextInput label="Dirección" {...form.getInputProps('direccion')} />
        </Grid.Col>
      </Grid>
      <Group justify="flex-end" mt="md">
        <Button variant="default" onClick={close}>Cancelar</Button>
        <Button type="submit" loading={isPending}>Guardar</Button>
      </Group>
    </Stack>
  </form>
</Modal>
```

---

## 6. TABLAS — MANTINE DATATABLE

### 6.1 Estructura base obligatoria

```tsx
import { DataTable } from 'mantine-datatable'
import type { DataTableColumn } from 'mantine-datatable'

// Siempre tipar las columnas
const columns: DataTableColumn<Servidor>[] = [
  {
    accessor: 'cedula',
    title: 'Cédula',
    width: 120,
  },
  {
    accessor: 'nombres',
    title: 'Nombres',
    render: (row) => `${row.nombres} ${row.apellidos}`,
  },
  {
    accessor: 'estado',
    title: 'Estado',
    render: (row) => <EstadoBadge estado={row.estado} />,
  },
]

<DataTable
  records={data}
  columns={columns}
  fetching={isLoading}
  totalRecords={total}
  recordsPerPage={perPage}
  page={page}
  onPageChange={setPage}
  highlightOnHover
  striped
  withTableBorder
  borderRadius="md"
  shadow="none"
/>
```

### 6.2 Paginación

- Siempre paginación server-side. Nunca cargar todos los registros.
- Parámetros: `page`, `per_page` (default 15).
- El backend devuelve `current_page`, `last_page`, `total`, `data`.

---

## 7. GRÁFICAS — ECHARTS

### 7.1 Wrapper obligatorio

```tsx
import ReactECharts from 'echarts-for-react'

// Siempre envolver en un componente con altura definida
<div style={{ height: 320 }}>
  <ReactECharts
    option={option}
    style={{ height: '100%' }}
    theme={colorScheme === 'dark' ? 'dark' : undefined}
  />
</div>
```

### 7.2 Paleta de colores en gráficas

```typescript
const CHART_COLORS = [
  '#059669', // emerald primary
  '#10B981', // emerald 500
  '#34D399', // emerald 400
  '#065F46', // emerald 800
  '#F59E0B', // amber
  '#3B82F6', // blue
  '#EF4444', // red
]
```

---

## 8. CONVENCIONES DE CÓDIGO

### 8.1 Naming

```
Componentes:    PascalCase        → ServidorCard.tsx
Hooks:          camelCase useX    → useServidores.ts
Services:       camelCase         → servidorService.ts
Stores:         camelCase .store  → auth.store.ts
Types:          PascalCase        → ServidorFormData
Constants:      UPPER_SNAKE_CASE  → MAX_PER_PAGE
Archivos:       kebab-case        → servidor-detail.tsx (páginas Next.js)
```

### 8.2 Estructura de un componente

```tsx
// 1. Imports — React primero, luego Mantine, luego Tabler, luego internos
import { useState } from 'react'
import { Card, Text, Button, Group } from '@mantine/core'
import { IconEdit, IconTrash } from '@tabler/icons-react'
import type { Servidor } from '@/types/api'
import { useServidores } from '../hooks/useServidores'

// 2. Types locales
interface Props {
  servidor: Servidor
  onEdit: (id: number) => void
}

// 3. Componente
export function ServidorCard({ servidor, onEdit }: Props) {
  // 3a. Hooks primero
  const theme = useMantineTheme()

  // 3b. Estado local
  const [open, setOpen] = useState(false)

  // 3c. Handlers
  const handleEdit = () => onEdit(servidor.id)

  // 3d. Render
  return (
    <Card radius="lg" withBorder>
      {/* contenido */}
    </Card>
  )
}
```

### 8.3 Estructura de un hook de datos

```typescript
// src/features/expediente/hooks/useServidores.ts
import { useQuery } from '@tanstack/react-query'
import { servidorService } from '../services/servidorService'

export function useServidores(params: ServidorParams) {
  return useQuery({
    queryKey: ['servidores', params],
    queryFn: () => servidorService.listar(params),
    staleTime: 1000 * 60 * 5, // 5 minutos
  })
}
```

### 8.4 Estructura de un service

```typescript
// src/features/expediente/services/servidorService.ts
import { api } from '@/lib/axios'
import type { PaginatedResponse, Servidor } from '@/types/api'

export const servidorService = {
  listar: (params: ServidorParams) =>
    api.get<PaginatedResponse<Servidor>>('/expediente/servidores', { params })
      .then(r => r.data),

  obtener: (id: number) =>
    api.get<Servidor>(`/expediente/servidores/${id}`)
      .then(r => r.data),

  crear: (data: ServidorFormData) =>
    api.post<Servidor>('/expediente/servidores', data)
      .then(r => r.data),
}
```

---

## 9. ARQUITECTURA DE CARPETAS

```
sgth-frontend/
├── .antigravity/
│   └── FRONTEND.md              ← este archivo
├── src/
│   ├── app/
│   │   ├── (auth)/
│   │   │   └── login/
│   │   │       └── page.tsx
│   │   ├── (dashboard)/
│   │   │   ├── layout.tsx       ← AppShell wrapper
│   │   │   ├── page.tsx         ← Dashboard
│   │   │   ├── servidores/
│   │   │   ├── estructura/
│   │   │   ├── expediente/
│   │   │   ├── nomina/
│   │   │   ├── asistencia/
│   │   │   ├── viaticos/
│   │   │   ├── dispensario/
│   │   │   ├── sso/
│   │   │   ├── capacitacion/
│   │   │   ├── bienestar/
│   │   │   ├── seleccion/
│   │   │   ├── evaluacion/
│   │   │   ├── disciplinario/
│   │   │   ├── inventario-ti/
│   │   │   ├── helpdesk/
│   │   │   ├── sgd/
│   │   │   ├── reporteria/
│   │   │   └── autoservicio/    ← portal servidor
│   │   ├── layout.tsx           ← Root layout (MantineProvider)
│   │   └── globals.css
│   ├── components/
│   │   ├── layout/
│   │   │   ├── AppShell.tsx
│   │   │   ├── Sidebar.tsx
│   │   │   ├── Topbar.tsx
│   │   │   └── NavItem.tsx
│   │   └── ui/
│   │       ├── EstadoBadge.tsx
│   │       ├── PageHeader.tsx
│   │       ├── EmptyState.tsx
│   │       ├── LoadingOverlay.tsx
│   │       └── ConfirmModal.tsx
│   ├── features/
│   │   ├── auth/
│   │   │   ├── components/
│   │   │   ├── hooks/
│   │   │   └── services/
│   │   ├── estructura/
│   │   ├── expediente/
│   │   ├── viaticos/
│   │   ├── dispensario/
│   │   └── ... (un folder por módulo)
│   ├── hooks/
│   │   ├── useAuth.ts
│   │   └── usePermiso.ts
│   ├── lib/
│   │   ├── axios.ts             ← instancia axios configurada
│   │   └── queryClient.ts       ← TanStack Query client
│   ├── store/
│   │   ├── auth.store.ts
│   │   └── ui.store.ts
│   ├── config/
│   │   ├── env.ts
│   │   ├── routes.ts
│   │   └── nav.ts               ← definición de navegación por rol
│   └── types/
│       ├── api.generated.ts     ← NO editar (auto-generado)
│       └── api.ts               ← tipos de conveniencia
├── openapi.yaml
├── TYPES.md
└── .env.local
```

---

## 10. AUTENTICACIÓN Y PERMISOS

### 10.1 Flujo de login

```
1. POST /api/v1/auth/login → { token, primer_login, usuario }
2. Guardar token en localStorage + Zustand auth.store
3. Si primer_login === true → redirigir a /cambiar-password
4. Construir sidebar según usuario.permisos
5. Middleware protege rutas (dashboard) verificando token
```

### 10.2 Zustand auth store

```typescript
interface AuthState {
  token: string | null
  usuario: UsuarioAuth | null
  setAuth: (token: string, usuario: UsuarioAuth) => void
  clearAuth: () => void
  hasPermiso: (permiso: string) => boolean
  hasRole: (role: string) => boolean
  isAuthenticated: () => boolean
}
```

### 10.3 Guard de ruta — proxy.ts

```typescript
// proxy.ts en raíz del proyecto
import { NextResponse } from 'next/server'
import type { NextRequest } from 'next/server'

export function proxy(request: NextRequest) {
  const token = request.cookies.get('sgth_token')?.value
  const isAuth = request.nextUrl.pathname.startsWith('/login')

  if (!token && !isAuth) {
    return NextResponse.redirect(new URL('/login', request.url))
  }
  if (token && isAuth) {
    return NextResponse.redirect(new URL('/', request.url))
  }
}

export const config = {
  matcher: ['/((?!api|_next/static|_next/image|favicon.ico).*)'],
}
```

---

## 11. MANEJO DE ERRORES Y NOTIFICACIONES

### 11.1 Notificaciones — siempre @mantine/notifications

```typescript
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'

// Éxito
notifications.show({
  title: 'Servidor guardado',
  message: 'Los datos se actualizaron correctamente.',
  color: 'emerald',
  icon: <IconCheck size={16} />,
})

// Error
notifications.show({
  title: 'Error al guardar',
  message: error.message,
  color: 'red',
  icon: <IconX size={16} />,
})
```

### 11.2 Manejo de errores en mutations

```typescript
const mutation = useMutation({
  mutationFn: servidorService.crear,
  onSuccess: () => {
    notifications.show({ title: 'Guardado', color: 'emerald', icon: <IconCheck size={16} /> })
    queryClient.invalidateQueries({ queryKey: ['servidores'] })
    close()
  },
  onError: (error: AxiosError<ApiErrorResponse>) => {
    notifications.show({
      title: 'Error',
      message: error.response?.data?.mensaje ?? 'Error inesperado',
      color: 'red',
      icon: <IconX size={16} />,
    })
  },
})
```

---

## 12. MANTINE THEME CONFIG — mantine.theme.ts

```typescript
import { createTheme, rem } from '@mantine/core'

export const theme = createTheme({
  primaryColor: 'emerald',
  primaryShade: 6,
  fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
  defaultRadius: 'md',

  colors: {
    emerald: [
      '#ECFDF5', '#D1FAE5', '#A7F3D0', '#6EE7B7', '#34D399',
      '#10B981', '#059669', '#047857', '#065F46', '#064E3B',
    ],
  },

  radius: {
    xs: rem(4),
    sm: rem(6),
    md: rem(8),
    lg: rem(10),
    xl: rem(12),
  },

  shadows: {
    none: 'none',
    sm: '0 1px 3px rgba(0,0,0,0.06)',
  },

  components: {
    Button: {
      defaultProps: { radius: 'md' },
    },
    TextInput: {
      defaultProps: { radius: 'md' },
    },
    Select: {
      defaultProps: { radius: 'md' },
    },
    Card: {
      defaultProps: { radius: 'lg', withBorder: true },
    },
    Modal: {
      defaultProps: { radius: 'xl', centered: true },
    },
    Badge: {
      defaultProps: { radius: 'xl' },
    },
  },
})
```

---

## 13. SIDEBAR — COLORES EXACTOS

```typescript
// Sidebar siempre con estos colores — no cambiar
const SIDEBAR = {
  bg:           '#0D1F2D',
  hover:        'rgba(255,255,255,0.06)',
  activeBg:     'rgba(16,185,129,0.18)',
  activeBorder: '#10B981',
  text:         'rgba(255,255,255,0.85)',
  textMuted:    'rgba(255,255,255,0.45)',
  sectionLabel: 'rgba(255,255,255,0.35)',
  border:       'rgba(255,255,255,0.07)',
  width:        220,
}
```

---

## 14. PATRONES PROHIBIDOS

```
❌ Emojis como iconos en la UI
❌ <table> HTML directo para datos
❌ fetch() nativo (usar axios)
❌ useState para datos del API
❌ Colores hardcodeados hex en componentes
❌ @mantine/charts para gráficas de datos
❌ Pages Router de Next.js
❌ TypeScript any
❌ Shadows decorativas (box-shadow)
❌ CSS de Tailwind
❌ Importar desde 'react-query' (usar '@tanstack/react-query')
❌ Importar desde 'zod' directamente (usar 'zod/v4')
❌ Labels flotantes externos en formularios (siempre contained)
❌ Modals con position:fixed manual (usar @mantine/modals)
```

---

## 15. URLS DEL API

```
Base URL (local):   http://sgth-backend.test/api/v1
Base URL (env):     process.env.NEXT_PUBLIC_API_URL

Endpoints principales:
POST   /auth/login
POST   /auth/logout
GET    /catalogos/provincias
GET    /catalogos/provincias/{id}/cantones
GET    /catalogos/entidades-financieras
GET    /catalogos/tipos-unidad
GET    /estructura/unidades-administrativas
GET    /estructura/directorio-telefonico
GET    /expediente/servidores
POST   /expediente/servidores
GET    /expediente/servidores/{id}
PUT    /expediente/servidores/{id}
GET    /viaticos
POST   /viaticos
GET    /dispensario/cie10/buscar?q=
GET    /dispensario/dashboard/kpis
```

---

## 16. COMANDOS ÚTILES

```bash
# Desarrollo
npm run dev

# Regenerar tipos TypeScript desde OpenAPI del backend
npm run types:generate   # solo tipos, necesita openapi.yaml actualizado
npm run types:sync       # exporta del backend + regenera tipos

# Build producción
npm run build
npm run start
```

---

## 17. COMPOSICIÓN DE COMPONENTES — REGLAS DE ORO

### 17.1 Límite de líneas por archivo — REGLA ABSOLUTA

```
Páginas (page.tsx):         máx  80 líneas  — solo orquesta componentes
Componentes de UI:          máx 200 líneas
Hooks:                      máx 150 líneas
Services:                   máx 100 líneas
Stores Zustand:             máx 100 líneas
Schemas Zod:                máx  80 líneas
```

Si un archivo supera su límite, DEBES dividirlo antes de continuar.
No hay excepciones. Un archivo largo es una señal de diseño incorrecto.

### 17.2 Patrón de división obligatorio por módulo

Cada módulo sigue esta estructura de archivos. Ejemplo con "servidores":

```
features/expediente/
├── components/
│   ├── ServidorPage.tsx          ← orquestador, máx 80 líneas
│   ├── ServidorToolbar.tsx       ← búsqueda + filtros + botón crear
│   ├── ServidorTable.tsx         ← DataTable + columnas definidas
│   ├── ServidorModal.tsx         ← modal crear/editar (abre ServidorForm)
│   ├── ServidorForm.tsx          ← solo el formulario con los inputs
│   ├── ServidorDetail.tsx        ← drawer lateral de detalle
│   ├── ServidorCard.tsx          ← card compacta para vista grid (si aplica)
│   └── ServidorDeleteConfirm.tsx ← modal de confirmación de eliminación
├── hooks/
│   ├── useServidores.ts          ← useQuery lista paginada
│   ├── useServidor.ts            ← useQuery detalle por id
│   ├── useServidorMutations.ts   ← crear, editar, eliminar (useMutation)
│   └── useServidorForm.ts        ← lógica del formulario + validación Zod
├── services/
│   └── servidorService.ts        ← todas las llamadas axios del módulo
├── schemas/
│   └── servidor.schema.ts        ← schemas Zod del módulo
└── index.ts                      ← barrel: export { ServidorPage }
```

### 17.3 Regla de responsabilidad única

Cada archivo tiene UNA sola responsabilidad:

```
✅ ServidorForm.tsx      → solo renderiza los inputs del formulario
✅ useServidores.ts      → solo fetching y caché de la lista
✅ servidorService.ts    → solo llamadas HTTP al API
✅ servidor.schema.ts    → solo schemas Zod de validación

❌ ServidorPage.tsx con 600 líneas que incluye tabla + form + lógica + estilos
❌ Un hook que hace fetch Y maneja el estado del modal Y valida el form
❌ Un service que también transforma datos para la UI
```

### 17.4 Separación lógica / presentación

```
LÓGICA (hooks, services, stores):
- Nunca renderiza JSX
- Nunca importa componentes de UI
- Es reutilizable desde cualquier componente

PRESENTACIÓN (componentes):
- Recibe datos como props
- No hace llamadas axios directamente
- No conoce la URL del API
- Puede usar hooks internos de Mantine (useDisclosure, etc.)
```

Ejemplo correcto:
```tsx
// ✅ hook maneja lógica
function useServidorModal() {
  const [opened, { open, close }] = useDisclosure(false)
  const [editId, setEditId] = useState<number | null>(null)
  const mutation = useServidorMutations()

  const handleEdit = (id: number) => { setEditId(id); open() }
  const handleClose = () => { setEditId(null); close() }

  return { opened, editId, handleEdit, handleClose, mutation }
}

// ✅ componente solo presenta
function ServidorPage() {
  const modal = useServidorModal()
  const { data, isLoading } = useServidores()

  return (
    <>
      <ServidorToolbar onCrear={modal.handleEdit.bind(null, 0)} />
      <ServidorTable data={data} onEdit={modal.handleEdit} isLoading={isLoading} />
      <ServidorModal opened={modal.opened} id={modal.editId} onClose={modal.handleClose} />
    </>
  )
}
```

### 17.5 Columnas de DataTable — siempre en archivo separado

Las definiciones de columnas de DataTable pueden ser largas. Siempre en su propio archivo:

```typescript
// features/expediente/components/servidor.columns.tsx
import type { DataTableColumn } from 'mantine-datatable'
import type { Servidor } from '@/types/api'

export const servidorColumns: DataTableColumn<Servidor>[] = [
  { accessor: 'cedula', title: 'Cédula', width: 120 },
  { accessor: 'nombres', title: 'Nombre completo',
    render: (row) => `${row.nombres} ${row.apellidos}` },
  { accessor: 'unidad_administrativa.nombre', title: 'Unidad' },
  { accessor: 'estado', title: 'Estado',
    render: (row) => <EstadoBadge estado={row.estado} /> },
]
```

```tsx
// ServidorTable.tsx — limpio y corto
import { servidorColumns } from './servidor.columns'

export function ServidorTable({ data, isLoading, ...props }: Props) {
  return (
    <DataTable
      records={data}
      columns={servidorColumns}
      fetching={isLoading}
      {...props}
    />
  )
}
```

### 17.6 Schemas Zod — siempre en archivo separado

```typescript
// features/expediente/schemas/servidor.schema.ts
import { z } from 'zod/v4'

export const servidorSchema = z.object({
  nombres:    z.string().min(2, 'Mínimo 2 caracteres'),
  apellidos:  z.string().min(2, 'Mínimo 2 caracteres'),
  cedula:     z.string().length(10, 'Debe tener 10 dígitos'),
  email:      z.string().email('Email inválido').optional(),
  canton_nacimiento_id: z.number({ error: 'Seleccione un cantón' }),
})

export type ServidorFormData = z.infer<typeof servidorSchema>
```

### 17.7 Barrel exports — index.ts en cada feature

```typescript
// features/expediente/index.ts
export { ServidorPage } from './components/ServidorPage'
// Solo exportar lo que otras features o páginas necesitan
// Los sub-componentes internos NO se exportan desde el barrel
```

### 17.8 Checklist antes de hacer commit en cualquier archivo

Antes de finalizar cualquier archivo, verifica:

```
□ ¿El archivo tiene menos líneas que su límite?
□ ¿El componente hace una sola cosa?
□ ¿La lógica está en hooks, no en el componente?
□ ¿Las columnas de tabla están en archivo separado?
□ ¿El schema Zod está en archivo separado?
□ ¿El componente no importa axios directamente?
□ ¿No hay console.log sin propósito de debug?
□ ¿Todos los tipos están explícitos (sin any)?
□ ¿Los iconos son de @tabler/icons-react (sin emojis)?
□ ¿Los inputs usan el patrón contained de Mantine?
```

---

## 19. DISEÑO RESPONSIVE — REGLAS OBLIGATORIAS

### 19.1 El sistema DEBE funcionar en móvil, tablet y desktop

Los funcionarios acceden desde computadores y dispositivos móviles.
Cada componente que se construya debe probarse mentalmente en las tres vistas.
Responsive NO es opcional ni se agrega después — va desde el primer componente.

### 19.2 Breakpoints de Mantine (usar siempre estos, nunca valores ad-hoc)

```
xs:  475px    → móvil pequeño
sm:  576px    → móvil grande
md:  768px    → tablet
lg:  992px    → laptop
xl:  1200px   → desktop
```

En código Mantine se usan así:
```tsx
// En props de Grid
<Grid.Col span={{ base: 12, sm: 6, lg: 4 }}>

// En Stack/Group con visibilidad condicional
<Box visibleFrom="md">  {/* solo visible desde tablet */}
<Box hiddenFrom="md">   {/* oculto desde tablet */}

// En hooks
const { isMobile } = useMobileBreakpoint() // hook propio — ver 19.6
```

### 19.3 AppShell — comportamiento por breakpoint

```
Desktop (≥lg / 992px):
  - Sidebar visible y fijo, ancho 220px
  - Topbar con breadcrumb completo
  - Contenido ocupa el resto

Tablet (md / 768px - 991px):
  - Sidebar colapsado a 60px (solo íconos)
  - Al hacer hover sobre íconos → tooltip con el nombre del item
  - Topbar con breadcrumb reducido

Móvil (<md / 767px):
  - Sidebar OCULTO por defecto
  - Topbar muestra botón Burger (☰) a la izquierda
  - Al tocar Burger → Drawer desliza el sidebar completo desde la izquierda
  - Drawer se cierra al seleccionar un item de navegación
  - Topbar muestra solo el título de la página actual
```

Implementación con Mantine AppShell:
```tsx
// En components/layout/AppShell.tsx
import { AppShell, Burger, useDisclosure } from '@mantine/core'
import { useMediaQuery } from '@mantine/hooks'

export function SGTHAppShell({ children }: { children: React.ReactNode }) {
  const [opened, { toggle, close }] = useDisclosure()
  const isMobile = useMediaQuery('(max-width: 768px)')
  const isTablet = useMediaQuery('(max-width: 992px)')

  return (
    <AppShell
      navbar={{
        width: isTablet ? 60 : 220,
        breakpoint: 'md',
        collapsed: { mobile: !opened },
      }}
      header={{ height: 52 }}
      padding="md"
    >
      <AppShell.Header>
        <Burger opened={opened} onClick={toggle} hiddenFrom="md" size="sm" />
        {/* resto del topbar */}
      </AppShell.Header>

      <AppShell.Navbar>
        <Sidebar collapsed={isTablet && !isMobile} onNavClick={close} />
      </AppShell.Navbar>

      <AppShell.Main>{children}</AppShell.Main>
    </AppShell>
  )
}
```

### 19.4 Grids de formularios — responsive obligatorio

```tsx
// ✅ SIEMPRE con breakpoints — nunca span fijo sin base
<Grid>
  <Grid.Col span={{ base: 12, sm: 6 }}>        {/* 1 col móvil, 2 col tablet+ */}
    <TextInput label="Nombres" ... />
  </Grid.Col>
  <Grid.Col span={{ base: 12, sm: 6 }}>
    <TextInput label="Apellidos" ... />
  </Grid.Col>
  <Grid.Col span={{ base: 12, sm: 6, md: 4 }}>  {/* 3 columnas en desktop */}
    <TextInput label="Cédula" ... />
  </Grid.Col>
  <Grid.Col span={12}>                           {/* siempre full width */}
    <Textarea label="Observaciones" ... />
  </Grid.Col>
</Grid>

// ❌ NUNCA así
<Grid.Col span={6}>   {/* roto en móvil */}
```

### 19.5 Modals — fullScreen en móvil

```tsx
import { useMediaQuery } from '@mantine/hooks'

function ServidorModal({ opened, onClose }: Props) {
  const isMobile = useMediaQuery('(max-width: 768px)')

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title="Nuevo servidor"
      size="lg"
      fullScreen={isMobile}  // ← fullScreen en móvil
      radius={isMobile ? 0 : 'xl'}
    >
      <ServidorForm />
    </Modal>
  )
}
```

### 19.6 Hook utilitario — useMobileBreakpoint

Crea en `src/hooks/useMobileBreakpoint.ts`:

```typescript
import { useMediaQuery } from '@mantine/hooks'

export function useMobileBreakpoint() {
  const isMobile  = useMediaQuery('(max-width: 767px)')  ?? false
  const isTablet  = useMediaQuery('(max-width: 991px)')  ?? false
  const isDesktop = useMediaQuery('(min-width: 992px)')  ?? true

  return { isMobile, isTablet, isDesktop }
}
```

### 19.7 Tablas (DataTable) en móvil

En pantallas pequeñas las tablas con muchas columnas necesitan scroll horizontal:

```tsx
<Box style={{ overflowX: 'auto' }}>
  <DataTable
    records={data}
    columns={columns}
    // En móvil reducir columnas visibles:
    // Usar columnas con hidden: isMobile para columnas secundarias
    minWidth={600}   // fuerza scroll horizontal antes de colapsar
  />
</Box>
```

Para tablas complejas en móvil, mostrar una vista alternativa tipo "card list":
```tsx
function ServidorTable({ data }: Props) {
  const { isMobile } = useMobileBreakpoint()

  if (isMobile) return <ServidorCardList data={data} />   // vista cards
  return <DataTable records={data} columns={columns} />   // vista tabla
}
```

### 19.8 Stats / KPI cards — grid responsive

```tsx
// ✅ Dashboard KPIs
<SimpleGrid cols={{ base: 1, sm: 2, lg: 4 }}>
  <StatCard label="Servidores activos" value={247} />
  <StatCard label="Viáticos pendientes" value={18} />
  <StatCard label="Permisos hoy" value={9} />
  <StatCard label="Consultas médicas" value={34} />
</SimpleGrid>
```

### 19.9 Topbar en móvil

```tsx
// En Topbar.tsx
const { isMobile } = useMobileBreakpoint()

// Mostrar en desktop: breadcrumb completo
// Mostrar en móvil: solo el título de la página actual + Burger
{isMobile ? (
  <Text fw={600} size="sm">{pageTitle}</Text>
) : (
  <Breadcrumbs>{breadcrumbItems}</Breadcrumbs>
)}
```

### 19.10 Sidebar colapsado en tablet — solo íconos

Cuando `isTablet && !isMobile`, el sidebar muestra solo los íconos (60px de ancho).
Al hacer hover en un ícono aparece un Tooltip con el nombre del item.

```tsx
// En NavItem.tsx
function NavItem({ item, collapsed }: { item: NavItem; collapsed: boolean }) {
  const Icon = getTablerIcon(item.icon)  // resolver icono por nombre

  if (collapsed) {
    return (
      <Tooltip label={item.label} position="right" withArrow>
        <UnstyledButton style={itemStyles}>
          <Icon size={20} />
        </UnstyledButton>
      </Tooltip>
    )
  }

  return (
    <UnstyledButton style={itemStyles}>
      <Icon size={16} />
      <Text size="sm">{item.label}</Text>
    </UnstyledButton>
  )
}
```

### 19.11 Checklist responsive — agregar al checklist de commit (sección 17.8)

```
□ ¿Los Grid.Col tienen span={{ base, sm, md }}?
□ ¿Los Modals tienen fullScreen={isMobile}?
□ ¿Las tablas tienen overflowX o vista alternativa en móvil?
□ ¿Los stats usan SimpleGrid con cols={{ base: 1, sm: 2, lg: 4 }}?
□ ¿El AppShell tiene el Burger visible en móvil?
□ ¿Los botones de acción tienen tamaño suficiente para touch (min 44px)?
□ ¿Los inputs tienen font-size ≥ 16px para evitar zoom en iOS?
```

---

## 21. OPENAPI Y TIPOS — CONTRATO BACKEND/FRONTEND

### 19.1 Flujo completo

```
sgth-backend/storage/app/openapi.yaml   (generado por Scramble)
        ↓  npm run types:sync  (o copia manual)
sgth-frontend/openapi.yaml
        ↓  npm run types:generate
src/types/api.generated.ts              ← NO editar nunca
        ↓  reexporta con aliases
src/types/api.ts                        ← único archivo a importar en componentes
```

### 19.2 Reglas de importación de tipos — ABSOLUTAS

```typescript
// ✅ SIEMPRE así — importar desde api.ts
import type { Servidor, Viatico, Canton, EstadoViatico } from '@/types/api'

// ❌ NUNCA importar desde el archivo generado directamente
import type { components } from '@/types/api.generated'

// ❌ NUNCA inventar tipos manualmente en componentes
type Servidor = { id: number; nombres: string; ... }
```

### 19.3 Cuándo regenerar los tipos

Regenerar ANTES de implementar cualquier pantalla que use un endpoint nuevo o modificado:

```bash
# Si el backend ya exportó el openapi.yaml actualizado:
npm run types:generate

# Si necesitas exportar desde el backend + regenerar en uno:
npm run types:sync
```

Señales de que necesitas regenerar:
- El backend agregó un nuevo endpoint que vas a consumir
- Un Form Request del backend cambió campos
- Un ApiResource del backend cambió la estructura de respuesta
- TypeScript marca errores en propiedades que deberían existir

### 19.4 Agregar tipos de conveniencia en api.ts

Si necesitas un tipo que no está en api.generated.ts (por ejemplo, un FormData local
que combina campos de varios schemas), agrégalo en `src/types/api.ts`:

```typescript
// src/types/api.ts — al final del archivo, en sección "Tipos locales de conveniencia"

// Tipo para el formulario de servidor (subset de campos editables)
export type ServidorFormData = {
  nombres: string
  apellidos: string
  cedula: string
  canton_nacimiento_id: number
  tiene_discapacidad: boolean
}

// Tipo para parámetros de búsqueda
export type ServidorParams = {
  page?: number
  per_page?: number
  search?: string
  unidad_id?: number
  estado?: EstadoContrato
}
```

### 19.5 Verificar tipos disponibles

Antes de crear un tipo manualmente, buscar en api.ts:

```bash
# En PowerShell — buscar si el tipo ya existe
Select-String -Path "src\types\api.ts" -Pattern "Servidor"
Select-String -Path "src\types\api.ts" -Pattern "Viatico"
```

Los tipos principales ya definidos en api.ts:
```
Geografía:    Provincia, Canton
Estructura:   UnidadAdministrativa, Puesto, TipoUnidad, ExtensionTelefonica
Expediente:   Servidor, ContratoServidor, DiscapacidadServidor,
              EnfermedadCatastroficaServidor, CuentaBancariaServidor, EntidadFinanciera
Viáticos:     Viatico, DestinoViatico, TransporteViatico, LiquidacionViatico,
              FacturaViatico, Comision, AutorizacionVuelo
Dispensario:  Beneficiario, HistoriaClinica, AgendaMedica, ConsultaMedica,
              Triaje, RecetaMedica, DiagnosticoCie10, AlergiaPaciente,
              AntecedentePaciente, InventarioMedicina
Nómina:       RolPago, DetalleRolPago
Asistencia:   Permiso, Vacacion
Enums:        EstadoViatico, TipoNombramiento, EstadoContrato, ConceptoFactura
API helpers:  ApiResponse<T>, PaginatedResponse<T>, LoginRequest, LoginResponse
```

---

## 22. REFERENCIAS

- Mantine v9 docs:       https://mantine.dev
- Mantine DataTable:     https://icflorescu.github.io/mantine-datatable
- Mantine UI (examples): https://ui.mantine.dev
- TanStack Query v5:     https://tanstack.com/query/v5
- ECharts:               https://echarts.apache.org/en/api.html
- Tabler Icons React:    https://tabler.io/icons
- OpenAPI types:         src/types/api.generated.ts
- Tipos conveniencia:    src/types/api.ts
- Regenerar tipos:       TYPES.md

---

_Fin del documento — SGTH Frontend Context v1.3_
