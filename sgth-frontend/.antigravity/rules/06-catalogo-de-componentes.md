# 06 · Catálogo de componentes

Todo sale de `src/components/ui`, y se importa desde el barril:

```tsx
import { PageShell, PageHeader, SgthTable, DataState } from '@/components/ui'
```

**Antes de construir un componente, revisa si uno de estos lo resuelve.** Si de
verdad falta algo transversal, se agrega al catálogo — no se resuelve dentro
del módulo.

## Tabla de referencia

| Componente | Para qué | Nota |
|---|---|---|
| `PageShell` | Contenedor de toda página | `fluid` solo para lienzos |
| `PageHeader` | Título, descripción, acciones | Sin icono. `backHref` en detalles |
| `Toolbar` | Filtros sobre un listado | Campos con `useContainedInput('sm')` |
| `SectionCard` | Bloque con título dentro de una página | Sustituye al `Divider` con etiqueta |
| `StatCard` | Indicador numérico de un tablero | `tone` solo si el número es bueno o malo |
| `StatusBadge` | Etiqueta de estado | Recibe un tono semántico, no un color |
| `DetailList` | Pares etiqueta/valor de un detalle | Valor ausente se dibuja como guion |
| `DataState` | Los cuatro estados de una consulta | Envuelve la tabla o la lista |
| `EmptyState` | Estado vacío | Dice qué falta **y** qué hacer |
| `SgthTable` | La única tabla del sistema | `PAGINACION_ES` si hay paginación |
| `TableActions` | Menú de acciones de una fila | Última columna, `width: 50` |
| `FormModal` | Modal de formulario | Pantalla completa en móvil |
| `confirmar` | Confirmación de acción irreversible | Nunca el diálogo del navegador |

## Tablas

**Ninguna pantalla usa `DataTable` directo ni una tabla HTML.** `SgthTable`
fija bordes, densidad, textos en español y comportamiento; cualquier prop de
`mantine-datatable` sigue disponible y sobrescribe los valores por defecto.

Las columnas van **siempre** en un archivo aparte, tipadas:

```tsx
// servidor.columns.tsx
import type { DataTableColumn } from 'mantine-datatable'

export const columnasServidor: DataTableColumn<Servidor>[] = [
  { accessor: 'cedula', title: 'Cédula', width: 110 },
  { accessor: 'nombre_completo', title: 'Servidor' },
  {
    accessor: 'estado',
    title: 'Estado',
    render: (s) => <StatusBadge tone={TONO_ESTADO[s.estado]}>{s.estado_label}</StatusBadge>,
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (s) => (
      <TableActions
        actions={[
          { label: 'Editar', icon: <IconEdit size={14} />, onClick: () => editar(s) },
          { label: 'Eliminar', icon: <IconTrash size={14} />, color: 'red', onClick: () => borrar(s) },
        ]}
      />
    ),
  },
]
```

### Paginación

**Siempre del lado del servidor**, con `per_page` 15 por defecto. Traer tres
mil servidores al navegador para paginar en memoria hace lenta la pantalla y
castiga a quien la abre desde una conexión de la Prefectura.

Los textos en español van en `PAGINACION_ES`, que se esparce en las tablas
paginadas. Está aparte de `SgthTable` porque `mantine-datatable` tipa la
paginación como una unión discriminada y fijar `paginationText` dentro del
envoltorio genérico impide a TypeScript resolver la variante. La alternativa
habría sido una aserción de tipo, que está prohibida.

### Acciones de fila

Van en `TableActions`, nunca como iconos sueltos en la fila. Tres o cuatro
iconos por fila multiplicados por quince filas son cincuenta objetivos de clic
compitiendo con los datos.

- `hidden` para lo que el usuario no puede hacer por permisos.
- `disabled` para lo que no puede hacer por el estado del registro.
- `color: 'red'` solo en acciones destructivas.

## Estados de una pantalla

`DataState` envuelve el contenido y resuelve carga, error y vacío. Para una
lista pequeña dentro de una tarjeta no hace falta montarlo: basta un
`<Text size="sm" c="dimmed">`.

## Estados de un registro

El significado y el color se deciden en un solo sitio. Cada módulo declara el
mapa **de estado a tono semántico**, no a color:

```ts
// features/certificaciones/constants/estados.ts
import type { SemanticTone } from '@/config/design.tokens'

export const TONO_SOLICITUD: Record<EstadoSolicitud, SemanticTone> = {
  aprobada:  'success',
  pendiente: 'warning',
  negada:    'danger',
  borrador:  'neutral',
}
```

```tsx
<StatusBadge tone={TONO_SOLICITUD[s.estado]}>{s.estado_label}</StatusBadge>
```

Antes cada módulo declaraba su propio mapa con nombres de color de Mantine
dentro, y el mismo concepto acababa en tonos distintos según la pantalla.

## Modales

`FormModal` para captura; `confirmar()` para acciones irreversibles.

`FormModal` resuelve de una vez lo que cada modal repetía: pantalla completa en
móvil, el formulario envolviendo el contenido para que Enter envíe, el pie con
Cancelar y Guardar siempre igual, y el cuerpo con scroll propio para que el pie
no se pierda.

```tsx
confirmar({
  title: 'Eliminar extensión',
  message: <>Se eliminará la extensión <b>{ext.numero}</b>. No se puede deshacer.</>,
  destructiva: true,
  onConfirm: () => eliminar.mutate(ext.id),
})
```

## Botones

```tsx
// Acción principal de la pantalla
<Button variant="light" leftSection={<IconPlus size={16} />}>Nuevo servidor</Button>

// Secundaria: cancelar, volver
<Button variant="default">Cancelar</Button>

// Terciaria: enlaces, acciones dentro de una tarjeta
<Button variant="subtle">Ver detalle</Button>

// Destructiva
<Button variant="light" color="red">Eliminar</Button>
```

El color de la acción principal lo pone el tema (`primaryColor`); no se escribe
en cada botón. `variant="filled"` se reserva para el envío de un formulario en
un flujo de un solo paso, no para abrir un modal de creación.

## Iconos

Todos de `@tabler/icons-react`, tamaño 14–16 en línea y 18–20 en controles.
**Nunca emojis en la interfaz**: no escalan, no heredan color, se ven distinto
en cada sistema operativo y no tienen nombre accesible.

## Gráficas

ECharts a través de `echarts-for-react`, con el tema sincronizado al esquema de
color (ver [03](03-design-system.md)). `@mantine/charts` no se usa.
