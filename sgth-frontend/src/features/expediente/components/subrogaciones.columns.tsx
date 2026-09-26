import { Text } from '@mantine/core'
import { IconBan, IconPlayerStop } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions, confirmar } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { Subrogacion, TipoSubrogacion } from '@/types/api'

const TIPO_LABELS: Record<TipoSubrogacion, string> = {
  subrogacion: 'Subrogación',
  encargo: 'Encargo',
}

const MOTIVO_LABELS: Record<string, string> = {
  vacaciones: 'Vacaciones',
  comision_servicios: 'Comisión de Servicios',
  enfermedad: 'Enfermedad',
  licencia: 'Licencia',
  encargo_vacante: 'Encargo por Vacante',
  otro: 'Otro',
}

function nombreServidor(s?: { nombre?: string; apellido?: string } | null): string {
  if (!s) return '—'
  return [s.apellido, s.nombre].filter(Boolean).join(' ') || '—'
}

type Handlers = {
  onFinalizar: (id: number) => void
  onCancelar: (id: number) => void
}

export const getSubrogacionColumns = ({
  onFinalizar, onCancelar,
}: Handlers): DataTableColumn<Subrogacion>[] => [
  {
    accessor: 'tipo',
    title: 'Tipo',
    width: 140,
    render: ({ tipo }) => (
      <StatusBadge>
        {TIPO_LABELS[tipo]}
      </StatusBadge>
    ),
  },
  {
    accessor: 'subrogante',
    title: 'Subrogante / Encargado',
    render: ({ subrogante }) => (
      <Text size="sm" fw={500}>{nombreServidor(subrogante)}</Text>
    ),
  },
  {
    accessor: 'subrogado',
    title: 'Titular subrogado',
    render: ({ subrogado }) => (
      <Text size="sm" c="dimmed">{subrogado ? nombreServidor(subrogado) : '— (encargo)'}</Text>
    ),
  },
  {
    accessor: 'puesto_subrogado',
    title: 'Puesto',
    render: ({ puesto_subrogado, unidad_administrativa }) => (
      <div>
        <Text size="sm">{puesto_subrogado?.cargo?.nombre ?? '—'}</Text>
        <Text size="xs" c="dimmed">{unidad_administrativa?.nombre ?? '—'}</Text>
      </div>
    ),
  },
  {
    accessor: 'periodo',
    title: 'Período',
    width: 180,
    render: (s) => (
      <Text size="sm">{formatFecha(s.fecha_inicio)} → {formatFecha(s.fecha_fin)}</Text>
    ),
  },
  {
    accessor: 'motivo',
    title: 'Motivo',
    render: ({ motivo }) => (
      <Text size="sm" c="dimmed">{MOTIVO_LABELS[motivo] ?? motivo}</Text>
    ),
  },
  {
    accessor: 'estado',
    title: 'Estado',
    width: 190,
    render: ({ estado, movimiento_personal }) => (
      estado === 'pendiente' ? (
        <div>
          <StatusBadge tone="warning">Pendiente</StatusBadge>
          <Text size="xs" c="dimmed" mt={2}>
            {movimiento_personal?.codigo_registro
              ? `Acción ${movimiento_personal.codigo_registro}`
              : 'Espera su Acción de Personal'}
          </Text>
        </div>
      ) : (
        <StatusBadge tone="success">Activa</StatusBadge>
      )
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (s) => (
      <TableActions
        actions={[
          // Finalizar solo tiene sentido en las que ya surten efecto: una
          // pendiente todavía no empezó, y el servicio la rechaza. Ofrecerla
          // igual era prometer una acción que siempre falla.
          ...(s.estado === 'activa' ? [{
            label: 'Finalizar',
            icon: <IconPlayerStop size={14} />,
            onClick: () => confirmar({
              title:   'Finalizar subrogación',
              message: 'Se dará por terminada esta subrogación o encargo con fecha de hoy.',
              confirmLabel: 'Finalizar',
              onConfirm: () => onFinalizar(Number(s.id)),
            }),
          }] : []),
          {
            label: 'Cancelar',
            icon: <IconBan size={14} />,
            color: 'red',
            onClick: () => onCancelar(Number(s.id)),
          },
        ]}
      />
    ),
  },
]
