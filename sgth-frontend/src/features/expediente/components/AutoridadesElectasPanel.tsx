'use client'

import React from 'react'
import { Alert, Stack } from '@mantine/core'
import { IconBuildingBank, IconInfoCircle } from '@tabler/icons-react'
import { DataState, SgthTable } from '@/components/ui'
import { useAutoridadesElectas } from '../hooks/useAutoridadesElectas'
import { usePuedePrepararAcciones } from '../hooks/usePuedePrepararAccion'
import type { AutoridadElecta } from '../services/autoridadElectaService'
import { getAutoridadElectaColumns } from './autoridadesElectas.columns'
import { FinDelPeriodoModal } from './FinDelPeriodoModal'

/**
 * El Prefecto y el Viceprefecto en funciones y su período (diseño, 9.14; TH
 * 1–7; fase 4.1). Su inicio y su cese se registran desde «Nueva acción de
 * personal», sin acción ni firmantes; aquí se ve cuándo termina cada período y
 * lo que sigue, y se fija el fin del de quien ya estaba sin él.
 */
export function AutoridadesElectasPanel() {
  const { data: autoridades = [], isLoading, error, refetch } = useAutoridadesElectas()
  const puedePreparar = usePuedePrepararAcciones()
  const [aFijar, setAFijar] = React.useState<AutoridadElecta | null>(null)

  const columns = getAutoridadElectaColumns({ puedePreparar, onFijarFin: setAFijar })

  return (
    <Stack gap="md">
      <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
        A las autoridades electas no se les emite acción de personal: su período
        empieza con la credencial del CNE y se cierra solo en su fecha. Si fue
        reelegida, registre el inicio del período nuevo desde «Nueva acción de
        personal»: empieza cuando termina el actual, sin cortar su antigüedad.
      </Alert>

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar las autoridades electas"
        onRetry={refetch}
        empty={!autoridades.length}
        emptyProps={{
          icon: IconBuildingBank,
          title: 'No hay autoridades electas en funciones',
          description: 'Aquí aparecen el Prefecto y el Viceprefecto, con su período y lo que sigue a él.',
        }}
      >
        <SgthTable records={autoridades} columns={columns} idAccessor="contrato_id" minHeight={200} />
      </DataState>

      <FinDelPeriodoModal opened={!!aFijar} onClose={() => setAFijar(null)} autoridad={aFijar} />
    </Stack>
  )
}
