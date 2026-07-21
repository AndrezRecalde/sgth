'use client'

import { Stack } from '@mantine/core'
import { IconClipboardCheck } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { CumplimientoTab } from '@/features/sso/components/CumplimientoTab'

export default function CumplimientoNormativoPage() {
  return (
    <Stack gap="md">
      <PageHeader
        title="Cumplimiento Normativo"
        subtitle="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
        icon={<IconClipboardCheck size={24} />}
      />
      <CumplimientoTab />
    </Stack>
  )
}
