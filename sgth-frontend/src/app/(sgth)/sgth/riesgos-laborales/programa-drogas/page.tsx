'use client'

import { Stack } from '@mantine/core'
import { IconChecklist } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { ProgramaDrogasTab } from '@/features/sso/components/ProgramaDrogasTab'

export default function ProgramaDrogasPage() {
  return (
    <Stack gap="md">
      <PageHeader
        title="Programa de Prevención de Drogas"
        subtitle="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
        icon={<IconChecklist size={24} />}
      />
      <ProgramaDrogasTab />
    </Stack>
  )
}
