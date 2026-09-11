'use client'

import { Badge, Text } from '@mantine/core'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import type { DataTableColumn } from 'mantine-datatable'
import type { ConsolidadoPermiso, ConsolidadoPermisoResponse } from '@/types/api'

type Totales = ConsolidadoPermisoResponse['totales']

/**
 * Las columnas del consolidado de permisos.
 *
 * Los totales van en el pie de cada columna numérica: antes eran una fila
 * `<tfoot>` escrita a mano en una tabla HTML, con un fondo de color fijo que no
 * se veía en modo oscuro.
 */
export function getConsolidadoColumns(totales?: Totales): DataTableColumn<ConsolidadoPermiso>[] {
  const pie = (valor: string | number) => <Text size="sm" fw={700} ff="monospace">{valor}</Text>

  return [
    {
      accessor: 'cedula',
      title: 'Cédula',
      width: 120,
      render: ({ cedula }) => <Text size="sm" ff="monospace">{cedula}</Text>,
      footer: totales && <Text size="sm" fw={700}>TOTALES</Text>,
    },
    {
      accessor: 'servidor_nombre',
      title: 'Servidor',
      render: ({ servidor_nombre }) => <Text size="sm" fw={500}>{servidor_nombre}</Text>,
    },
    {
      accessor: 'unidad',
      title: 'Unidad',
      render: ({ unidad }) => <Text size="sm" c="dimmed">{unidad}</Text>,
    },
    {
      accessor: 'total_permisos',
      title: 'Permisos',
      textAlign: 'center',
      width: 100,
      render: ({ total_permisos }) => (
        <Badge variant="light" color={SEMANTIC_COLOR.info} size="sm">{total_permisos}</Badge>
      ),
      footer: totales && pie(totales.total_permisos),
    },
    {
      accessor: 'total_minutos',
      title: 'Minutos',
      textAlign: 'right',
      width: 100,
      render: ({ total_minutos }) => <Text size="sm" ff="monospace">{total_minutos}</Text>,
      footer: totales && pie(totales.total_minutos),
    },
    {
      accessor: 'tiempo_total',
      title: 'Tiempo',
      textAlign: 'right',
      width: 110,
      render: ({ tiempo_total }) => <Text size="sm" ff="monospace" fw={500}>{tiempo_total}</Text>,
      footer: totales && pie('—'),
    },
    {
      accessor: 'total_dias',
      title: 'Días',
      textAlign: 'right',
      width: 90,
      // Un día o más de permisos en el rango se resalta: es lo que Talento
      // Humano mira primero al cerrar el mes.
      render: ({ total_dias }) => (
        <Text
          size="sm"
          ff="monospace"
          fw={600}
          c={total_dias >= 1 ? SEMANTIC_COLOR.warning : undefined}
        >
          {total_dias.toFixed(2)}
        </Text>
      ),
      footer: totales && pie(totales.total_dias.toFixed(2)),
    },
  ]
}
