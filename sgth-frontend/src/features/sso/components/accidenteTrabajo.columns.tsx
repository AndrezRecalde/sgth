'use client'

import { Text } from '@mantine/core'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import {
  TONO_GRAVEDAD, TONO_TIPO_EVENTO_ACCIDENTE, TIPO_EVENTO_ACCIDENTE_OPTIONS, GRAVEDAD_OPTIONS,
} from '../schemas/accidenteTrabajo.schema'
import { formatFecha } from '@/lib/fecha'
import type { DataTableColumn } from 'mantine-datatable'
import type { AccidenteTrabajo } from '../services/tipos'

/**
 * Columnas del registro de accidentes e incidentes de trabajo.
 *
 * Las acciones llegan desde la vista, que es quien tiene las mutaciones, los
 * modales y el permiso: aquí solo se dibuja el menú.
 */
export function columnasAccidenteTrabajo(
  accionesDe: (accidente: AccidenteTrabajo) => TableAction[],
): DataTableColumn<AccidenteTrabajo>[] {
  return [
    {
      accessor: 'servidor',
      title: 'Servidor',
      render: (a) => (
        <Text size="sm" fw={500}>
          {a.servidor ? `${a.servidor.nombre} ${a.servidor.apellido}` : `Servidor ${a.servidor_id}`}
        </Text>
      ),
    },
    {
      accessor: 'tipo_evento',
      title: 'Tipo',
      width: 110,
      render: (a) => (
        <StatusBadge tone={TONO_TIPO_EVENTO_ACCIDENTE[a.tipo_evento] ?? 'neutral'}>
          {TIPO_EVENTO_ACCIDENTE_OPTIONS.find(o => o.value === a.tipo_evento)?.label.split(' ')[0] ?? a.tipo_evento}
        </StatusBadge>
      ),
    },
    {
      accessor: 'fecha_accidente',
      title: 'Fecha',
      width: 110,
      render: (a) => formatFecha(a.fecha_accidente),
    },
    { accessor: 'lugar_accidente', title: 'Lugar' },
    {
      accessor: 'gravedad',
      title: 'Gravedad',
      width: 120,
      // La etiqueta, no el valor crudo: la columna pintaba «leve» y «mortal»
      // en minúscula, como vienen de la base, mientras las demás columnas del
      // módulo sí traducen. El texto sin traducir se conserva por si alguna
      // fila anterior a la validación trae algo fuera de la escala.
      render: (a) => (
        <StatusBadge tone={TONO_GRAVEDAD[a.gravedad] ?? 'neutral'}>
          {GRAVEDAD_OPTIONS.find(o => o.value === a.gravedad)?.label ?? a.gravedad}
        </StatusBadge>
      ),
    },
    {
      accessor: 'estado',
      title: 'Investigación',
      width: 130,
      render: (a) => (
        <StatusBadge tone={a.estado ? 'warning' : 'success'}>
          {a.estado ? 'Abierta' : 'Cerrada'}
        </StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (a) => <TableActions actions={accionesDe(a)} />,
    },
  ]
}
