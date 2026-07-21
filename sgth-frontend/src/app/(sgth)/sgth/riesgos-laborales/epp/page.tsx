'use client'

import { Stack } from '@mantine/core'
import { IconHelmet } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { EquiposProteccionTab } from '@/features/sso/components/EquiposProteccionTab'

export default function EquiposProteccionPage() {
  return (
    <Stack gap="md">
      <PageHeader
        title="Equipos de Protección Personal"
        subtitle="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
        icon={<IconHelmet size={24} />}
      />
      <EquiposProteccionTab />
    </Stack>
  )
}
