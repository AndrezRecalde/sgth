'use client'

import { Stack } from '@mantine/core'
import { IconVaccine } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { AssistCampaniasTab } from '@/features/sso/components/AssistCampaniasTab'

export default function TamizajeAssistPage() {
  return (
    <Stack gap="md">
      <PageHeader
        title="Tamizaje ASSIST"
        subtitle="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
        icon={<IconVaccine size={24} />}
      />
      <AssistCampaniasTab />
    </Stack>
  )
}
