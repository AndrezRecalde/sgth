'use client'

import { Text } from '@mantine/core'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import {
  ESTADO_SUMARIO_LABELS,
  TIPO_SANCION_LABELS,
  TONO_SUMARIO,
  nombreServidor,
} from '../utils/etiquetas'
import { formatFecha } from '@/lib/fecha'
import type { DataTableColumn } from 'mantine-datatable'
import type { Sumario } from '@/types/api'

/**
 * Columnas del listado de sumarios administrativos.
 *
 * Las acciones llegan desde la pestaña, que es quien tiene las mutaciones y
 * los modales: aquí solo se dibuja el menú.
 */
export function columnasSumario(
  accionesDe: (sumario: Sumario) => TableAction[],
): DataTableColumn<Sumario>[] {
  return [
    {
      accessor: 'servidor',
      title: 'Servidor',
      render: (s) => (
        <div>
          <Text size="sm" fw={500}>{nombreServidor(s.servidor)}</Text>
          <Text size="xs" c="dimmed">{s.servidor?.cedula ?? '—'}</Text>
        </div>
      ),
    },
    {
      accessor: 'motivo',
      title: 'Motivo',
      render: (s) => (
        <Text size="sm" lineClamp={2}>{s.motivo}</Text>
      ),
    },
    {
      accessor: 'fecha_apertura',
      title: 'Apertura',
      width: 110,
      render: (s) => <Text size="sm">{formatFecha(s.fecha_apertura)}</Text>,
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 140,
      render: (s) => (
        <StatusBadge tone={TONO_SUMARIO[s.estado]}>
          {ESTADO_SUMARIO_LABELS[s.estado]}
        </StatusBadge>
      ),
    },
    {
      // La destitución va en rojo porque es la salida del servicio público, no
      // un grado más de la escala; el resto de las sanciones son categorías y
      // van neutras (regla 06).
      accessor: 'sancion',
      title: 'Sanción',
      width: 150,
      render: (s) => s.sancion
        ? (
          <StatusBadge
            tone={s.sancion.tipo_sancion === 'destitucion' ? 'danger' : 'neutral'}
          >
            {TIPO_SANCION_LABELS[s.sancion.tipo_sancion]}
          </StatusBadge>
        )
        : <Text size="sm" c="dimmed">—</Text>,
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (s) => <TableActions actions={accionesDe(s)} />,
    },
  ]
}
