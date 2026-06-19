'use client'

import { Stack, Center, Loader } from '@mantine/core'
import { useAuth } from '@/hooks/useAuth'
import { PerfilServidorCard } from
  '@/features/portal/components/PerfilServidorCard'
import { NoticiasCard } from
  '@/features/portal/components/NoticiasCard'

export default function PortalHomePage() {
  const { usuario } = useAuth()

  if (!usuario) {
    return (
      <Center h="60vh">
        <Loader color="emerald" size="lg" type="dots" />
      </Center>
    )
  }

  return (
    <Stack gap="md" maw={720}>
      <PerfilServidorCard usuario={usuario} />
      <NoticiasCard />
    </Stack>
  )
}
