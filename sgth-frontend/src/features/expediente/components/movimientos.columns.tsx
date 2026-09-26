import { Text } from '@mantine/core'
import { IconEye, IconFileDownload } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import {
  ESTADO_LABELS, TONO_ACCION, puedeDescargarPdf,
} from '../utils/estadoAccionPersonal'
import {
  SUBTIPO_LABELS, etiquetaTipoMovimiento,
} from '../utils/taxonomiaAccionPersonal'
import type { MovimientoPersonal } from '@/types/api'

type Handlers = {
  onVerDetalle: (movimiento: MovimientoPersonal) => void
  onDescargarPdf: (movimiento: MovimientoPersonal) => void
  /** Id de la acción cuyo PDF se está generando, para deshabilitar su opción. */
  descargandoId: number | null
}

export const getMovimientoColumns = ({
  onVerDetalle, onDescargarPdf, descargandoId,
}: Handlers): DataTableColumn<MovimientoPersonal>[] => [
  {
    accessor: 'tipo_movimiento',
    title: 'Tipo',
    render: ({ tipo_movimiento, subtipo_movimiento }) => (
      <div>
        <Text size="sm" fw={500}>
          {etiquetaTipoMovimiento(tipo_movimiento)}
        </Text>
        {subtipo_movimiento && (
          <Text size="xs" c="dimmed">
            {SUBTIPO_LABELS[subtipo_movimiento as keyof typeof SUBTIPO_LABELS]
              ?? subtipo_movimiento}
          </Text>
        )}
      </div>
    ),
  },
  {
    accessor: 'descripcion',
    title: 'Descripción',
    render: ({ descripcion }) => (
      <Text size="sm" c="dimmed" lineClamp={2}>{descripcion}</Text>
    ),
  },
  {
    accessor: 'periodo',
    title: 'Período',
    width: 190,
    render: (m) => (
      <Text size="sm">
        {m.fecha_inicio
          ? `${formatFecha(m.fecha_inicio)} → ${formatFecha(m.fecha_fin)}`
          : formatFecha(m.fecha_efectiva)}
      </Text>
    ),
  },
  {
    accessor: 'autorizado_por_usuario',
    title: 'Autorizado por',
    render: (m) => (
      <Text size="sm" c="dimmed">
        {m.autorizado_por_usuario?.nombre_completo ?? '—'}
      </Text>
    ),
  },
  {
    accessor: 'estado',
    title: 'Estado',
    width: 150,
    render: ({ estado }) =>
      estado ? (
        <StatusBadge tone={TONO_ACCION[estado]}>
          {ESTADO_LABELS[estado]}
        </StatusBadge>
      ) : (
        <Text size="sm" c="dimmed">—</Text>
      ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    // El detalle concentra revisar, editar, avanzar y descargar; se deja
    // aparte solo el atajo de PDF, que es la acción más frecuente sobre
    // acciones ya registradas.
    render: (m) => (
      <TableActions
        actions={[
          {
            label: 'Ver detalle',
            icon: <IconEye size={14} />,
            onClick: () => onVerDetalle(m),
          },
          {
            label: puedeDescargarPdf(m.estado, m.tipo_movimiento)
              ? 'Descargar PDF de Acción de Personal'
              : 'Sin documento imprimible',
            icon: <IconFileDownload size={14} />,
            disabled: descargandoId === Number(m.id)
              || !puedeDescargarPdf(m.estado, m.tipo_movimiento),
            onClick: () => onDescargarPdf(m),
          },
        ]}
      />
    ),
  },
]
