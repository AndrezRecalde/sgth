import { Text } from '@mantine/core'
import { IconEye } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { TableActions } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { MovimientoPersonal } from '@/types/api'
import { EstadoAccionBadge } from './EstadoAccionBadge'

type Handlers = {
  onVerDetalle: (movimiento: MovimientoPersonal) => void
}

export function nombreServidor(s?: MovimientoPersonal['servidor']): string {
  if (!s) return '—'

  return [s.apellido, s.segundo_apellido, s.nombre, s.segundo_nombre]
    .filter(Boolean).join(' ') || '—'
}

export const getBandejaAccionesColumns = ({
  onVerDetalle,
}: Handlers): DataTableColumn<MovimientoPersonal>[] => [
  {
    accessor: 'servidor',
    title: 'Servidor',
    render: (m) => (
      <div>
        <Text size="sm" fw={500}>{nombreServidor(m.servidor)}</Text>
        <Text size="xs" c="dimmed">{m.servidor?.cedula ?? '—'}</Text>
      </div>
    ),
  },
  {
    accessor: 'etiqueta',
    title: 'Acción',
    // El nombre lo pone el backend —el de la clase, o el del tipo en la
    // bitácora—, y debajo la causal cuando la hay.
    render: (m) => (
      <div>
        <Text size="sm">{m.etiqueta}</Text>
        {m.causal_etiqueta && (
          <Text size="xs" c="dimmed">{m.causal_etiqueta}</Text>
        )}
      </div>
    ),
  },
  {
    accessor: 'fecha_efectiva',
    title: 'Rige desde',
    width: 110,
    render: (m) => <Text size="sm">{formatFecha(m.fecha_efectiva)}</Text>,
  },
  {
    accessor: 'codigo_registro',
    title: 'Código',
    width: 130,
    render: (m) => (
      <Text size="sm" ff="monospace">{m.codigo_registro ?? '—'}</Text>
    ),
  },
  {
    accessor: 'estado',
    title: 'Estado',
    width: 150,
    render: (m) => <EstadoAccionBadge m={m} />,
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    // Una sola entrada: revisar primero, decidir después. Editar, avanzar,
    // anular y descargar viven dentro del drawer, sobre la acción completa.
    render: (m) => (
      <TableActions
        actions={[
          {
            label: 'Ver detalle',
            icon: <IconEye size={14} />,
            onClick: () => onVerDetalle(m),
          },
        ]}
      />
    ),
  },
]
