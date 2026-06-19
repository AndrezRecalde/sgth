'use client'

import { useQuery }  from '@tanstack/react-query'
import { Stack, Center, Loader } from '@mantine/core'
import api               from '@/lib/axios'
import { useAuth }       from '@/hooks/useAuth'
import type { UsuarioAuth } from '@/store/auth.store'
import { PerfilServidorCard } from
  '@/features/portal/components/PerfilServidorCard'
import { NoticiasCard } from
  '@/features/portal/components/NoticiasCard'

export default function PortalHomePage() {
  const { usuario, token, setAuth } = useAuth()

  const { data: perfil, isLoading } = useQuery({
    queryKey: ['mi-perfil-portal'],
    queryFn: async () => {
      const res = await api.get<{
        datos: UsuarioAuth
      }>('/auth/perfil')
      const data = res.data.datos
      if (data && token) {
        setAuth(token, data)
      }
      return data
    },
    enabled:   !!token,
    staleTime: 1000 * 60 * 5,
  })

  const usuarioActual = perfil ?? usuario

  if (isLoading || !usuarioActual) {
    return (
      <Center h="60vh">
        <Loader color="emerald" size="lg" type="dots" />
      </Center>
    )
  }

  return (
    <Stack gap="md" maw={720}>
      <PerfilServidorCard usuario={usuarioActual} />
      <NoticiasCard />
    </Stack>
  )
}
