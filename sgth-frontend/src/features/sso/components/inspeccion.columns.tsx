'use client'

import { Text } from '@mantine/core'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { DataTableColumn } from 'mantine-datatable'
import type { InspeccionSso } from '../services/tipos'

/**
 * Columnas del registro de inspecciones de seguridad.
 *
 * Las acciones llegan desde la vista, que es quien tiene las mutaciones, los
 * modales y el permiso: aquí solo se dibuja el menú.
 */
export function columnasInspeccion(
  accionesDe: (inspeccion: InspeccionSso) => TableAction[],
): DataTableColumn<InspeccionSso>[] {
  return [
    {
      accessor: 'unidad_administrativa',
      title: 'Unidad',
      render: (i) => (
        <Text size="sm" fw={500}>
          {i.unidad_administrativa?.nombre ?? `Unidad ${i.unidad_administrativa_id}`}
        </Text>
      ),
    },
    {
      accessor: 'fecha_inspeccion',
      title: 'Fecha',
      width: 110,
      render: (i) => formatFecha(i.fecha_inspeccion),
    },
    { accessor: 'tipo_inspeccion', title: 'Tipo' },
    {
      accessor: 'hallazgos',
      title: 'Hallazgos',
      render: (i) => i.hallazgos
        ? <Text size="sm" lineClamp={2}>{i.hallazgos}</Text>
        : <Text size="sm" c="dimmed">—</Text>,
    },
    {
      accessor: 'estado',
      title: 'Seguimiento',
      width: 130,
      // Abierto es lo que queda por atender, no un error: ámbar y no rojo.
      render: (i) => (
        <StatusBadge tone={i.estado ? 'warning' : 'success'}>
          {i.estado ? 'Abierto' : 'Cerrado'}
        </StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (i) => <TableActions actions={accionesDe(i)} />,
    },
  ]
}
