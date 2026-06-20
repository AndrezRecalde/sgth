'use client'

import { Stack } from '@mantine/core'
import { IconHeartbeat } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { DisponibilidadToggle } from
  '@/features/dispensario/components/DisponibilidadToggle'
import { useAuth } from '@/hooks/useAuth'

export default function SaludHomePage() {
  const { usuario } = useAuth()
  const roles = (usuario?.roles as string[]) ?? []
  const esPersonalClinico = roles.some(r =>
    ['medico', 'odontologo'].includes(r)
  )

  return (
    <Stack gap="md">
      <PageHeader
        title="Dispensario Médico"
        subtitle="Sistema de Salud Ambulatoria — GADPE"
        icon={<IconHeartbeat size={24} />}
      />

      {esPersonalClinico && <DisponibilidadToggle />}
    </Stack>
  )
}
