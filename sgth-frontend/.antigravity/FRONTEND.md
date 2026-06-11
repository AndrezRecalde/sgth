# SGTH Frontend — Documento de Contexto del Agente

# GAD Provincial de Esmeraldas

# Versión 2.0 — Mayo 2026

---

## 0. INSTRUCCIÓN DE USO

Este archivo es el contexto obligatorio del agente para el frontend del SGTH.
DEBES leer este archivo completo antes de escribir cualquier componente, página o hook.
Si alguna instrucción de este archivo contradice lo que el usuario pide en el chat,
prioriza este archivo y notifica la contradicción antes de proceder.

---

## 1. STACK TECNOLÓGICO — VERSIONES EXACTAS

```
Next.js               16.2.6       (App Router — OBLIGATORIO)
React                 19.x
TypeScript            ~5.x (strict: true)
@mantine/core         9.2.1        ← v9, NO v8
@mantine/hooks        9.2.1
@mantine/form         9.2.1        ← solo para migraciones, no formularios nuevos
@mantine/dates        9.2.1
@mantine/charts       9.2.1
@mantine/dropzone     9.2.1
@mantine/modals       9.2.1
@mantine/notifications 9.2.1
@mantine/schedule     9.2.1
mantine-datatable     9.2.2
@tabler/icons-react   (versión instalada)
@tanstack/react-query v5
react-hook-form       (versión instalada)  ← ESTÁNDAR para formularios
@hookform/resolvers   (versión instalada)
zustand               v5
zod                   v4            ← importar desde 'zod/v4'
axios                 (versión instalada)
echarts               (versión instalada)
echarts-for-react     (versión instalada)
```

---

## 2. REGLAS ABSOLUTAS — NUNCA VIOLAR

### 2.1 Componentes

- **TODOS los componentes de UI vienen de @mantine/core v9**. Nunca usar HTML nativo para UI.
- **Todas las tablas usan SgthTable** (wrapper de mantine-datatable).
- **Todas las gráficas estadísticas usan ECharts** a través de `echarts-for-react`.
- **Todos los iconos usan @tabler/icons-react**. NUNCA usar emojis como iconos.
- **Todos los inputs SIEMPRE tienen label visible**. Nunca omitir el label.
- **Todos los formularios usan el patrón "contained"** — label dentro del input.

### 2.2 Routing y arquitectura

- Usar Next.js **App Router** exclusivamente. Nunca Pages Router.
- Las rutas protegidas viven bajo `src/app/(dashboard)/`.
- Las rutas públicas viven bajo `src/app/(auth)/`.
- Middleware de Next.js vive en `src/proxy.ts`.

### 2.3 Estado y datos

- **TanStack Query v5** para todo el estado servidor.
- **Zustand v5** para estado cliente global.
- No usar useState para datos del API.
- No usar Context API para estado global.

### 2.4 Tipado — REGLA CRÍTICA

- TypeScript estricto. **NUNCA usar `any`**.
- **NUNCA usar `as unknown as T`**. Este patrón bypasea el sistema de tipos y oculta
  errores reales. Si aparece la necesidad, el problema es que el tipo en api.ts no
  coincide con el backend — corrígelo en api.ts.

```typescript
// ❌ NUNCA
(servidor as unknown as { id: number }).id;
data as unknown as PuestoConRelaciones[];

// ✅ SIEMPRE — corregir el tipo en api.ts
servidor.id(data as PuestoConRelaciones[]); // solo si el tipo es compatible
```

### 2.5 Estilos

- CSS Modules para estilos personalizados.
- Variables CSS de Mantine: `var(--mantine-color-emerald-6)`.
- NUNCA hardcodear colores hexadecimales en componentes.
- NUNCA usar Tailwind CSS.
- NUNCA usar `styles={{}}` inline para estilos reutilizables.

---

## 3. SISTEMA DE DISEÑO — TOKENS

### 3.1 Paleta de colores

```typescript
colors: {
  emerald: [
    '#ECFDF5', '#D1FAE5', '#A7F3D0', '#6EE7B7', '#34D399',
    '#10B981', '#059669', '#047857', '#065F46', '#064E3B',
  ],
}
primaryColor: 'emerald',
primaryShade: 6,

// Semánticos
Success:  emerald[6]  #059669
Warning:  #F59E0B
Danger:   #EF4444
Info:     #3B82F6
```

