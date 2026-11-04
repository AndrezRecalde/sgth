'use client'

import { useState } from 'react'
import { Stack } from '@mantine/core'
import { IconScale } from '@tabler/icons-react'
import { DataState, MotivoModal, SectionHeading, SgthTable } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { useEscalasRemuneracion, useRevertirEscala } from '../hooks/useEscalaRemuneracion'
import type { EscalaRemuneracion } from '../services/escalaRemuneracionService'
import { getEscalaRemuneracionColumns } from './escalaRemuneracion.columns'

/**
 * Las escalas de remuneraciones registradas (fase 7.3b), con lo que subió cada
 * una. La que se registró por error se revierte entera, mientras nada haya
 * cambiado después.
 */
export function EscalasRemuneracionSection() {
  const { hasPermiso } = useAuth()
  const puedeRevertir = hasPermiso('anular-accion-personal')
  const { data: escalas = [], isLoading, error, refetch } = useEscalasRemuneracion()
  const revertir = useRevertirEscala()
  const [aRevertir, setARevertir] = useState<EscalaRemuneracion | null>(null)

  const columnas = getEscalaRemuneracionColumns({ alRevertir: setARevertir, puedeRevertir })

  return (
    <Stack gap="sm">
      <SectionHeading title="Escalas registradas" mb={0} />
      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar las escalas"
        onRetry={refetch}
        empty={!escalas.length}
        emptyProps={{
          icon: IconScale,
          title: 'Sin escalas registradas',
          description: 'Aquí aparecen las resoluciones que suben la RMU de los grados, con lo que subió cada una.',
        }}
      >
        <SgthTable records={escalas} columns={columnas} minHeight={120} />
      </DataState>

      <MotivoModal
        opened={!!aRevertir}
        onClose={() => setARevertir(null)}
        title="Revertir la escala"
        descripcion={aRevertir
          ? `La RMU de los grados y la remuneración de los ${aRevertir.vinculos} servidores vuelven a la de antes, con su constancia en la bitácora. Solo si nada cambió después.`
          : ''}
        confirmLabel="Revertir"
        destructiva
        cargando={revertir.isPending}
        onConfirm={(motivo) => aRevertir && revertir.mutate(
          { id: aRevertir.id, motivo },
          { onSuccess: () => setARevertir(null) },
        )}
      />
    </Stack>
  )
}
