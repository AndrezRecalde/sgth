import { Stack, Text, type MantineTheme } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { CONDICION_LABELS, TONO_CONDICION, TONO_ESTADO_BIEN } from '../constants/estados'
import type { BienInformatico } from '../services/tipos'

/**
 * La serie, la ubicación y la condición se ocultan en pantallas angostas: en
 * un portátil la fila se partía en cinco líneas. Siguen en la ficha.
 */
const DESDE_LG = (theme: MantineTheme) => `(min-width: ${theme.breakpoints.lg})`

/** Dos líneas: lo principal arriba, el detalle atenuado debajo. */
const Doble = ({ arriba, abajo }: { arriba: React.ReactNode; abajo?: React.ReactNode }) => (
  <Stack gap={0}>
    <Text size="sm">{arriba}</Text>
    {abajo ? <Text size="xs" c="dimmed">{abajo}</Text> : null}
  </Stack>
)

export const columnasBienes = (accionesDe: (b: BienInformatico) => TableAction[]): DataTableColumn<BienInformatico>[] => [
  {
    accessor: 'codigo_institucional',
    title: 'Código',
    width: 150,
    render: (b) => <Doble arriba={<Text span fw={600} size="sm">{b.codigo_institucional}</Text>} abajo={b.codigo_anterior} />,
  },
  {
    accessor: 'tipo',
    title: 'Equipo',
    render: (b) => (
      <Doble
        arriba={[b.tipo?.nombre, b.subtipo?.nombre].filter(Boolean).join(' · ')}
        abajo={[b.marca?.nombre, b.modelo].filter(Boolean).join(' ') || null}
      />
    ),
  },
  { accessor: 'numero_serie', title: 'Serie', width: 150, visibleMediaQuery: DESDE_LG, render: (b) => b.numero_serie ?? 'S/N' },
  {
    accessor: 'custodio',
    title: 'Custodio',
    render: (b) => b.custodio
      ? <Doble arriba={b.custodio.nombre} abajo={b.unidad?.acronimo ?? b.unidad?.nombre} />
      : <Doble arriba={<Text span size="sm" c="dimmed">Sin custodio</Text>} abajo={b.unidad?.nombre} />,
  },
  { accessor: 'ubicacion', title: 'Ubicación', visibleMediaQuery: DESDE_LG, render: (b) => b.ubicacion?.nombre ?? '—' },
  {
    accessor: 'estado_operativo',
    title: 'Estado',
    width: 150,
    render: (b) => (
      <Stack gap={4} align="flex-start">
        <StatusBadge tone={TONO_ESTADO_BIEN[b.estado_operativo]}>{b.estado_etiqueta}</StatusBadge>
        {b.prestable ? <StatusBadge variant="outline">De préstamo</StatusBadge> : null}
      </Stack>
    ),
  },
  {
    accessor: 'condicion_fisica',
    title: 'Condición',
    width: 110,
    visibleMediaQuery: DESDE_LG,
    render: (b) => <StatusBadge variant="dot" tone={TONO_CONDICION[b.condicion_fisica]}>{CONDICION_LABELS[b.condicion_fisica]}</StatusBadge>,
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (b) => <TableActions actions={accionesDe(b)} />,
  },
]
