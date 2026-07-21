'use client'

import { Stack } from '@mantine/core'
import { IconCalendarOff } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { AusentismoTab } from '@/features/sso/components/AusentismoTab'

export default function AusentismoPage() {
  return (
    <Stack gap="md">
      <PageHeader
        title="Ausentismo por Enfermedad"
        subtitle="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
        icon={<IconCalendarOff size={24} />}
      />
      <AusentismoTab />
    </Stack>
  )
}
