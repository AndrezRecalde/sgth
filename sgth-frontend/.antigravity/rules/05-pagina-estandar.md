# 05 · La página estándar

## Anatomía

Toda pantalla del área autenticada tiene la misma estructura vertical:

```
┌─ AppShell.Main ─────────────── padding md (móvil) / lg (escritorio)
│  ┌─ PageShell ──────────────── máx. 1600px, centrado, gap lg
│  │
│  │   PageHeader      título · descripción · acciones
│  │   Toolbar         filtros y búsqueda        (si es un listado)
│  │   contenido       tabla, tarjetas, formulario, pestañas
│  │
│  └────────────────────────────────────────────────────────────
└───────────────────────────────────────────────────────────────
```

**El padding lo pone `AppShell.Main`, no la página.** Ponerlo en cada pantalla
obligaría a que 51 archivos se acuerden, y el que se olvide queda pegado al
borde. `PageShell` aporta el ancho de lectura y el ritmo vertical.

`PageShell` acepta `fluid` para lienzos que necesitan todo el ancho
(organigrama, odontograma, calendarios). Nunca en un listado.

## Ejemplo completo

```tsx
// page.tsx
import type { Metadata } from 'next'
import { ServidoresView } from './ServidoresView'

export const metadata: Metadata = {
  title: 'Servidores',
  description: 'Directorio de servidores del GAD Provincial de Esmeraldas',
}

export default function ServidoresPage() {
  return <ServidoresView />
}
```

```tsx
// ServidoresView.tsx  ('use client' en la primera línea)

export function ServidoresView() {
  const [pagina, setPagina] = useState(1)
  const { data, isLoading, error } = useServidores({ pagina })

  return (
    <PageShell>
      <PageHeader
        title="Servidores"
        description="Directorio del talento humano institucional"
        actions={
          <Button variant="light" leftSection={<IconPlus size={16} />} onClick={abrirModal}>
            Nuevo servidor
          </Button>
        }
      />

      <Toolbar>
        <TextInput label="Buscar" {...contained} value={busqueda} onChange={onBuscar} />
        <Select label="Unidad" {...contained} data={unidades} />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!data?.datos.length}
        emptyProps={{
          icon: IconUsers,
          title: 'No hay servidores registrados',
          description: 'Comienza agregando el primero.',
          action: <Button variant="light" onClick={abrirModal}>Nuevo servidor</Button>,
        }}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={data.datos}
          columns={columnasServidor}
          totalRecords={data.total}
          recordsPerPage={15}
          page={pagina}
          onPageChange={setPagina}
        />
      </DataState>
    </PageShell>
  )
}
```

## Metadata

**Toda página exporta `metadata`.** El template global es `GADPE — %s`, así que
`title` lleva solo el nombre del módulo:

```tsx
export const metadata: Metadata = {
  title: 'Nómina',                        // → "GADPE — Nómina"
  description: 'Gestión de roles de pago y períodos de nómina',
}
```

Un `page.tsx` con `export const metadata` **no puede** llevar la directiva de
cliente. Si la pantalla necesita interactividad, la lógica va a una vista
hermana, como en el ejemplo de arriba.

En rutas dinámicas donde el título depende del registro se usa
`generateMetadata`; si el dato solo existe en el cliente, basta un título
estático descriptivo ("Detalle de convocatoria").

## Los cuatro estados de una pantalla con datos

Se resuelven con `DataState`, siempre los cuatro:

| Estado | Qué se ve |
|---|---|
| Cargando | Filas de esqueleto del alto real de la lista |
| Error | `Alert` rojo con el motivo, nunca una pantalla en blanco |
| Vacío | `EmptyState`: qué falta **y** qué hacer al respecto |
| Con datos | El contenido |

Un cargador centrado a media pantalla no es un estado de carga aceptable en un
listado: descoloca el diseño y no anticipa la forma del resultado.

## Título de página

`PageHeader` **no lleva icono decorativo**. La ubicación ya la comunican el
menú lateral y las migas de pan; un icono grande junto al título repite esa
información y consume espacio vertical.

En pantallas de detalle sí se usa `backHref`, que dibuja la flecha de retorno.
