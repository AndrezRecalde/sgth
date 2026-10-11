'use client'

import { Alert, Tabs } from '@mantine/core'
import { IconBook2, IconInfoCircle, IconScale, IconTool } from '@tabler/icons-react'
import { ReglamentoInternoTab } from '@/features/disciplinario/components/ReglamentoInternoTab'
import { SumariosTab } from '@/features/disciplinario/components/SumariosTab'
import { VistosBuenosTab } from '@/features/disciplinario/components/VistosBuenosTab'
import { PageHeader, PageShell } from '@/components/ui'

export function DisciplinarioView() {
  return (
    <PageShell>
      <PageHeader
        title="Régimen Disciplinario"
        description="GAD Provincial de Esmeraldas"
      />

      {/* Sin `mb`: el ritmo vertical entre los hijos de `PageShell` lo pone su
          propio `gap`, y sumarle un margen dejaba este bloque más suelto que
          los demás de la pantalla (regla 05). */}
      <Alert
        variant="light"
        color="ocean"
        icon={<IconInfoCircle size={16} />}
      >
        El procedimiento depende del régimen del servidor: el <strong>sumario
        administrativo</strong> aplica al personal LOSEP. A los obreros bajo
        Código del Trabajo se les sanciona con el <strong>procedimiento de su
        reglamento interno</strong>, con las faltas de su catálogo, y su
        terminación por falta grave es un <strong>visto bueno</strong> ante el
        Inspector del Trabajo. La terminación del vínculo se materializa como una
        cesación que Talento Humano debe revisar y aprobar.
      </Alert>

      <Tabs defaultValue="sumarios">
        <Tabs.List mb="md">
          <Tabs.Tab value="sumarios" leftSection={<IconScale size={16} />}>
            Sumarios y procedimientos
          </Tabs.Tab>
          <Tabs.Tab value="vistos-buenos" leftSection={<IconTool size={16} />}>
            Vistos buenos (Código del Trabajo)
          </Tabs.Tab>
          <Tabs.Tab value="reglamento-interno" leftSection={<IconBook2 size={16} />}>
            Reglamento interno
          </Tabs.Tab>
        </Tabs.List>

        <Tabs.Panel value="sumarios">
          <SumariosTab />
        </Tabs.Panel>

        <Tabs.Panel value="vistos-buenos">
          <VistosBuenosTab />
        </Tabs.Panel>

        <Tabs.Panel value="reglamento-interno">
          <ReglamentoInternoTab />
        </Tabs.Panel>
      </Tabs>
    </PageShell>
  )
}
