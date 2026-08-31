import { Text, Badge } from '@mantine/core'
import { TableActions } from '@/components/ui/TableActions'
import { IconEye, IconLock } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import type { Nomina, EstadoNomina } from '@/types/api'

const ESTADO_COLORS: Record<EstadoNomina, string> = {
  borrador:       'gray',
  en_proceso:     'blue',
  cerrada:        'orange',
  contabilizada:  'violet',
  pagada:         'emerald',
}

const ESTADO_LABELS: Record<EstadoNomina, string> = {
  borrador:       'Borrador',
  en_proceso:     'En proceso',
  cerrada:        'Cerrada',
  contabilizada:  'Contabilizada',
  pagada:         'Pagada',
}

function formatMonto(v?: number | string | null): string {
  if (v === null || v === undefined) return '—'
  return `$${Number(v).toLocaleString('es-EC', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`
}

type Handlers = {
  onVer:    (n: Nomina) => void
  onCerrar: (n: Nomina) => void
}

export const getNominaColumns = (
  { onVer, onCerrar }: Handlers
): DataTableColumn<Nomina>[] => [
  {
    accessor: 'periodo',
    title:    'Período',
    render: ({ periodo }) => (
      <Text size="sm" fw={600} ff="monospace">{periodo}</Text>
    ),
  },
  {
    accessor: 'fecha_inicio',
    title:    'Inicio',
    width:    110,
    render: ({ fecha_inicio }) => (
      <Text size="sm">
        {fecha_inicio
          ? new Date(fecha_inicio).toLocaleDateString('es-EC', {
              timeZone: 'UTC',
              day: '2-digit', month: '2-digit', year: 'numeric',
            })
          : '—'}
      </Text>
    ),
  },
  {
    accessor: 'fecha_fin',
    title:    'Fin',
    width:    110,
    render: ({ fecha_fin }) => (
      <Text size="sm">
        {fecha_fin
          ? new Date(fecha_fin).toLocaleDateString('es-EC', {
              timeZone: 'UTC',
              day: '2-digit', month: '2-digit', year: 'numeric',
            })
          : '—'}
      </Text>
    ),
  },
  {
    accessor: 'total_ingresos',
    title:    'Ingresos',
    width:    120,
    render: ({ total_ingresos }) => (
      <Text size="sm" ff="monospace" c="emerald">
        {formatMonto(total_ingresos)}
      </Text>
    ),
  },
  {
    accessor: 'total_descuentos',
    title:    'Descuentos',
    width:    120,
    render: ({ total_descuentos }) => (
      <Text size="sm" ff="monospace" c="red">
        {formatMonto(total_descuentos)}
      </Text>
    ),
  },
  {
    accessor: 'total_neto',
    title:    'Neto a pagar',
    width:    130,
    render: ({ total_neto }) => (
      <Text size="sm" ff="monospace" fw={600}>
        {formatMonto(total_neto)}
      </Text>
    ),
  },
  {
    accessor: 'estado',
    title:    'Estado',
    width:    120,
    render: ({ estado }) => (
      <Badge
        color={ESTADO_COLORS[estado] ?? 'gray'}
        variant="light" size="sm"
      >
        {ESTADO_LABELS[estado] ?? estado}
      </Badge>
    ),
  },
  {
    accessor: 'acciones',
    title:    '',
    width:    50,
    render: (nomina) => (
      <TableActions actions={[
        {
          label:   'Ver detalle',
          icon:    <IconEye size={14} />,
          color:   'blue',
          onClick: () => onVer(nomina),
        },
        {
          label:   'Cerrar nómina',
          icon:    <IconLock size={14} />,
          color:   'orange',
          onClick: () => onCerrar(nomina),
          hidden: nomina.estado !== 'borrador' && nomina.estado !== 'en_proceso',
        },
      ]} />
    ),
  },
]
