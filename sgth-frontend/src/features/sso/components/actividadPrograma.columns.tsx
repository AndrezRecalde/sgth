'use client'

import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { FASE_PROGRAMA_DROGAS_OPTIONS } from '../schemas/programaDrogas.schema'
import type { DataTableColumn } from 'mantine-datatable'
import type { ProgramaDrogaActividad } from '../services/programaDrogasService'

const etiquetaFase = (valor: string) =>
  FASE_PROGRAMA_DROGAS_OPTIONS.find((o) => o.value === valor)?.label ?? valor

/**
 * Columnas del catálogo de actividades del programa de prevención de drogas.
 *
 * Las acciones llegan desde el modal, que es quien tiene las mutaciones: aquí
 * solo se dibuja el menú.
 */
export function columnasActividadPrograma(
  accionesDe: (actividad: ProgramaDrogaActividad) => TableAction[],
): DataTableColumn<ProgramaDrogaActividad>[] {
  return [
    { accessor: 'nombre', title: 'Actividad' },
    {
      accessor: 'fase',
      title: 'Fase',
      width: 200,
      render: (a) => <StatusBadge>{etiquetaFase(a.fase)}</StatusBadge>,
    },
    {
      accessor: 'activo',
      title: 'Estado',
      width: 100,
      render: (a) => (
        <StatusBadge tone={a.activo ? 'success' : 'neutral'}>
          {a.activo ? 'Activa' : 'Inactiva'}
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
