'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { Anchor, Breadcrumbs, Text } from '@mantine/core'
import { IconChevronRight } from '@tabler/icons-react'
import { useAuth } from '@/hooks/useAuth'
import { findNavTrail } from '@/config/nav'
import { SUBSISTEMAS } from '@/config/subsistemas'
import type { Subsistema } from '@/config/routes'

interface Props {
  subsistema: Subsistema
}

/**
 * Migas de pan derivadas del menú, no de la URL.
 *
 * Derivarlas del pathname obligaría a "embellecer" segmentos
 * (`riesgos-laborales` → `Riesgos laborales`) y aun así no sabría a qué grupo
 * pertenece la pantalla. Como el menú ya tiene etiquetas en español y
 * jerarquía, la fuente de verdad es `config/nav.ts`.
 *
 * En rutas de detalle (`/expediente/123`) se muestra la miga del listado
 * padre: el título de la página ya identifica el registro concreto.
 */
export function AppBreadcrumbs({ subsistema }: Props) {
  const pathname = usePathname()
  const { usuario } = useAuth()

  const cfg = SUBSISTEMAS[subsistema]
  const trail = findNavTrail(subsistema, usuario?.permisos ?? [], pathname)

  if (!trail) return null

  const migas = [
    { label: cfg.nombre, href: cfg.home },
    ...(trail.padre ? [{ label: trail.padre, href: undefined }] : []),
    { label: trail.label, href: trail.href },
  ]

  return (
    <Breadcrumbs
      separator={<IconChevronRight size={13} stroke={2} />}
      separatorMargin={6}
      aria-label="Ruta de navegación"
    >
      {migas.map((miga, i) => {
        const esUltima = i === migas.length - 1

        if (esUltima || !miga.href) {
          return (
            <Text
              key={`${miga.label}-${i}`}
              size="sm"
              fw={esUltima ? 600 : 400}
              c={esUltima ? undefined : 'dimmed'}
            >
              {miga.label}
            </Text>
          )
        }

        return (
          <Anchor key={miga.href} component={Link} href={miga.href} size="sm" c="dimmed">
            {miga.label}
          </Anchor>
        )
      })}
    </Breadcrumbs>
  )
}
