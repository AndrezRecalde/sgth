'use client'

import { Alert, Box, Tabs } from '@mantine/core'
import { IconGavel, IconInfoCircle, IconScale, IconTool } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { SumariosTab } from '@/features/disciplinario/components/SumariosTab'
import { VistosBuenosTab } from '@/features/disciplinario/components/VistosBuenosTab'

export function DisciplinarioView() {
  return (
    <Box>
      <PageHeader
        title="Régimen Disciplinario"
        subtitle="GAD Provincial de Esmeraldas"
        icon={<IconGavel size={28} />}
      />

      <Alert
        variant="light"
        color="blue"
        icon={<IconInfoCircle size={16} />}
        mb="md"
      >
        El procedimiento depende del régimen del servidor: el <strong>sumario
        administrativo</strong> aplica al personal LOSEP, y el <strong>visto
        bueno</strong> ante el Inspector del Trabajo a los obreros bajo Código del
        Trabajo. En ambos casos, la terminación del vínculo se materializa como
        una Cesación de Funciones que Talento Humano debe revisar y aprobar.
      </Alert>

      <Tabs defaultValue="sumarios" color="emerald">
        <Tabs.List mb="md">
          <Tabs.Tab value="sumarios" leftSection={<IconScale size={16} />}>
            Sumarios administrativos (LOSEP)
          </Tabs.Tab>
          <Tabs.Tab value="vistos-buenos" leftSection={<IconTool size={16} />}>
            Vistos buenos (Código del Trabajo)
          </Tabs.Tab>
        </Tabs.List>

        <Tabs.Panel value="sumarios">
          <SumariosTab />
        </Tabs.Panel>

        <Tabs.Panel value="vistos-buenos">
          <VistosBuenosTab />
        </Tabs.Panel>
      </Tabs>
    </Box>
  )
}
