'use client'

import { useState } from 'react'
import { Tabs } from '@mantine/core'
import { IconClockHour4, IconFlask, IconListCheck, IconShieldCheck } from '@tabler/icons-react'
import { PageHeader, PageShell } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { BandejaExamenesPanel } from '@/features/dispensario/components/examenes/BandejaExamenesPanel'
import { CatalogoExamenesPanel } from '@/features/dispensario/components/examenes/CatalogoExamenesPanel'
import { ExamenesPorRiesgoPanel } from '@/features/dispensario/components/examenes/ExamenesPorRiesgoPanel'
import type { VistaBandeja } from '@/features/dispensario/services/bandejaExamenesService'
import { PerfilesExamenPanel } from '@/features/dispensario/components/examenes/PerfilesExamenPanel'

interface Props {
  /** La vista de la bandeja con la que abre, y el médico de «Por revisar». */
  vista?:       VistaBandeja
  solicitante?: number | null
}

/**
 * Los exámenes del Dispensario: la bandeja de lo que espera resultados y lo
 * que se ofrece al pedirlos. Es de médicos, odontólogos y la administración;
 * Enfermería no entra (2026-10-24).
 * Los perfiles los arma cada profesional para su especialidad; el catálogo,
 * solo la administración (P2); los exámenes por riesgo del puesto, el médico
 * ocupacional o la administración (D6).
 */
export function ExamenesView({ vista, solicitante = null }: Props) {
  const { hasRole } = useAuth()
  const catalogo = hasRole('admin-dispensario')
  // Como la ficha ocupacional, de la que es parte: médico y administración.
  const porRiesgo = hasRole('medico') || hasRole('admin-dispensario')
  const [pestana, setPestana] = useState<string | null>('pendientes')

  return (
    <PageShell>
      <PageHeader
        title="Exámenes"
        description="Órdenes que esperan resultados, perfiles y catálogo de exámenes"
      />
      <Tabs value={pestana} onChange={setPestana} keepMounted={false}>
        <Tabs.List>
          <Tabs.Tab value="pendientes" leftSection={<IconClockHour4 size={14} />}>Bandeja</Tabs.Tab>
          <Tabs.Tab value="perfiles" leftSection={<IconListCheck size={14} />}>Perfiles</Tabs.Tab>
          {porRiesgo && <Tabs.Tab value="por-riesgo" leftSection={<IconShieldCheck size={14} />}>Por riesgo</Tabs.Tab>}
          {catalogo && <Tabs.Tab value="catalogo" leftSection={<IconFlask size={14} />}>Catálogo</Tabs.Tab>}
        </Tabs.List>
        <Tabs.Panel value="pendientes" pt="lg">
          <BandejaExamenesPanel vistaInicial={vista} solicitanteInicial={solicitante} />
        </Tabs.Panel>
        <Tabs.Panel value="perfiles" pt="lg"><PerfilesExamenPanel /></Tabs.Panel>
        {porRiesgo && <Tabs.Panel value="por-riesgo" pt="lg"><ExamenesPorRiesgoPanel puedeEditar={porRiesgo} /></Tabs.Panel>}
        {catalogo && <Tabs.Panel value="catalogo" pt="lg"><CatalogoExamenesPanel /></Tabs.Panel>}
      </Tabs>
    </PageShell>
  )
}
