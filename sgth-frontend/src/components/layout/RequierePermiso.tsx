'use client'

import Link from 'next/link'
import { Button } from '@mantine/core'
import { IconLock } from '@tabler/icons-react'
import { EmptyState, PageShell } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'

interface PantallaSinAccesoProps {
  /** Adónde lleva el botón: un sitio donde el usuario sí entra. */
  volverA: string
  titulo?: string
  textoVolver?: string
}

/** Lo que ve quien abre una pantalla que su perfil no incluye. */
export function PantallaSinAcceso({
  volverA,
  titulo = 'No tiene acceso a esta pantalla',
  textoVolver = 'Ir al inicio',
}: PantallaSinAccesoProps) {
  return (
    <PageShell>
      <EmptyState
        icon={IconLock}
        title={titulo}
        description="Su perfil no la incluye. Si se la acaban de conceder, cierre la sesión y vuelva a entrar; si la necesita para su trabajo, solicítela a Talento Humano."
        action={
          <Button component={Link} href={volverA} variant="default">
            {textoVolver}
          </Button>
        }
      />
    </PageShell>
  )
}

interface RequierePermisoProps {
  permiso: string
  /** El listado del que cuelga la pantalla: allí sí entra quien llegó aquí. */
  volverA: string
  textoVolver?: string
  children: React.ReactNode
}

/**
 * Una pantalla de acción que pide un permiso más estrecho que su listado.
 *
 * `GuardaDePantalla` decide por la entrada del menú que contiene la ruta, y
 * las pantallas de alta o edición que no están en el menú heredan la de su
 * listado: el auditor, que consulta el inventario, abría «Registrar equipo»
 * por la dirección y recibía el 403 al guardar (docs/plan-roles-y-accesos.md,
 * PR 14). Va en el `page.tsx`, para que la vista no llegue a pedir nada.
 */
export function RequierePermiso({ permiso, volverA, textoVolver = 'Volver al listado', children }: RequierePermisoProps) {
  const { hasPermiso } = useAuth()

  if (!hasPermiso(permiso)) {
    return <PantallaSinAcceso volverA={volverA} textoVolver={textoVolver} />
  }

  return children
}
