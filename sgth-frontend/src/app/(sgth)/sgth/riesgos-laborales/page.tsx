'use client'

import { Stack } from '@mantine/core'
import { IconShieldCheck } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { DashboardSsoTab } from '@/features/sso/components/DashboardSsoTab'

export default function RiesgosLaboralesIndexPage() {
  return (
    <Stack gap="md">
      <PageHeader
        title="Riesgos Laborales (SSO)"
        subtitle="Dashboard consolidado — GAD Provincial de Esmeraldas"
        icon={<IconShieldCheck size={24} />}
      />
      <DashboardSsoTab />
    </Stack>
  )
}
