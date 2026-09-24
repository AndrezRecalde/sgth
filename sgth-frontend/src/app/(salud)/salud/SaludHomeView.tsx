'use client'

import { useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { Skeleton } from '@mantine/core'
import { IconStethoscope } from '@tabler/icons-react'
import { EmptyState, PageHeader, PageShell } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useHydrated } from '@/hooks/useHydrated'
import { useAuthStore } from '@/store/auth.store'
import { TableroDispensario } from
  '@/features/dispensario/components/TableroDispensario'

/**
 * Referencia estable para quien todavía no tiene roles.
 *
 * Devolver un `[]` nuevo en cada lectura del store cambia la instantánea en
 * cada render, y `useSyncExternalStore` lo toma por un cambio de verdad: se
 * queja por consola y vuelve a renderizar sin parar.
 */
const SIN_ROLES: readonly string[] = []

/**
 * A qué pantalla entra cada quien al abrir el Dispensario.
 *
 * El tablero de `/salud` es de gestión y el backend solo se lo sirve a la
 * administración del dispensario, así que al resto del personal esta pantalla
 * le quedaba en blanco: el título y nada debajo. Y no se llega por
 * equivocación —es el `home` del subsistema—, sino desde la marca del
 * sidebar, desde la miga «Dispensario Médico» y desde el conmutador de
 * subsistemas.
 *
 * Cada rol clínico tiene ya su propia pantalla de trabajo, con sus pacientes
 * del día. Se le lleva a ella.
 */
const INICIO_POR_ROL: ReadonlyArray<readonly [string, string]> = [
  ['medico',     ROUTES.SALUD.CONSULTAS],
  ['odontologo', ROUTES.SALUD.ODONTOLOGIA],
  ['enfermera',  ROUTES.SALUD.ENFERMERIA],
]

export function SaludHomeView() {
  const router = useRouter()
  const hidratado = useHydrated()

  // Se suscribe a los roles y no a `hasRole`: esa función es siempre la misma
  // instancia, así que seleccionarla no provoca un render cuando la sesión
  // termina de rehidratarse, y quien tenía que ser redirigido se quedaría
  // mirando el cargador para siempre.
  const roles = useAuthStore((s) => s.usuario?.roles ?? SIN_ROLES)

  const verTablero =
    roles.includes('admin-dispensario') || roles.includes('maxima-autoridad')

  // Quien administra el dispensario y además atiende se queda en el tablero:
  // es la pantalla que solo él puede ver, y a la suya llega por el menú.
  const destino = verTablero
    ? null
    : INICIO_POR_ROL.find(([rol]) => roles.includes(rol))?.[1] ?? null

  useEffect(() => {
    // La rehidratación del store es asíncrona: en el primer render no hay
    // roles todavía. Decidir antes de que llegue manda a Consultas hasta al
    // administrador.
    if (hidratado && destino) router.replace(destino)
  }, [hidratado, destino, router])

  if (!hidratado || destino) {
    return (
      <PageShell>
        <Skeleton height={80} radius="lg" />
      </PageShell>
    )
  }

  if (!verTablero) {
    return (
      <PageShell>
        <PageHeader
          title="Dispensario Médico"
          description="Sistema de Salud Ambulatoria — GADPE"
        />
        <EmptyState
          icon={IconStethoscope}
          title="Elija una pantalla en el menú"
          description="Su perfil no tiene una pantalla de inicio propia en el Dispensario."
        />
      </PageShell>
    )
  }

  return (
    <PageShell>
      <PageHeader
        title="Dispensario Médico"
        description="Sistema de Salud Ambulatoria — GADPE"
      />
      <TableroDispensario />
    </PageShell>
  )
}