### 3.2 Tipografía

```
fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"

Display:  28px / weight 700  — títulos de página
H1:       20px / weight 600  — títulos de sección
Body:     14px / weight 400  — texto corriente
Small:    12px / weight 400  — captions
Tiny:     10px / weight 500  — badges
```

### 3.3 Espaciado

```
xs: 4px  |  sm: 8px  |  md: 16px  |  lg: 24px  |  xl: 32px
En props Mantine: p="md", gap="sm", mt="lg"
```

### 3.4 Radios

```
xs: 4px  |  sm: 6px  |  md: 8px  |  lg: 10px  |  xl: 12px
defaultRadius: 'md'
```

### 3.5 Elevación — sin sombras decorativas

La elevación se expresa con bordes, no sombras:

- Base: `border: 1px solid var(--mantine-color-default-border)`
- Hover: borde color emerald
- Focus: Mantine lo maneja automáticamente

---

## 4. APPSHELL — ESTRUCTURA VISUAL Y DARK MODE

### 4.1 Sidebar — colores adaptativos

El sidebar usa `var(--mantine-color-body)` para adaptarse al dark mode:

```css
/* Sidebar.module.css */
.sidebar {
  background-color: var(--mantine-color-body);
  border-right: 1px solid var(--mantine-color-default-border);
}

.navItem {
  color: var(--mantine-color-text);
  border-radius: var(--mantine-radius-md);
}

.navItemActive {
  background-color: var(--mantine-color-emerald-light);
  color: var(--mantine-color-emerald-filled);
  border-left: 3px solid var(--mantine-color-emerald-6);
}

.navItemHover:hover {
  background-color: var(--mantine-color-default-hover);
}

.sectionLabel {
  color: var(--mantine-color-dimmed);
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}
```

### 4.2 Dark Mode — implementación obligatoria

El toggle de dark mode vive DENTRO del menú de usuario
en el Topbar, no como botón independiente.

```tsx
// src/components/layout/Topbar.tsx — toggle dentro del menú de usuario
import { useMantineColorScheme } from "@mantine/core";
import { IconSun, IconMoon } from "@tabler/icons-react";

// Dentro de Menu.Dropdown:
<Box px="md" py="xs">
  <Group justify="space-between">
    <Group gap="sm">
      {colorScheme === "dark" ? <IconSun size={16} /> : <IconMoon size={16} />}
      <Text size="sm">Modo oscuro</Text>
    </Group>
    <Switch
      checked={colorScheme === "dark"}
      onChange={toggleColorScheme}
      size="xs"
      color="emerald"
      aria-label="Toggle modo oscuro"
    />
  </Group>
</Box>;
```

El topbar NO tiene botón de toggle independiente.
Esto mantiene el topbar limpio y agrupa las
preferencias del usuario en un solo lugar.

### 4.3 Reglas de dark mode

- NUNCA hardcodear colores que solo funcionen en un modo.
- ECharts recibe el tema según el modo activo:
  ```tsx
  const scheme = useComputedColorScheme('light')
  <ReactECharts theme={scheme === 'dark' ? 'dark' : undefined} />
  ```

### 4.4 AppShell responsive

```
Desktop (≥lg):  Sidebar 220px fijo
Tablet (md):    Sidebar 60px, solo íconos con Tooltip
Móvil (<md):    Sidebar oculto, Burger en Topbar abre Drawer
```

---

## 5. FORMULARIOS — SISTEMA OFICIAL

### 5.1 React Hook Form + Zod (estándar oficial)

**TODOS los formularios nuevos usan React Hook Form + Zod.**
Los formularios con @mantine/form existentes se migran progresivamente.

