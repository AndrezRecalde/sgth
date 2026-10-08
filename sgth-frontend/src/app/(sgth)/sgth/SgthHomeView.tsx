'use client'

import { useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { Text } from '@mantine/core'
import { PageHeader, PageShell } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { soloPermisosEnSgth } from '@/config/nav'
import { ROUTES } from '@/config/routes'

/**
 * Panel principal del SGTH.
 *
 * Quien solo trabaja los permisos (Recepción, Trabajo Social) no tiene nada
 * que hacer aquí: el menú y el conmutador ya lo llevan a Asistencia ›
 * Permisos, y esto cubre a quien escribe la dirección o tiene un marcador.
 */
export function SgthHomeView() {
  const router = useRouter()
  const { usuario } = useAuth()
  const soloPermisos = soloPermisosEnSgth(usuario?.roles ?? [])

  useEffect(() => {
    if (soloPermisos) router.replace(ROUTES.SGTH.ASISTENCIA_PERMISOS)
  }, [soloPermisos, router])

  return (
    <PageShell>
      <PageHeader
        title="Gestión de Talento Humano"
        description="Panel principal del subsistema"
      />
      <Text c="dimmed">Módulo en construcción.</Text>
    </PageShell>
  )
}
