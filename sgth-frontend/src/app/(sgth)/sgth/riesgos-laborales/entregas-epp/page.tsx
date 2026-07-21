'use client'

import { Stack } from '@mantine/core'
import { IconTruckDelivery } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { EntregasEppTab } from '@/features/sso/components/EntregasEppTab'

export default function EntregasEppPage() {
  return (
    <Stack gap="md">
      <PageHeader
        title="Entregas de EPP"
        subtitle="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
        icon={<IconTruckDelivery size={24} />}
      />
      <EntregasEppTab />
    </Stack>
  )
}
