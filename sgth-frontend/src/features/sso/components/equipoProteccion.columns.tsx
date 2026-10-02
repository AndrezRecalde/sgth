'use client'

import { Text } from '@mantine/core'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { TIPO_EPP_OPTIONS } from '../schemas/equipoProteccion.schema'
import type { DataTableColumn } from 'mantine-datatable'
import type { EquipoProteccion } from '../services/tipos'

const etiquetaTipo = (tipo: string) =>
  TIPO_EPP_OPTIONS.find((o) => o.value === tipo)?.label ?? tipo

/**
 * Columnas del catálogo de equipos de protección personal.
 *
 * Las acciones llegan desde la vista, que es quien tiene las mutaciones, los
 * modales y el permiso: aquí solo se dibuja el menú.
 */
export function columnasEquipoProteccion(
  accionesDe: (equipo: EquipoProteccion) => TableAction[],
): DataTableColumn<EquipoProteccion>[] {
  return [
    { accessor: 'codigo', title: 'Código', width: 110 },
    {
      accessor: 'nombre',
      title: 'Equipo',
      render: (e) => <Text size="sm" fw={500}>{e.nombre}</Text>,
    },
    {
      accessor: 'tipo',
      title: 'Tipo',
      render: (e) => etiquetaTipo(e.tipo),
    },
    { accessor: 'vida_util_meses', title: 'Vida útil (meses)', width: 150 },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 90,
      render: (e) => (
        <StatusBadge tone={e.estado ? 'success' : 'neutral'}>
          {e.estado ? 'Activo' : 'Inactivo'}
        </StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (e) => <TableActions actions={accionesDe(e)} />,
    },
  ]
}
