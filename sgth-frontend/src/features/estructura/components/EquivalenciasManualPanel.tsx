'use client'

import { useState } from 'react'
import { Stack, Text } from '@mantine/core'
import { IconBuildingCommunity } from '@tabler/icons-react'
import { DataState, SgthTable } from '@/components/ui'
import { useEquivalenciasManual, useManualPuestosMutations } from '../hooks/useManualPuestos'
import { useTodasUnidades } from '../hooks/useUnidades'
import { getEquivalenciasManualColumns } from './equivalenciaManual.columns'

/**
 * «Unidades del manual» (P4, PR 12): a qué unidad de hoy corresponde cada
 * gestión del Manual de Puestos 2018-2019. La sugerida sale del nombre; la que
 * TH elige manda. Las de nivel 2, como en el formulario del puesto: los puestos
 * van en la gestión (D2).
 */
export function EquivalenciasManualPanel() {
  const { data: gestiones = [], isLoading, error } = useEquivalenciasManual()
  const unidades = useTodasUnidades({ nivel: 2 })
  const { guardarEquivalencia } = useManualPuestosMutations()
  const [guardando, setGuardando] = useState<string | null>(null)

  const columns = getEquivalenciasManualColumns({
    unidades: (unidades.data ?? []).map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` })),
    guardando,
    alCambiar: (g, unidadId) => {
      setGuardando(g.gestion)
      guardarEquivalencia.mutate({ gestion: g.gestion, unidadId }, { onSettled: () => setGuardando(null) })
    },
  })

  return (
    <Stack gap="sm">
      <Text size="sm" c="dimmed">
        El manual nombra las gestiones de 2018, a veces con erratas. Cada una va a la unidad de hoy
        donde están sus puestos: el sistema sugiere la de nombre parecido, y usted corrige la que no
        corresponde o elige la que falta. Al cambiarla se recalculan las sugerencias de sus fichas.
      </Text>
      <DataState
        loading={isLoading || unidades.isLoading}
        error={error ?? unidades.error}
        empty={!gestiones.length}
        emptyProps={{ icon: IconBuildingCommunity, title: 'Sin gestiones', description: 'Todavía no se importó el Manual de Puestos.' }}
      >
        <SgthTable records={gestiones} idAccessor="gestion" columns={columns} minHeight={200} />
      </DataState>
    </Stack>
  )
}
