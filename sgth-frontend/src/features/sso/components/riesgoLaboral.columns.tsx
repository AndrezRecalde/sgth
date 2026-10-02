'use client'

import { Text } from '@mantine/core'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { NIVEL_INTERVENCION_CORTO, TONO_NIVEL_INTERVENCION } from '../schemas/riesgoLaboral.schema'
import type { DataTableColumn } from 'mantine-datatable'
import type { RiesgoLaboral } from '../services/tipos'

/**
 * Columnas de la matriz de riesgos por puesto.
 *
 * Las acciones llegan desde la vista, que es quien tiene las mutaciones, los
 * modales y el permiso: aquí solo se dibuja el menú.
 */
export function columnasRiesgoLaboral(
  accionesDe: (riesgo: RiesgoLaboral) => TableAction[],
): DataTableColumn<RiesgoLaboral>[] {
  return [
    {
      accessor: 'puesto',
      title: 'Puesto',
      render: (r) => (
        <Text size="sm" fw={500}>{r.puesto?.cargo?.nombre ?? `Puesto ${r.puesto_id}`}</Text>
      ),
    },
    {
      accessor: 'factor_riesgo',
      title: 'Factor de riesgo',
      render: (r) => r.factor_riesgo?.nombre ?? `Factor ${r.factor_riesgo_id}`,
    },
    {
      accessor: 'nivel_riesgo_valor',
      title: 'NR',
      width: 70,
      render: (r) => r.nivel_riesgo_valor ?? '—',
    },
    {
      accessor: 'nivel_intervencion',
      title: 'Nivel de intervención',
      width: 200,
      // Los riesgos identificados antes de la matriz NTP 330 tienen los tres
      // niveles en NULL: la insignia salía vacía, como un dato que no llegó.
      render: (r) => (
        <StatusBadge tone={TONO_NIVEL_INTERVENCION[r.nivel_intervencion ?? ''] ?? 'neutral'}>
          {NIVEL_INTERVENCION_CORTO[r.nivel_intervencion ?? ''] ?? 'Sin valorar'}
        </StatusBadge>
      ),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 90,
      render: (r) => (
        <StatusBadge tone={r.estado ? 'success' : 'neutral'}>
          {r.estado ? 'Activo' : 'Inactivo'}
        </StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (r) => <TableActions actions={accionesDe(r)} />,
    },
  ]
}
