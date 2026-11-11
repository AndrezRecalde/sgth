import { Select, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import type { GestionDelManual } from '../services/manualPuestosService'

/*
| Las gestiones del Manual de Puestos y la unidad de hoy de cada una (P4,
| PR 12). Las erratas del manual son filas aparte («Gestión de Fiiscalización»):
| casi todas tienen su unidad sugerida y no piden nada.
*/
export const getEquivalenciasManualColumns = (
  { unidades, guardando, alCambiar }: {
    unidades: { value: string; label: string }[]
    /** La gestión que se está guardando. */
    guardando: string | null
    alCambiar: (g: GestionDelManual, unidadId: number | null) => void
  },
): DataTableColumn<GestionDelManual>[] => [
  {
    accessor: 'nombre',
    title: 'Gestión en el manual',
    render: ({ nombre, fichas }) => (
      <div>
        <Text size="sm">{nombre}</Text>
        <Text size="xs" c="dimmed">{fichas} {fichas === 1 ? 'ficha' : 'fichas'}</Text>
      </div>
    ),
  },
  {
    accessor: 'origen',
    title: 'Cómo se decidió',
    width: 150,
    render: ({ unidad }) => {
      if (!unidad) return <StatusBadge tone="warning">Falta elegirla</StatusBadge>
      return unidad.origen === 'elegida'
        ? <StatusBadge tone="success">Elegida por TH</StatusBadge>
        : <StatusBadge tone="info">Sugerida</StatusBadge>
    },
  },
  {
    accessor: 'unidad',
    title: 'Unidad de hoy',
    width: 380,
    render: (g) => (
      <Select
        aria-label={`Unidad de hoy de ${g.nombre}`}
        size="xs"
        placeholder="Elija la unidad"
        data={unidades}
        searchable
        allowDeselect={false}
        // Quitar la elegida devuelve la gestión a la sugerida.
        clearable={g.unidad?.origen === 'elegida'}
        disabled={guardando === g.gestion}
        value={g.unidad ? String(g.unidad.id) : null}
        onChange={(v) => {
          const unidadId = v ? Number(v) : null
          if (unidadId !== (g.unidad?.id ?? null)) alCambiar(g, unidadId)
        }}
        comboboxProps={{ withinPortal: true }}
      />
    ),
  },
]
