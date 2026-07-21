'use client'

import { Stack } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { AccidentesTrabajoTab } from '@/features/sso/components/AccidentesTrabajoTab'

export default function AccidentesTrabajoPage() {
  return (
    <Stack gap="md">
      <PageHeader
        title="Accidentes de Trabajo"
        subtitle="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
        icon={<IconAlertTriangle size={24} />}
      />
      <AccidentesTrabajoTab />
    </Stack>
  )
}
