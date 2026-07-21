'use client'

import { Stack } from '@mantine/core'
import { IconShieldCheck } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { RiesgosLaboralesTab } from '@/features/sso/components/RiesgosLaboralesTab'

export default function FactoresRiesgoPage() {
  return (
    <Stack gap="md">
      <PageHeader
        title="Factores de Riesgo Laboral"
        subtitle="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
        icon={<IconShieldCheck size={24} />}
      />
      <RiesgosLaboralesTab />
    </Stack>
  )
}