```tsx
import { useForm, Controller } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod/v4";
import { TextInput, Select, Button, Grid } from "@mantine/core";

const schema = z.object({
  nombre: z.string().min(2, "Mínimo 2 caracteres"),
  cedula: z
    .string()
    .length(10, "Debe tener 10 dígitos")
    .regex(/^\d+$/, "Solo dígitos"),
  genero: z.enum(["masculino", "femenino", "otro"]),
});

type FormData = z.infer<typeof schema>;

export function MiForm({ onSubmit }: { onSubmit: (data: FormData) => void }) {
  const contained = useContainedInput();

  const {
    register,
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: { nombre: "", cedula: "", genero: "masculino" },
  });

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      <Grid>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          {/* TextInput — usar register directamente */}
          <TextInput
            label="Nombre"
            placeholder="Primer nombre"
            error={errors.nombre?.message}
            {...contained}
            {...register("nombre")}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          {/* Select — SIEMPRE usar Controller */}
          <Controller
            name="genero"
            control={control}
            render={({ field }) => (
              <Select
                label="Género"
                data={[
                  { value: "masculino", label: "Masculino" },
                  { value: "femenino", label: "Femenino" },
                  { value: "otro", label: "Otro" },
                ]}
                error={errors.genero?.message}
                {...contained}
                value={field.value}
                onChange={field.onChange}
              />
            )}
          />
        </Grid.Col>
      </Grid>
      <Group justify="flex-end" mt="xl">
        <Button type="submit">Guardar</Button>
      </Group>
    </form>
  );
}
```

### 5.2 Cuándo usar Controller vs register

```
register:   TextInput, PasswordInput, Textarea, NumberInput (inputs HTML nativos)
Controller: Select, MultiSelect, DatePickerInput, Switch, Checkbox, Radio, FileInput
            — cualquier componente Mantine que NO sea un input HTML nativo
```

### 5.3 Label SIEMPRE obligatorio

```tsx
// ✅ CORRECTO
<TextInput label="Número de extensión" placeholder="Ej: 1234" />

// ❌ INCORRECTO — sin label
<TextInput placeholder="Número de extensión" />
```

### 5.4 Patrón "contained" — hook obligatorio

```tsx
import { useContainedInput } from '@/hooks/useContainedInput'

const contained = useContainedInput()

// Aplicar en todos los inputs
<TextInput label="Nombres" {...contained} {...register('nombres')} />
<Controller
  name="genero"
  control={control}
  render={({ field }) => (
    <Select label="Género" {...contained} value={field.value} onChange={field.onChange} />
  )}
/>
```

### 5.5 Schema Zod en archivo separado

```typescript
// src/features/expediente/schemas/servidor.schema.ts
import { z } from "zod/v4"; // ← SIEMPRE 'zod/v4', nunca 'zod'

export const servidorSchema = z.object({
  nombre: z.string().min(2, "Mínimo 2 caracteres"),
  cedula: z.string().length(10, "Debe tener 10 dígitos"),
});

export type ServidorFormData = z.infer<typeof servidorSchema>;
```

## 5.7 Botones de acción — patrón estándar

Los botones para crear nuevos registros usan siempre
este patrón: variant="light", color="emerald",
con ícono relevante a la izquierda.

```tsx
// ✅ PATRÓN ESTÁNDAR — botón de acción principal
<Button
  color="emerald"
  variant="light"
  leftSection={<IconPlus size={16} />}
  onClick={handleNuevo}
>
  Nuevo servidor
</Button>

// Ejemplos con íconos específicos por contexto:
<Button color="emerald" variant="light" leftSection={<IconCubePlus size={16} />}>
  Nueva unidad
</Button>
<Button color="emerald" variant="light" leftSection={<IconPlus size={16} />}>
  Nuevo usuario
</Button>

// ❌ NUNCA usar variant="filled" para botones de crear
// ❌ NUNCA usar color diferente a "emerald" para acción principal
// ✅ variant="default" para botones secundarios (Cancelar, Volver)
// ✅ variant="subtle" para acciones terciarias (enlaces, acciones de tabla)
```

---

## 6. TABLAS — SGTH TABLE

### 6.1 SgthTable — componente obligatorio

```tsx
import { SgthTable } from "@/components/ui/SgthTable";

<SgthTable
  records={data}
  columns={columns}
  fetching={isLoading}
  totalRecords={total}
  recordsPerPage={15}
  page={page}
  onPageChange={setPage}
/>;
```

Props por defecto en SgthTable (NO repetir):
`withTableBorder`, `withColumnBorders`, `borderRadius="md"`, `striped`,
`highlightOnHover`, `verticalSpacing="sm"`, `noRecordsText="No hay registros para mostrar"`

