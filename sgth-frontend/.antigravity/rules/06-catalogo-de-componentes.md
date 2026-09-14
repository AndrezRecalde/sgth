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
| `PageHeader` | Título, descripción, acciones | Sin icono. `backHref` u `onBack` y `estado` en detalles |
| `Toolbar` | Filtros sobre un listado | Campos con `useContainedInput('sm')` |
| `SectionCard` | Bloque con título dentro de una página | Sustituye al `Divider` con etiqueta |
| `StatCard` | Indicador numérico de un tablero | `tone` solo si el número es bueno o malo |
| `StatusBadge` | Toda etiqueta: estado, señal o categoría | Recibe un tono, no un color; sin tono es neutra |
| `CountBadge` | Una cifra suelta en una pastilla | `destacado` si pide acción |
| `LegendBadge` | Leyenda de un gráfico | Único que recibe color: el color es el dato |
| `DetailList` | Pares etiqueta/valor de un detalle | Valor ausente se dibuja como guion |
| `DataState` | Los cuatro estados de una consulta | Envuelve la tabla o la lista |
| `EmptyState` | Estado vacío | Dice qué falta **y** qué hacer |
| `SgthTable` | La única tabla del sistema | `PAGINACION_ES` si hay paginación |
| `TableActions` | Menú de acciones de una fila | Última columna, `width: 50` |
| `SgthModal` | Base de todo modal | Nunca el `Modal` de Mantine directo |
| `ModalFooter` | Pie de un modal de acción | Pegajoso; principal relleno |
| `FormModal` | Modal de formulario | `SgthModal` + `<form>` + `ModalFooter` |
| `MotivoModal` | Confirmación que pide un motivo | `destructiva` al anular o rechazar |
| `confirmar` | Confirmación de acción irreversible | Nunca el diálogo del navegador |

## Tablas

**Ninguna pantalla usa `DataTable` directo ni una tabla HTML.** `SgthTable`
fija bordes, densidad, textos en español y comportamiento; cualquier prop de
`mantine-datatable` sigue disponible y sobrescribe los valores por defecto.

Tampoco el `Table` de Mantine: ESLint rechaza los dos fuera de
`src/components/`. Lo que parecía tabla se resuelve así:

| Qué es | Cómo |
|---|---|
| Filas de datos, aunque sean tres dentro de un modal | `SgthTable` con `minHeight` bajo y `noRecordsText` propio |
| Pares etiqueta/valor de un registro | `DetailList` (antes, una tabla vertical) |
| Filas que se capturan (ítems de una compra) | `SgthTable` con `records={fields}` y un `Controller` por celda |
| Un ranking corto en un tablero | Filas de `Group`, como «Diagnósticos más frecuentes» |

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

## Etiquetas

**Ninguna pantalla importa `Badge` de Mantine.** ESLint lo rechaza fuera de
`src/components/`. El color de una etiqueta sale de lo que significa:

| Qué muestra | Cómo | Ejemplo |
|---|---|---|
| Un estado | `StatusBadge` con el tono de su mapa | Pendiente, Aprobado, Anulado |
| Una señal buena o mala | `StatusBadge` con tono directo | «Vence hoy» `warning`, «GPS inactivo» `danger` |
| Una categoría | `StatusBadge` **sin tono** | Tipo de permiso, régimen, especialidad, parentesco, rol |
| Una cifra sola | `CountBadge` | Permisos del mes, ítems de una compra |
| La leyenda de un dibujo | `LegendBadge` | Condiciones del odontograma |

**Las categorías van neutras.** LOSEP no es mejor ni peor que Código del
Trabajo, ni Odontología que Medicina general. Antes cada una tenía su color
—azul, violeta, turquesa, uva, cian— y el ámbar de Código del Trabajo se leía
como advertencia. Se distinguen por el texto.

Los tonos, por lo que significan en un flujo:

```
success   terminó bien                  aprobado, liquidado, apto, vigente
warning   espera algo de alguien        pendiente, solicitado, en espera, por vencer
danger    terminó mal o se anuló        rechazado, anulado, cancelado, vencido
info      en curso, sin nada que hacer  en proceso, en comisión, en evaluación
neutral   ni empezó ni cuenta           borrador, cerrado, contabilizado, categorías
```

`variant="dot"` para una marca discreta, `outline` para un código (CIE-10,
lote). `filled` solo para una alerta; nunca en una categoría.

### Estados de un registro

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
dentro, y el mismo concepto acababa en tonos distintos según la pantalla:
«Anulado» salía rojo en el odontograma y naranja en los certificados, y «En
espera» gris en la atención médica y naranja en la tabla de turnos.

Si el color de un estado se necesita fuera de una etiqueta —la viñeta de un
`Timeline`, un `Alert`—, se toma del mismo mapa:
`color={SEMANTIC_COLOR[TONO_VIATICO[estado]]}`.

## Modales

**Ninguna pantalla importa `Modal` de Mantine.** ESLint lo rechaza fuera de
`src/components/`. Hay cuatro piezas, de la más común a la más libre:

| Pieza | Cuándo |
|---|---|
| `confirmar()` | Sí o no ante una acción irreversible |
| `MotivoModal` | Sí o no, pero con un motivo escrito: rechazar, anular, devolver |
| `FormModal` | Capturar y guardar: un formulario con Cancelar y Guardar |
| `SgthModal` + `ModalFooter` | Todo lo demás: asistentes por pasos, formulario en un componente hijo, acción sin `<form>`, consulta |

`SgthModal` es la base de todas: pone la pantalla completa sin radio por debajo
de 768 px. Una cuarta parte de los modales se olvidaba de hacerlo a mano.

`ModalFooter` es el pie de todo modal de acción. Cancelar va antes del botón
principal, que es `filled` —es el envío de un formulario— y toma el color del
tema. Solo cambia a rojo con `destructiva`. El pie es pegajoso: con un error de
validación en una laptop de 768 px, los botones quedaban debajo del borde.
Antes cada modal escribía el suyo, y guardar salía en verde, azul, naranja o
turquesa, relleno o tenue, con o sin icono.

```tsx
// Captura
<FormModal
  opened={opened}
  onClose={cerrar}
  title="Nueva extensión"
  onSubmit={handleSubmit(guardar)}
  submitLabel="Registrar extensión"
  submitting={crear.isPending}
>
  …campos…
</FormModal>

// El formulario vive en un hijo: el botón lo envía por su id
<SgthModal opened={opened} onClose={cerrar} title="Nuevo puesto">
  <PuestoForm onSubmit={guardar} />
  <ModalFooter onCancel={cerrar} form="puesto-form" submitLabel="Crear puesto" />
</SgthModal>

// Asistente: «Atrás» a la izquierda; sin onSubmit, el último paso envía el form
<ModalFooter
  onCancel={cerrar}
  leftSection={paso > 0 && <Button variant="default" onClick={atras}>Atrás</Button>}
  onSubmit={esUltimo ? undefined : siguiente}
  submitLabel={esUltimo ? 'Registrar' : 'Siguiente'}
/>

// Consulta: solo cerrar
<ModalFooter onCancel={cerrar} cancelLabel="Cerrar" sinPrincipal />
```

El título de un modal es texto, sin icono decorativo, por la misma razón que el
de `PageHeader`.

Los modales de catálogo que agregan filas en línea sobre una tabla —factores de
riesgo, normativa legal— no tienen pie: se cierran con la X.

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
