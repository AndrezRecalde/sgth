'use client'

import { useEffect } from 'react'
import Link from 'next/link'
import { usePathname, useRouter } from 'next/navigation'
import { Button, Skeleton } from '@mantine/core'
import { IconLock } from '@tabler/icons-react'
import { EmptyState, PageShell } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import {
  getSubsistemasDisponibles, inicioDelSubsistema, puedeAbrirPantalla,
} from '@/config/nav'
import { SUBSISTEMAS } from '@/config/subsistemas'
import { ROUTES, type Subsistema } from '@/config/routes'

interface Props {
  subsistema: Subsistema
  children: React.ReactNode
}

/**
 * Abre solo las pantallas que el menú le ofrece al usuario.
 *
 * Quien escribía la dirección de una pantalla que no estaba en su menú, o
 * llegaba por un marcador, entraba a una página que le respondía 403 a cada
 * consulta (docs/plan-roles-y-accesos.md, PR 9). Ahora ve que no tiene acceso
 * y un enlace a donde sí lo tiene. La pantalla se decide con el menú
 * (`puedeAbrirPantalla`), así que no hay una segunda lista que mantener.
 *
 * Y lleva al inicio: el `home` de un subsistema que el usuario no tiene en el
 * menú —el panel del SGTH, el tablero del Dispensario para el médico— lo manda
 * a su primera pantalla, para que la marca, la miga y el conmutador no dejen
 * a nadie en una página en blanco.
 *
 * Es una guarda de pantalla, no de datos: el backend sigue autorizando cada
 * petición.
 */
export function GuardaDePantalla({ subsistema, children }: Props) {
  const pathname = usePathname()
  const router = useRouter()
  const { usuario } = useAuth()
  const permisos = usuario?.permisos ?? []
  const roles = usuario?.roles ?? []

  const disponibles = getSubsistemasDisponibles(permisos, roles)
  const entra = disponibles.includes(subsistema)
  const inicio = inicioDelSubsistema(subsistema, permisos, roles)
  const alInicio = entra && pathname === SUBSISTEMAS[subsistema].home && inicio !== pathname

  useEffect(() => {
    if (alInicio) router.replace(inicio)
  }, [alInicio, inicio, router])

  if (alInicio) {
    return (
      <PageShell>
        <Skeleton height={80} radius="lg" />
      </PageShell>
    )
  }

  if (entra && puedeAbrirPantalla(subsistema, pathname, permisos, roles)) {
    return children
  }

  // Sin ninguna pantalla en este subsistema, el inicio que sirve es el de
  // otro: el primero que tenga, que casi siempre es el Portal.
  const salida = entra
    ? inicio
    : disponibles[0]
      ? inicioDelSubsistema(disponibles[0], permisos, roles)
      : ROUTES.PORTAL.HOME

  return (
    <PageShell>
      <EmptyState
        icon={IconLock}
        title={entra
          ? 'No tiene acceso a esta pantalla'
          : `No tiene acceso a ${SUBSISTEMAS[subsistema].nombre}`}
        description="Su perfil no la incluye. Si se la acaban de conceder, cierre la sesión y vuelva a entrar; si la necesita para su trabajo, solicítela a Talento Humano."
        action={
          <Button component={Link} href={salida} variant="default">
            Ir al inicio
          </Button>
        }
      />
    </PageShell>
  )
}
