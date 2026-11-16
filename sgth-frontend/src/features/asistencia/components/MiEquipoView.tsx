'use client'

import { useState } from 'react'
import { Tabs } from '@mantine/core'
import { IconBeach, IconClipboardList, IconScale } from '@tabler/icons-react'
import { PageHeader, PageShell } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import type { Jefatura } from '@/store/auth.store'
import { EquipoPermisosTab } from './EquipoPermisosTab'
import { EquipoSaldosTab } from './EquipoSaldosTab'
import { EquipoVacacionesTab } from './EquipoVacacionesTab'

const COMO: Record<Jefatura['como'], string> = {
  titular:     '',
  subrogacion: ' (por subrogación)',
  encargo:     ' (por encargo)',
}

/**
 * «Mi equipo» del Portal: los permisos y vacaciones de la unidad que dirige,
 * en consulta, y el saldo de cada persona.
 *
 * La ve quien dirige una unidad por su puesto —como titular, por subrogación
 * o por encargo—, sin que TI le asigne un rol (decisión del 2026-11-13). Las
 * acciones siguen siendo de Talento Humano: el jefe firma en papel.
 */
export function MiEquipoView() {
  const { usuario } = useAuth()
  const [vineta, setVineta] = useState<string | null>('permisos')

  const unidades = (usuario?.servidor?.jefaturas ?? [])
    .map((j) => `${j.unidad ?? 'su unidad'}${COMO[j.como]}`)
    .join(', ')

  return (
    <PageShell>
      <PageHeader
        title="Mi equipo"
        description={`Permisos y vacaciones de ${unidades || 'la unidad que dirige'}, y los que lo nombran jefe inmediato`}
      />

      {/* Solo la pestaña abierta pide sus datos: los saldos cuestan una consulta por persona. */}
      <Tabs value={vineta} onChange={setVineta} keepMounted={false}>
        <Tabs.List mb="md">
          <Tabs.Tab value="permisos" leftSection={<IconClipboardList size={16} />}>
            Permisos
          </Tabs.Tab>
          <Tabs.Tab value="vacaciones" leftSection={<IconBeach size={16} />}>
            Vacaciones
          </Tabs.Tab>
          <Tabs.Tab value="saldos" leftSection={<IconScale size={16} />}>
            Saldos de vacaciones
          </Tabs.Tab>
        </Tabs.List>

        <Tabs.Panel value="permisos">
          <EquipoPermisosTab />
        </Tabs.Panel>
        <Tabs.Panel value="vacaciones">
          <EquipoVacacionesTab />
        </Tabs.Panel>
        <Tabs.Panel value="saldos">
          <EquipoSaldosTab />
        </Tabs.Panel>
      </Tabs>
    </PageShell>
  )
}
