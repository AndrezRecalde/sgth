'use client'

import { useRouter } from 'next/navigation'
import {
  Group, Menu, Stack, Text, ThemeIcon, Tooltip, UnstyledButton,
} from '@mantine/core'
import { IconCheck, IconSelector } from '@tabler/icons-react'
import { useAuth } from '@/hooks/useAuth'
import { getSubsistemasDisponibles } from '@/config/nav'
import { SUBSISTEMAS } from '@/config/subsistemas'
import type { Subsistema } from '@/config/routes'
import classes from './Sidebar.module.css'

interface Props {
  actual: Subsistema
  collapsed: boolean
}

/**
 * Conmutador entre SGTH, Dispensario y Portal.
 *
 * Vive en el sidebar, bajo la marca: el subsistema activo determina TODO el
 * menú que hay debajo, así que el control que lo cambia debe estar ahí y no
 * escondido tras un icono de la barra superior.
 *
 * Si el usuario solo tiene acceso a un subsistema no hay nada que conmutar:
 * se muestra como etiqueta de contexto, sin menú.
 */
export function SubsistemaSwitcher({ actual, collapsed }: Props) {
  const router = useRouter()
  const { usuario } = useAuth()

  const disponibles = getSubsistemasDisponibles(usuario?.roles ?? [])
  const cfg = SUBSISTEMAS[actual]
  const Icono = cfg.icon
  const puedeConmutar = disponibles.length > 1

  const cara = (
    <UnstyledButton
      component={puedeConmutar ? 'button' : 'div'}
      className={`${classes.switcher} ${collapsed ? classes.switcherCollapsed : ''}`}
      aria-label={
        puedeConmutar ? `Subsistema actual: ${cfg.nombre}. Cambiar` : cfg.nombre
      }
    >
      <ThemeIcon color={cfg.color} variant="light" size={collapsed ? 26 : 28} radius="md">
        <Icono size={16} />
      </ThemeIcon>
      {!collapsed && (
        <>
          <span className={classes.switcherLabel}>{cfg.nombre}</span>
          {puedeConmutar && (
            <IconSelector size={14} className={classes.chevron} />
          )}
        </>
      )}
    </UnstyledButton>
  )

  if (!puedeConmutar) {
    return collapsed ? (
      <Tooltip label={cfg.nombre} position="right">
        {cara}
      </Tooltip>
    ) : (
      cara
    )
  }

  return (
    <Menu width={300} position={collapsed ? 'right-start' : 'bottom-start'} withinPortal>
      <Menu.Target>
        {collapsed ? (
          <Tooltip label={cfg.nombre} position="right">
            {cara}
          </Tooltip>
        ) : (
          cara
        )}
      </Menu.Target>

      <Menu.Dropdown>
        <Menu.Label>Cambiar de subsistema</Menu.Label>
        {disponibles.map((key) => {
          const item = SUBSISTEMAS[key]
          const ItemIcono = item.icon
          const activo = key === actual

          return (
            <Menu.Item
              key={key}
              onClick={() => router.push(item.home)}
              // `Menu.Item` no deja que su etiqueta interna encoja, así que un
              // texto largo empuja el ancho del menú en vez de recortarse.
              classNames={{ itemLabel: classes.menuItemLabel }}
            >
              <Group gap="sm" wrap="nowrap" w="100%">
                <ThemeIcon color={item.color} variant="light" size="md" radius="md">
                  <ItemIcono size={16} />
                </ThemeIcon>
                <Stack gap={0} style={{ flex: 1, minWidth: 0 }}>
                  <Text size="sm" fw={activo ? 600 : 500} truncate>
                    {item.nombre}
                  </Text>
                  <Text size="xs" c="dimmed" truncate>
                    {item.descripcion}
                  </Text>
                </Stack>
                {activo && (
                  <IconCheck
                    size={16}
                    color="var(--sgth-accent)"
                    style={{ flexShrink: 0 }}
                  />
                )}
              </Group>
            </Menu.Item>
          )
        })}
      </Menu.Dropdown>
    </Menu>
  )
}
