'use client'

import { Stack } from '@mantine/core'
import { IconMoodSmile } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { CampaniasPsicosocialTab } from '@/features/sso/components/CampaniasPsicosocialTab'

export default function EvaluacionPsicosocialPage() {
  return (
    <Stack gap="md">
      <PageHeader
        title="Evaluación Psicosocial"
        subtitle="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
        icon={<IconMoodSmile size={24} />}
      />
      <CampaniasPsicosocialTab />
    </Stack>
  )
}
