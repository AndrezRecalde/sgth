'use client'

import { useState } from 'react'
import Link from 'next/link'
import { ActionIcon, Indicator, Menu, Stack, Text, ThemeIcon, Tooltip } from '@mantine/core'
import { IconBell, IconChevronRight, IconNews, IconSpeakerphone } from '@tabler/icons-react'
import { ROUTES } from '@/config/routes'
import { useAuthStore } from '@/store/auth.store'
import { useResumenComunicados } from '../hooks/useMisComunicados'
import { fechaPublicacion } from '../utils/presentacion'

const SIN_PERMISOS: readonly string[] = []

/**
 * La campana de la barra superior (docs/diseno-noticias-comunicados.md, 5.4 y
 * D7): cuántas publicaciones no ha abierto y las cinco más nuevas, desde
 * cualquier subsistema. Es el único aviso: no se manda correo (D8).
 *
 * Quien no tiene el Portal no la ve: no tendría nada que leer.
 */
export function CampanaComunicados() {
  const tienePortal = useAuthStore((s) => (s.usuario?.permisos ?? SIN_PERMISOS).includes('acceso-autoservicio'))
  const { data } = useResumenComunicados()
  // Con el menú abierto el tooltip sobra: se ponía encima del título del menú.
  const [abierto, setAbierto] = useState(false)

  if (!tienePortal) return null

  const sinLeer = data?.sin_leer ?? 0
  const noLeidos = data?.no_leidos ?? []
  const etiqueta = sinLeer
    ? `Noticias y comunicados: ${sinLeer} sin leer`
    : 'Noticias y comunicados'

  return (
    <Menu position="bottom-end" width={360} shadow="md" opened={abierto} onChange={setAbierto}>
      <Menu.Target>
        <Tooltip label={etiqueta} disabled={abierto}>
          <Indicator color="red" size={16} offset={6} label={sinLeer > 9 ? '9+' : sinLeer} disabled={!sinLeer}>
            <ActionIcon size="lg" radius="xl" aria-label={etiqueta}>
              <IconBell size={19} stroke={1.6} />
            </ActionIcon>
          </Indicator>
        </Tooltip>
      </Menu.Target>

      <Menu.Dropdown>
        <Menu.Label>Noticias y comunicados sin leer</Menu.Label>

        {noLeidos.length === 0 ? (
          <Text size="sm" c="dimmed" px="sm" py="xs">
            Está al día: no tiene publicaciones sin leer.
          </Text>
        ) : (
          noLeidos.map((c) => (
            <Menu.Item
              key={c.id}
              component={Link}
              href={ROUTES.PORTAL.COMUNICADO(c.id)}
              leftSection={
                <ThemeIcon variant="light" radius="xl" size="md">
                  {c.tipo === 'comunicado' ? <IconSpeakerphone size={15} /> : <IconNews size={15} />}
                </ThemeIcon>
              }
            >
              <Stack gap={2}>
                <Text size="sm" fw={600} lineClamp={2} lh={1.35}>{c.titulo}</Text>
                <Text size="xs" c="dimmed">
                  {fechaPublicacion(c.publicado_desde)}
                  {c.por_confirmar && <Text span size="xs" c="amber" fw={600}> · Por confirmar</Text>}
                </Text>
              </Stack>
            </Menu.Item>
          ))
        )}

        <Menu.Divider />
        <Menu.Item component={Link} href={ROUTES.PORTAL.COMUNICADOS} rightSection={<IconChevronRight size={14} />}>
          Ver todas en el Portal
        </Menu.Item>
      </Menu.Dropdown>
    </Menu>
  )
}
