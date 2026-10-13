'use client'

import { IconDevices, IconFileText } from '@tabler/icons-react'
import { DataState, PageHeader, PageShell, SgthTable, type TableAction } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { columnasMisEquipos } from '@/features/inventario-ti/components/misEquipos.columns'
import { useActaMia, useMisEquipos } from '@/features/inventario-ti/hooks/useCustodia'
import type { Asignacion } from '@/features/inventario-ti/services/tipos'

/**
 * Los equipos de TIC que el servidor tiene a su cargo o usa. El acta solo de
 * los que tiene a su cargo: es la que él firmó.
 */
export function MisEquiposView() {
  const { usuario } = useAuth()
  const servidorId = usuario?.servidor_id ?? null
  const { data = [], isLoading, error } = useMisEquipos()
  const acta = useActaMia()

  const accionesDe = (a: Asignacion): TableAction[] => [
    { label: 'Ver acta', icon: <IconFileText size={14} />, hidden: a.servidor_id !== servidorId, onClick: () => acta.mutate(a.id) },
  ]

  return (
    <PageShell>
      <PageHeader
        title="Mis equipos"
        description="Los equipos informáticos que tiene a su cargo o usa. Si algo no coincide, comuníquelo a la Unidad de TIC."
      />
      <DataState
        loading={isLoading}
        error={error}
        empty={!data.length}
        emptyProps={{
          icon: IconDevices,
          title: 'No tiene equipos a su cargo',
          description: 'Cuando TIC le entregue un equipo en custodia, aparecerá aquí con su acta.',
        }}
      >
        <SgthTable records={data} columns={columnasMisEquipos(servidorId, accionesDe)} minHeight={160} />
      </DataState>
    </PageShell>
  )
}