### 6.2 Acciones — menú obligatorio

```tsx
import { TableActions } from '@/components/ui/TableActions'

{
  accessor: 'acciones',
  title: '',
  width: 50,
  render: (record) => (
    <TableActions actions={[
      { label: 'Editar',   icon: <IconEdit size={14} />,  color: 'blue', onClick: () => onEdit(record) },
      { label: 'Eliminar', icon: <IconTrash size={14} />, color: 'red',  onClick: () => onDelete(record) },
    ]} />
  ),
},
```

### 6.3 Columnas

- Siempre en archivo separado: `nombre.columns.tsx`
- Tipadas con `DataTableColumn<T>`
- Columna de acciones: última, `width: 50`

### 6.4 Paginación

- Server-side siempre. `per_page` default 15.

---

## 7. EMPTY STATES Y LOADING STATES

### 7.1 EmptyState — componente estándar

```tsx
// src/components/ui/EmptyState.tsx
import { Stack, Text, ThemeIcon } from "@mantine/core";
import type { TablerIconsProps } from "@tabler/icons-react";

interface Props {
  icon: React.FC<TablerIconsProps>;
  title: string;
  description?: string;
  action?: React.ReactNode;
}

export function EmptyState({ icon: Icon, title, description, action }: Props) {
  return (
    <Stack align="center" gap="md" py="xl">
      <ThemeIcon size={56} radius="xl" variant="light" color="gray">
        <Icon size={28} />
      </ThemeIcon>
      <Stack align="center" gap="xs">
        <Text fw={600} size="md" c="dimmed">
          {title}
        </Text>
        {description && (
          <Text size="sm" c="dimmed" ta="center" maw={360}>
            {description}
          </Text>
        )}
      </Stack>
      {action}
    </Stack>
  );
}
```

Uso:

```tsx
<EmptyState
  icon={IconUsers}
  title="No hay servidores registrados"
  description="Comienza agregando el primer servidor al sistema."
  action={
    <Button leftSection={<IconPlus size={14} />} color="emerald">
      Nuevo servidor
    </Button>
  }
/>
```

### 7.2 Cuándo usar EmptyState

```
Tabla vacía (primer acceso):  EmptyState con ícono + título + acción
Sin resultados de búsqueda:   EmptyState con "Sin resultados para 'X'"
Lista pequeña vacía en card:  Text size="sm" c="dimmed" simple
```

### 7.3 Loading — Skeleton estándar

```tsx
// Tabla cargando
if (isLoading) return (
  <Stack gap="xs">
    {Array.from({ length: 5 }).map((_, i) => (
      <Skeleton key={i} height={44} radius="md" />
    ))}
  </Stack>
)

// Formulario cargando (opciones de selects)
<Box style={{ position: 'relative' }}>
  <LoadingOverlay visible={!isReady} zIndex={10} />
  <form>...</form>
</Box>
```

---

## 8. METADATOS POR PÁGINA

### 8.1 Formato de título

```
GADPE — {Nombre del Módulo}

Ejemplos:
  GADPE — Estructura Organizacional
  GADPE — Expediente Digital
  GADPE — Nómina
  GADPE — Viáticos
  GADPE — Dispensario Médico
  GADPE — Usuarios del Sistema
```

### 8.2 Implementación en páginas estáticas

```tsx
// src/app/(dashboard)/estructura/page.tsx
import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "GADPE — Estructura Organizacional",
  description:
    "Gestión del organigrama institucional y puestos del GAD Provincial de Esmeraldas",
};

export default function EstructuraPage() {
  // 'use client' NO se puede usar con metadata estática
  // Si la página necesita interactividad, extraer a componente hijo
  return <EstructuraView />;
}
```

### 8.3 Metadata global en layout.tsx

```tsx
// src/app/layout.tsx
export const metadata: Metadata = {
  title: {
    default: "GADPE — Sistema de Gestión de Talento Humano",
    template: "%s | SGTH",
  },
  description:
    "Sistema Integral de Gestión de Talento Humano — GAD Provincial de Esmeraldas",
};
```

