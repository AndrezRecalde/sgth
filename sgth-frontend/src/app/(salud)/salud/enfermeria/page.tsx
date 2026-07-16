'use client'

import { Stack } from '@mantine/core'
import { IconNurse } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { EnfermeriaAtenderPacienteView } from '@/features/dispensario/components/EnfermeriaAtenderPacienteView'

export default function EnfermeriaPage() {
  return (
    <Stack gap="md">
      <PageHeader
        title="Enfermería"
        subtitle="Atender paciente — admisión y triaje"
        icon={<IconNurse size={24} />}
      />
      <EnfermeriaAtenderPacienteView />
    </Stack>
  )
}
