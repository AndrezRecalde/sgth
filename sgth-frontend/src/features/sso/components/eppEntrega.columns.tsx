'use client'

import { Text } from '@mantine/core'
import { StatusBadge } from '@/components/ui'
import { MOTIVO_ENTREGA_OPTIONS } from '../schemas/eppEntrega.schema'
import { formatFecha } from '@/lib/fecha'
import type { DataTableColumn } from 'mantine-datatable'
import type { EppEntrega } from '../services/tipos'

const etiquetaMotivo = (valor: string) =>
  MOTIVO_ENTREGA_OPTIONS.find((o) => o.value === valor)?.label ?? valor

/**
 * Columnas de la bitácora de entregas de EPP.
 *
 * Sin columna de acciones y por eso una constante y no una función: la
 * bitácora no se edita ni se borra, cada movimiento queda como se registró.
 */
export const columnasEppEntrega: DataTableColumn<EppEntrega>[] = [
  {
    accessor: 'servidor',
    title: 'Servidor',
    render: (e) => (
      <Text size="sm" fw={500}>
        {e.servidor ? `${e.servidor.nombre} ${e.servidor.apellido}` : `Servidor ${e.servidor_id}`}
      </Text>
    ),
  },
  {
    accessor: 'equipo_proteccion',
    title: 'Equipo',
    render: (e) => e.equipo_proteccion?.nombre ?? `Equipo ${e.equipo_proteccion_id}`,
  },
  {
    accessor: 'fecha_entrega',
    title: 'Fecha',
    width: 110,
    render: (e) => formatFecha(e.fecha_entrega),
  },
  { accessor: 'cantidad', title: 'Cantidad', width: 90 },
  {
    accessor: 'motivo',
    title: 'Motivo',
    width: 130,
    render: (e) => (
      <StatusBadge>
        {etiquetaMotivo(e.motivo)}
      </StatusBadge>
    ),
  },
]
