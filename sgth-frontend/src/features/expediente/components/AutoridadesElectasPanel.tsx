'use client'

import React from 'react'
import { Alert, Button, Group, Stack, Text } from '@mantine/core'
import { IconAlertTriangle, IconBuildingBank, IconInfoCircle, IconUserPlus, IconUserShare } from '@tabler/icons-react'
import { DataState, SectionHeading, SgthTable } from '@/components/ui'
import { useAutoridadesElectas, useAutoridadesEntrantes } from '../hooks/useAutoridadesElectas'
import { usePuedePrepararAcciones } from '../hooks/usePuedePrepararAccion'
import type { AutoridadElecta } from '../services/autoridadElectaService'
import { getAutoridadElectaColumns } from './autoridadesElectas.columns'
import { getAutoridadEntranteColumns } from './autoridadesEntrantes.columns'
import { FinDelPeriodoModal } from './FinDelPeriodoModal'
import { SubrogacionPorLeyModal } from './SubrogacionPorLeyModal'

/**
 * El Prefecto y el Viceprefecto en funciones y su período (diseño, 9.14; TH
 * 1–7; fases 4.1 y 4.1b). Su inicio, su cese y la licencia del Consejo se
 * registran desde «Nueva acción de personal», sin acción ni firmantes; aquí se
 * ve cuándo termina cada período, la ausencia en curso, quién subroga y lo que
 * sigue. Se corrige el fin del período que el sistema le fijó a quien ya
 * estaba, y se registra la subrogación por ley de una ausencia que no nació de
 * una licencia.
 *
 * Debajo, las que entran (2026-10-21): su período todavía no rige, y si el de
 * la autoridad que sale no termina a tiempo, el aviso va arriba de todo.
 */
export function AutoridadesElectasPanel() {
  const { data: autoridades = [], isLoading, error, refetch } = useAutoridadesElectas()
  const { data: entrantes = [], error: errorEntrantes } = useAutoridadesEntrantes()
  const puedePreparar = usePuedePrepararAcciones()
  const [aFijar, setAFijar] = React.useState<AutoridadElecta | null>(null)
  const [subrogar, setSubrogar] = React.useState(false)

  const columns = getAutoridadElectaColumns({ puedePreparar, onFijarFin: setAFijar })
  const columnasEntrantes = getAutoridadEntranteColumns()
  const relevosSinCuadrar = entrantes.filter((e) => e.aviso)
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

      {relevosSinCuadrar.map((e) => (
        <Alert
          key={e.movimiento_id}
          variant="light"
          color="red"
          icon={<IconAlertTriangle size={16} />}
          title={`Antes de que entre ${e.servidor.nombre}`}
        >
          {e.aviso}
        </Alert>
      ))}

      {sinViceprefecto && (
        <Alert variant="light" color="amber" icon={<IconInfoCircle size={16} />} title="No hay Viceprefecto en funciones">
          Regístrelo desde «Nueva acción de personal» con el «Inicio del período». Si se eligió junto con el
          Prefecto, con la causal «Elección» y su credencial del CNE. Si la Viceprefectura quedó vacante, lo designa
          el Consejo Provincial por resolución, de una terna del Prefecto (COOTAD Art. 47 n): causal «Designación del
          Consejo Provincial», que termina con el período del Prefecto y no lleva acta de posesión.
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

      {errorEntrantes && (
        <Alert variant="light" color="red" icon={<IconAlertTriangle size={16} />}>
          No se pudieron cargar las autoridades que entran.
        </Alert>
      )}

      {entrantes.length > 0 && (
        <Stack gap="xs">
          <SectionHeading title="Autoridades que entran" icon={<IconUserPlus size={16} />} />
          <Text size="sm" c="dimmed">
            Su período todavía no empieza. El día de la posesión pasan a la tabla de arriba; la autoridad a la que
            suceden deja de estar en funciones al terminar el suyo.
          </Text>
          <SgthTable records={entrantes} columns={columnasEntrantes} idAccessor="movimiento_id" minHeight={120} />
        </Stack>
      )}

      <FinDelPeriodoModal opened={!!aFijar} onClose={() => setAFijar(null)} autoridad={aFijar} />
      <SubrogacionPorLeyModal opened={subrogar} onClose={() => setSubrogar(false)} />
    </Stack>
  )
}
