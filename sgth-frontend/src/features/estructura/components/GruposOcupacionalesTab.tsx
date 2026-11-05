'use client'

import { useState } from 'react'
import { Button, Select, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconLayersIntersect, IconTrendingUp } from '@tabler/icons-react'
import { DataState, SgthTable, Toolbar } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { useContainedInput } from '@/hooks/useContainedInput'
import { REGIMENES_PUESTO } from '../constants/clasificacionPuesto'
import { useGruposOcupacionales } from '../hooks/useGruposOcupacionales'
import { columnasGrupoOcupacional } from './grupoOcupacional.columns'
import { EscalasRemuneracionSection } from './EscalasRemuneracionSection'
import { NuevaEscalaModal } from './NuevaEscalaModal'

export function GruposOcupacionalesTab() {
  const filtro = useContainedInput('sm')
  // Sin régimen, los dos: el filtro se limpia con su «x», como los demás.
  const [regimen, setRegimen] = useState<string | null>(null)
  const { data: grupos = [], isLoading, error } = useGruposOcupacionales()

  // La nueva escala de la LOSEP (fase 7.3b): la prepara quien prepara
  // acciones; la registra el Director.
  const { hasPermiso } = useAuth()
  const veEscalas = hasPermiso('preparar-accion-personal') || hasPermiso('registrar-accion-personal')
  const [escalaAbierta, escala] = useDisclosure(false)

  const filtrados = regimen ? grupos.filter((g) => g.regimen === regimen) : grupos

  return (
    <Stack gap="md">
      <Toolbar
        actions={veEscalas && (
          // `light`, como la acción principal de las demás pestañas: el
          // relleno es para enviar un formulario, no para abrir uno (regla 06).
          <Button variant="light" leftSection={<IconTrendingUp size={16} />} onClick={escala.open}>
            Nueva escala
          </Button>
        )}
      >
        <Select
          label="Régimen"
          placeholder="Todos los regímenes"
          data={REGIMENES_PUESTO}
          clearable
          {...filtro}
          style={{ minWidth: 220 }}
          value={regimen}
          onChange={setRegimen}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!filtrados.length}
        emptyProps={{
          icon: IconLayersIntersect,
          title: 'Sin grupos ocupacionales',
          description: regimen
            ? 'Ningún grupo ocupacional pertenece a ese régimen. Quite el filtro para ver los demás.'
            : 'Aún no hay grupos ocupacionales en el catálogo.',
        }}
      >
        <SgthTable records={filtrados} columns={columnasGrupoOcupacional} minHeight={200} />
      </DataState>

      {veEscalas && <EscalasRemuneracionSection />}

      <NuevaEscalaModal opened={escalaAbierta} onClose={escala.close} />
    </Stack>
  )
}
