'use client'

import Link from 'next/link'
import { Card, Group, Stack, Text } from '@mantine/core'
import { IconCircleCheck } from '@tabler/icons-react'
import { ROUTES } from '@/config/routes'
import { EtiquetasComunicado } from '@/features/comunicados/components/EtiquetasComunicado'
import type { ComunicadoPortal } from '@/features/comunicados/services/tipos'
import { fechaPublicacion } from '@/features/comunicados/utils/presentacion'
import classes from './TarjetaComunicado.module.css'

/** Cómo va con esta publicación quien la mira: sin leer, leída, confirmada o vencida. */
function Lectura({ c }: { c: ComunicadoPortal }) {
  if (c.confirmado_at) {
    return (
      <Group gap={4} c="emerald" wrap="nowrap">
        <IconCircleCheck size={14} />
        <Text size="xs" fw={600}>Confirmó el {fechaPublicacion(c.confirmado_at)}</Text>
      </Group>
    )
  }
  if (c.vencido) return <Text size="xs" c="dimmed">Venció el {fechaPublicacion(c.publicado_hasta)}</Text>
  if (!c.leido_at) {
    return (
      <Group gap={6} wrap="nowrap" className={classes.sinLeer}>
        <span className={classes.punto} aria-hidden />
        <Text size="xs" fw={600}>Sin leer</Text>
      </Group>
    )
  }
  return <Text size="xs" c="dimmed">Leído</Text>
}

/**
 * Una publicación en el listado del Portal (docs/diseno-noticias-comunicados.md,
 * 5.2): solo texto —no hay portada—, con el resumen o el comienzo del texto.
 * La tarjeta entera abre el detalle.
 */
export function TarjetaComunicado({ c }: { c: ComunicadoPortal }) {
  return (
    <Card component={Link} href={ROUTES.PORTAL.COMUNICADO(c.id)} withBorder radius="lg" padding="md" className={classes.tarjeta}>
      <Stack gap="xs" h="100%">
        <EtiquetasComunicado c={c} />
        <Text fw={c.leido_at ? 500 : 600} lh={1.35}>{c.titulo}</Text>
        <Text size="sm" c="dimmed" lineClamp={3}>{c.extracto}</Text>
        <Group justify="space-between" gap="xs" mt="auto" pt="xs" className={classes.pie}>
          <Text size="xs" c="dimmed">
            {fechaPublicacion(c.publicado_desde)}{c.referencia ? ` · ${c.referencia}` : ''}
          </Text>
          <Lectura c={c} />
        </Group>
      </Stack>
    </Card>
  )
}
