'use client'

import { Stack } from '@mantine/core'
import { IconChartBar } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { IndicadoresSsoTab } from '@/features/sso/components/IndicadoresSsoTab'

export default function IndicadoresSsoPage() {
  return (
    <Stack gap="md">
      <PageHeader
        title="Indicadores SSO"
        subtitle="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
        icon={<IconChartBar size={24} />}
      />
      <IndicadoresSsoTab />
    </Stack>
  )
}
