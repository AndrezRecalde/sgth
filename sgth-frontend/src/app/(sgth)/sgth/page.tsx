'use client'

import { useEffect }  from 'react'
import { useRouter }  from 'next/navigation'
import { useAuth }    from '@/hooks/useAuth'
import { Center, Loader } from '@mantine/core'
import {
  getSubsistemasDisponibles,
} from '@/config/nav'
import { ROUTES }     from '@/config/routes'

export default function SgthHomePage() {
  const router  = useRouter()
  const { usuario } = useAuth()

  useEffect(() => {
    if (!usuario) return
    const roles      = (usuario.roles as string[]) ?? []
    const disponibles = getSubsistemasDisponibles(roles)
    if (disponibles.length === 0) return
    const primero = disponibles[0]
    const destino = {
      sgth:   ROUTES.SGTH.SERVIDORES,
      salud:  ROUTES.SALUD.HOME,
      portal: ROUTES.PORTAL.MI_PERFIL,
    }[primero]
    router.replace(destino)
  }, [usuario, router])

  return (
    <Center h="60vh">
      <Loader color="emerald" size="xl" type="dots" />
    </Center>
  )
}
