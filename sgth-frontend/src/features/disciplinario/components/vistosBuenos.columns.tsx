'use client'

import { Text, Tooltip } from '@mantine/core'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import {
  CAUSAL_LABELS,
  ESTADO_VISTO_BUENO_LABELS,
  TONO_VISTO_BUENO,
  nombreServidor,
  referenciaLegal,
} from '../utils/etiquetas'
import { formatFecha } from '@/lib/fecha'
import type { DataTableColumn } from 'mantine-datatable'
import type { VistoBueno } from '@/types/api'

/**
 * Columnas del listado de trámites de visto bueno.
 *
 * Las acciones llegan desde la pestaña, que es quien tiene las mutaciones y
 * los modales: aquí solo se dibuja el menú.
 */
export function columnasVistoBueno(
  accionesDe: (tramite: VistoBueno) => TableAction[],
): DataTableColumn<VistoBueno>[] {
  return [
    {
      accessor: 'servidor',
      title: 'Trabajador',
      render: (t) => (
        <div>
          <Text size="sm" fw={500}>{nombreServidor(t.servidor)}</Text>
          <Text size="xs" c="dimmed">{t.servidor?.cedula ?? '—'}</Text>
        </div>
      ),
    },
    {
      accessor: 'causal',
      title: 'Causal',
      render: (t) => (
        <Tooltip label={referenciaLegal(t.causal)} withArrow>
          <Text size="sm" lineClamp={2}>{CAUSAL_LABELS[t.causal]}</Text>
        </Tooltip>
      ),
    },
    {
      accessor: 'numero_tramite_mdt',
      title: 'Trámite MDT',
      width: 150,
      render: (t) => (
        <Text size="sm" ff="monospace">{t.numero_tramite_mdt ?? '—'}</Text>
      ),
    },
    {
      accessor: 'fecha_solicitud',
      title: 'Solicitud',
      width: 110,
      render: (t) => <Text size="sm">{formatFecha(t.fecha_solicitud)}</Text>,
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 160,
      render: (t) => (
        <StatusBadge tone={TONO_VISTO_BUENO[t.estado]}>
          {ESTADO_VISTO_BUENO_LABELS[t.estado]}
        </StatusBadge>
      ),
    },
    {
      // El correlativo de la acción de personal es un código, y la regla 06
      // reserva `outline` para los códigos. Mientras no lo tiene, lo que se
      // enseña es su estado —borrador—, que va neutro.
      accessor: 'movimiento_personal',
      title: 'Cesación',
      width: 130,
      render: (t) => {
        if (!t.movimiento_personal) return <Text size="sm" c="dimmed">—</Text>

        return t.movimiento_personal.codigo_registro
          ? (
            <StatusBadge variant="outline">
              {t.movimiento_personal.codigo_registro}
            </StatusBadge>
          )
          : <StatusBadge>En borrador</StatusBadge>
      },
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (t) => <TableActions actions={accionesDe(t)} />,
    },
  ]
}