**Nota:** Los page.tsx con `export const metadata` NO pueden tener `'use client'`.
Crear un componente hijo cliente y exportarlo desde el page.tsx sin directiva.

---

## 9. TOQUES VISUALES MODERNOS

### 9.1 Card con borde izquierdo semántico

```tsx
<Card withBorder radius="md" style={{ borderLeft: `4px solid var(--mantine-color-emerald-6)` }}>
```

### 9.2 Badges con punto de color

```tsx
<Badge variant="dot" color="emerald" size="sm">Activo</Badge>
<Badge variant="dot" color="gray"    size="sm">Inactivo</Badge>
<Badge variant="dot" color="red"     size="sm">Cancelado</Badge>
```

### 9.3 Dividers con label de sección

```tsx
<Divider
  label={
    <Group gap="xs">
      <IconUser size={14} />
      <Text size="sm" fw={600}>
        Datos personales
      </Text>
    </Group>
  }
  labelPosition="left"
  my="md"
/>
```

### 9.4 KPI Stats Cards

```tsx
<Paper withBorder radius="lg" p="lg">
  <Group justify="space-between" align="flex-start">
    <div>
      <Text size="xs" c="dimmed" tt="uppercase" fw={600} mb={4}>
        Servidores activos
      </Text>
      <Text size="xl" fw={700}>
        247
      </Text>
      <Text size="xs" c="emerald" mt={4}>
        +3 este mes
      </Text>
    </div>
    <ThemeIcon size={44} radius="md" variant="light" color="emerald">
      <IconUsers size={22} />
    </ThemeIcon>
  </Group>
</Paper>
```

### 9.5 Segmented control para vistas alternativas

```tsx
<SegmentedControl
  value={vista}
  onChange={(v) => setVista(v as "tabla" | "tarjetas")}
  color="emerald"
  data={[
    {
      value: "tabla",
      label: (
        <Group gap="xs">
          <IconList size={14} />
          Tabla
        </Group>
      ),
    },
    {
      value: "tarjetas",
      label: (
        <Group gap="xs">
          <IconGridDots size={14} />
          Tarjetas
        </Group>
      ),
    },
  ]}
/>
```

---

## 10. PATRONES PROHIBIDOS

```
❌ Emojis como iconos en la UI
❌ <table> HTML directo para datos
❌ fetch() nativo — usar axios
❌ useState para datos del API
❌ Colores hardcodeados hex en componentes
❌ @mantine/charts para gráficas de datos
❌ Pages Router de Next.js
❌ TypeScript any
❌ as unknown as T — nunca bypasear el tipo
❌ CSS Tailwind
❌ Importar desde 'zod' — usar 'zod/v4'
❌ Input sin label — todos tienen label obligatorio
❌ DataTable directo — usar SgthTable
❌ ActionIcon individuales en tabla — usar TableActions
❌ styles={{}} inline para estilos reutilizables
❌ Context API para estado global
❌ clearAuth() que no elimine cookies de sesión
❌ Formularios nuevos con @mantine/form (usar RHF)
```

---

## 11. ARQUITECTURA DE CARPETAS

```
sgth-frontend/
├── .antigravity/FRONTEND.md
├── src/
│   ├── app/
│   │   ├── (auth)/login/page.tsx
│   │   ├── (dashboard)/
│   │   │   ├── layout.tsx
│   │   │   ├── page.tsx
│   │   │   ├── estructura/page.tsx
│   │   │   ├── expediente/page.tsx
│   │   │   ├── nomina/page.tsx
│   │   │   ├── asistencia/page.tsx
│   │   │   ├── viaticos/page.tsx
│   │   │   ├── dispensario/page.tsx
│   │   │   ├── usuarios/page.tsx
│   │   │   └── autoservicio/page.tsx
│   │   ├── layout.tsx          ← metadata global + MantineProvider + ColorSchemeScript
│   │   └── globals.css
│   ├── components/
│   │   ├── layout/
│   │   │   ├── AppShell.tsx
│   │   │   ├── Sidebar.tsx + Sidebar.module.css
│   │   │   ├── Topbar.tsx
│   │   │   └── NavItem.tsx
│   │   └── ui/
│   │       ├── SgthTable.tsx
│   │       ├── TableActions.tsx
│   │       ├── EmptyState.tsx
│   │       └── PageHeader.tsx
│   ├── features/
│   │   └── {modulo}/
│   │       ├── components/     ← NombreForm, NombreModal, nombre.columns
│   │       ├── hooks/          ← useNombre, useNombreMutations
│   │       ├── schemas/        ← nombre.schema.ts
│   │       └── services/       ← nombreService.ts
│   ├── hooks/
│   │   ├── useAuth.ts
│   │   ├── useContainedInput.ts
│   │   └── useMobileBreakpoint.ts
│   ├── lib/axios.ts
│   ├── store/
│   │   ├── auth.store.ts
│   │   └── ui.store.ts
│   ├── config/
│   │   ├── theme.ts
│   │   ├── routes.ts
│   │   └── nav.ts
│   ├── styles/inputs.contained.module.css
│   └── types/
│       ├── api.generated.ts    ← NO editar
│       └── api.ts
├── src/proxy.ts                ← middleware Next.js 16
├── openapi.yaml
└── .env.local
```

