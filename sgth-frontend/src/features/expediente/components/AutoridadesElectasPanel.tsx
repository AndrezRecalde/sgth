'use client'

import React from 'react'
import { Alert, Button, Group, Stack } from '@mantine/core'
import { IconBuildingBank, IconInfoCircle, IconUserShare } from '@tabler/icons-react'
import { DataState, SgthTable } from '@/components/ui'
import { useAutoridadesElectas } from '../hooks/useAutoridadesElectas'
import { usePuedePrepararAcciones } from '../hooks/usePuedePrepararAccion'
import type { AutoridadElecta } from '../services/autoridadElectaService'
import { getAutoridadElectaColumns } from './autoridadesElectas.columns'
import { FinDelPeriodoModal } from './FinDelPeriodoModal'
import { SubrogacionPorLeyModal } from './SubrogacionPorLeyModal'

/**
 * El Prefecto y el Viceprefecto en funciones y su período (diseño, 9.14; TH
 * 1–7; fases 4.1 y 4.1b). Su inicio, su cese y la licencia del Consejo se
 * registran desde «Nueva acción de personal», sin acción ni firmantes; aquí se
 * ve cuándo termina cada período, la ausencia en curso, quién subroga y lo que
 * sigue. Se fija el fin del período de quien ya estaba sin él, y se registra la
 * subrogación por ley de una ausencia que no nació de una licencia.
 */
export function AutoridadesElectasPanel() {
  const { data: autoridades = [], isLoading, error, refetch } = useAutoridadesElectas()
  const puedePreparar = usePuedePrepararAcciones()
  const [aFijar, setAFijar] = React.useState<AutoridadElecta | null>(null)
  const [subrogar, setSubrogar] = React.useState(false)

  const columns = getAutoridadElectaColumns({ puedePreparar, onFijarFin: setAFijar })
  // Con Prefecto y sin nadie más en funciones, la Viceprefectura está vacante:
  // tras la asunción o la renuncia, el Consejo designa al nuevo (2026-10-20).
  const sinViceprefecto = autoridades.some((a) => a.es_prefecto) && autoridades.every((a) => a.es_prefecto)

  return (
    <Stack gap="md">
      <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
        A las autoridades electas no se les emite acción de personal: su período
        empieza con la credencial del CNE y se cierra solo en su fecha. Si fue
        reelegida, registre el inicio del período nuevo desde «Nueva acción de
        personal»: empieza cuando termina el actual, sin cortar su antigüedad. La
        licencia del Prefecto de más de tres días prepara sola la subrogación por
        ley del Viceprefecto (COOTAD Art. 51).
      </Alert>

      {sinViceprefecto && (
        <Alert variant="light" color="amber" icon={<IconInfoCircle size={16} />} title="No hay Viceprefecto en funciones">
          El Consejo Provincial lo designa por resolución, de una terna del Prefecto (COOTAD Art. 47 n). Regístrelo
          desde «Nueva acción de personal» con el «Inicio del período», causal «Designación del Consejo Provincial»:
          termina con el período del Prefecto y no lleva acta de posesión.
        </Alert>
      )}

      {puedePreparar(null) && (
        <Group justify="flex-end">
          <Button variant="light" leftSection={<IconUserShare size={16} />} onClick={() => setSubrogar(true)}>
            Registrar subrogación por ley
          </Button>
        </Group>
      )}

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
      <SubrogacionPorLeyModal opened={subrogar} onClose={() => setSubrogar(false)} />
    </Stack>
  )
}
