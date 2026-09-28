import { Text } from '@mantine/core'
import { IconBan, IconEye, IconPlayerStop } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions, confirmar } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { Subrogacion } from '@/types/api'
import {
  ESTADO_LABELS, MOTIVO_LABELS, TIPO_LABELS, TONO_SUBROGACION,
} from '../utils/subrogaciones'

function nombreServidor(s?: { nombre?: string; apellido?: string } | null): string {
  if (!s) return '—'
  return [s.apellido, s.nombre].filter(Boolean).join(' ') || '—'
}

type Handlers = {
  onVerDetalle: (id: number) => void
  onFinalizar: (id: number) => void
  onCancelar: (id: number) => void
  /** Falso para quien solo consulta: auditoría y máxima autoridad. */
  puedeAdministrar: boolean
}

export const getSubrogacionColumns = ({
  onVerDetalle, onFinalizar, onCancelar, puedeAdministrar,
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
    // El estado se pintaba con un ternario de dos ramas: 'pendiente', o
    // «Activa» en verde para todo lo demás. El listado solo trae esos dos
    // estados, así que la segunda rama era una mentira latente — y se volvía
    // real el día que estas columnas se reutilizaran para el historial del
    // servidor, donde sí hay finalizadas y canceladas. Ahora el tono y la
    // etiqueta salen del mapa del módulo, que cubre los cuatro.
    accessor: 'estado',
    title: 'Estado',
    width: 190,
    render: ({ estado, movimiento_personal }) => (
      <div>
        <StatusBadge tone={TONO_SUBROGACION[estado]}>
          {ESTADO_LABELS[estado]}
        </StatusBadge>
        {estado === 'pendiente' && (
          <Text size="xs" c="dimmed" mt={2}>
            {movimiento_personal?.codigo_registro
              ? `Acción ${movimiento_personal.codigo_registro}`
              : 'Espera su Acción de Personal'}
          </Text>
        )}
      </div>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (s) => (
      <TableActions
        actions={[
          {
            // Lo primero del menú, y para todos: quien solo consulta no puede
            // hacer nada con la fila, pero sí necesita leerla entera.
            label: 'Ver detalle',
            icon: <IconEye size={14} />,
            onClick: () => onVerDetalle(Number(s.id)),
          },
          {
            label: 'Finalizar',
            icon: <IconPlayerStop size={14} />,
            // `disabled` y no `hidden`: lo que impide la acción es el estado
            // del registro, no un permiso (regla 06). Escondiéndola, quien
            // miraba una pendiente no tenía forma de saber que finalizar
            // existe; el servicio la rechaza igual.
            disabled: s.estado !== 'activa',
            hidden: !puedeAdministrar,
            onClick: () => confirmar({
              title:   'Finalizar subrogación',
              message: 'Se dará por terminada esta subrogación o encargo con fecha de hoy.',
              confirmLabel: 'Finalizar',
              onConfirm: () => onFinalizar(Number(s.id)),
            }),
          },
          {
            label: 'Cancelar',
            icon: <IconBan size={14} />,
            color: 'red',
            hidden: !puedeAdministrar,
            onClick: () => onCancelar(Number(s.id)),
          },
        ]}
      />
    ),
  },
]
