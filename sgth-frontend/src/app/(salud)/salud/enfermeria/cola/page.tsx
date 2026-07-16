'use client'

import { Stack } from '@mantine/core'
import { IconNurse } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { EnfermeriaColaMonitoreoView } from '@/features/dispensario/components/EnfermeriaColaMonitoreoView'

export default function EnfermeriaColaPage() {
  return (
    <Stack gap="md">
      <PageHeader
        title="Enfermería"
        subtitle="Cola y monitoreo de turnos"
        icon={<IconNurse size={24} />}
      />
      <EnfermeriaColaMonitoreoView />
    </Stack>
  )
}