---

## 12. CONVENCIONES DE CÓDIGO

### 12.1 Naming

```
Componentes:   PascalCase         → ServidorCard.tsx
Hooks:         camelCase useX     → useServidores.ts
Services:      camelCase          → servidorService.ts
Stores:        camelCase .store   → auth.store.ts
Schemas:       kebab.schema.ts    → servidor.schema.ts
Columns:       kebab.columns.tsx  → servidor.columns.tsx
Types:         PascalCase         → ServidorFormData
Constants:     UPPER_SNAKE_CASE   → MAX_PER_PAGE
```

### 12.2 Límites de líneas

```
Páginas:    ≤ 100  |  Componentes: ≤ 200  |  Hooks: ≤ 150
Services:   ≤ 100  |  Schemas:     ≤  80  |  Columns: ≤ 150
```

---

## 13. OPENAPI Y TIPOS

```
sgth-backend/storage/app/openapi.yaml → copiar → sgth-frontend/openapi.yaml
                                           ↓ npm run types:generate
                                      src/types/api.generated.ts  ← NO editar
                                           ↓
                                      src/types/api.ts            ← único a importar
```

```typescript
// ✅ SIEMPRE
import type { Servidor, Canton } from "@/types/api";

// ❌ NUNCA — desde el generado
import type { components } from "@/types/api.generated";
```

---

## 14. AUTENTICACIÓN

```
1. POST /auth/login → { token, primer_login, usuario }
2. Guardar token en localStorage + cookie sgth_token + Zustand
3. Si primer_login === true → /cambiar-password
4. clearAuth() elimina localStorage + cookies sgth_token + sgth_primer_login
```

---

## 15. CHECKLIST ANTES DE COMMIT

```
□ ¿Archivo dentro del límite de líneas?
□ ¿Componente hace una sola cosa?
□ ¿Lógica en hooks, no en componentes?
□ ¿Columnas en archivo .columns.tsx separado?
□ ¿Schema Zod en archivo .schema.ts separado?
□ ¿Sin console.log de debug?
□ ¿Sin any ni as unknown?
□ ¿Iconos de @tabler/icons-react (sin emojis)?
□ ¿Inputs con patrón contained Y con label?
□ ¿Formulario nuevo usa RHF + zodResolver?
□ ¿Zod importa desde 'zod/v4'?
□ ¿Grid.Col con span={{ base, sm }}?
□ ¿Modal con fullScreen={isMobile}?
□ ¿Tablas usan SgthTable?
□ ¿Acciones de tabla usan TableActions?
□ ¿Página tiene metadata exportada?
□ ¿Colores usan variables CSS de Mantine?
□ ¿Componentes funcionan en dark mode?
```

---

## 16. REFERENCIAS

- Mantine v9: https://mantine.dev
- Mantine DataTable: https://icflorescu.github.io/mantine-datatable
- React Hook Form: https://react-hook-form.com
- TanStack Query v5: https://tanstack.com/query/v5
- ECharts: https://echarts.apache.org
- Tabler Icons: https://tabler.io/icons
- Zod v4: https://zod.dev

---

_Fin del documento — SGTH Frontend Context v2.0 — Mayo 2026_
