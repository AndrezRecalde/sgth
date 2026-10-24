'use client'

import Link from 'next/link'
import { Anchor, Card, Group, Skeleton, Stack, Text, ThemeIcon, UnstyledButton } from '@mantine/core'
import { IconChevronRight, IconSpeakerphone } from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { EtiquetasComunicado } from '@/features/comunicados/components/EtiquetasComunicado'
import { useResumenComunicados } from '@/features/comunicados/hooks/useMisComunicados'
import { fechaPublicacion } from '@/features/comunicados/utils/presentacion'
import classes from './NoticiasCard.module.css'

/**
 * Las noticias y comunicados en el inicio del Portal
 * (docs/diseno-noticias-comunicados.md, 5.1): las cinco primeras —fijadas
 * antes, con el color del Portal—, con un punto en las que no ha abierto.
 */
export function NoticiasCard() {
  const { data, isLoading, isError } = useResumenComunicados()
  const recientes = data?.recientes ?? []

  return (
    <Card withBorder radius="xl" p="xl">
      <Stack gap="md">
        <Group gap="xs" wrap="nowrap">
          <ThemeIcon variant="light" size="md">
            <IconSpeakerphone size={16} />
          </ThemeIcon>
          <Text fw={600} size="md" style={{ flex: 1 }}>
            Noticias y comunicados
          </Text>
          {!!data?.sin_leer && <StatusBadge tone="info">{data.sin_leer} sin leer</StatusBadge>}
        </Group>

        {isLoading ? (
          <Stack gap="sm">
            {[0, 1, 2].map((i) => <Skeleton key={i} height={64} radius="md" />)}
          </Stack>
        ) : isError ? (
          <Text size="sm" c="dimmed">No se pudieron cargar las novedades. Vuelva a intentarlo en un momento.</Text>
        ) : recientes.length === 0 ? (
          <Stack gap={4} align="center" py="lg">
            <ThemeIcon variant="light" size={48} radius="xl">
              <IconSpeakerphone size={24} />
            </ThemeIcon>
            <Text size="sm" c="dimmed" ta="center" maw={300}>
              Talento Humano no ha publicado novedades para usted. Aquí aparecerán cuando lo haga.
            </Text>
          </Stack>
        ) : (
          <Stack gap={0}>
            {recientes.map((c) => (
              <UnstyledButton
                key={c.id}
                component={Link}
                href={ROUTES.PORTAL.COMUNICADO(c.id)}
                className={`${classes.fila} ${c.fijado ? classes.fijada : ''}`}
              >
                <span className={c.leido_at ? classes.leido : classes.sinLeer} aria-label={c.leido_at ? undefined : 'Sin leer'} />
                <Stack gap={4} className={classes.contenido}>
                  <EtiquetasComunicado c={c} />
                  <Text size="sm" fw={c.leido_at ? 500 : 600} lh={1.4}>{c.titulo}</Text>
                  <Text size="sm" c="dimmed" lineClamp={2}>{c.extracto}</Text>
                  <Text size="xs" c="dimmed">
                    {fechaPublicacion(c.publicado_desde)}{c.referencia ? ` · ${c.referencia}` : ''}
                  </Text>
                </Stack>
              </UnstyledButton>
            ))}
          </Stack>
        )}

        {recientes.length > 0 && (
          <Anchor component={Link} href={ROUTES.PORTAL.COMUNICADOS} size="sm" fw={600} className={classes.verTodas}>
            Ver todas las noticias y comunicados
            <IconChevronRight size={16} />
          </Anchor>
        )}
      </Stack>
    </Card>
  )
}
