'use client'

import { useQuery } from '@tanstack/react-query'
import { Grid, Skeleton } from '@mantine/core'
import { PageHeader } from '@/components/ui'
import api from '@/lib/axios'
import { useAuth } from '@/hooks/useAuth'
import type { UsuarioAuth } from '@/store/auth.store'
import { PerfilServidorCard } from './PerfilServidorCard'
import { NoticiasCard } from './NoticiasCard'

export function PortalHomeView() {
  const { usuario, token, setAuth } = useAuth()

  // Al entrar al portal se refresca el perfil guardado en sesión: los datos
  // del servidor (cargo, unidad) cambian sin que el usuario vuelva a entrar.
  const { data: perfil, isLoading } = useQuery({
    queryKey: ['mi-perfil-portal'],
    queryFn: async () => {
      const res = await api.get<{ datos: UsuarioAuth }>('/auth/perfil')
      const data = res.data.datos
      if (data && token) {
        setAuth(token, data)
      }
      return data
    },
    enabled: !!token,
    staleTime: 1000 * 60 * 5,
  })

  const usuarioActual = perfil ?? usuario

  if (isLoading || !usuarioActual) {
    return (
      <>
        <PageHeader title="Mi portal" description="Tu ficha y las novedades de la institución" />
        <Grid>
          <Grid.Col span={{ base: 12, md: 6 }}><Skeleton height={320} radius="lg" /></Grid.Col>
          <Grid.Col span={{ base: 12, md: 6 }}><Skeleton height={320} radius="lg" /></Grid.Col>
        </Grid>
      </>
    )
  }

  return (
    <>
      <PageHeader title="Mi portal" description="Tu ficha y las novedades de la institución" />
      <Grid>
        <Grid.Col span={{ base: 12, md: 6 }}>
          <PerfilServidorCard usuario={usuarioActual} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 6 }}>
          <NoticiasCard />
        </Grid.Col>
      </Grid>
    </>
  )
}
