'use client'

import { ActionIcon, Burger, Group, Indicator, Kbd, Tooltip, UnstyledButton } from '@mantine/core'
import {
  IconBell, IconLayoutSidebarLeftCollapse,
  IconLayoutSidebarLeftExpand, IconSearch,
} from '@tabler/icons-react'
import { AppBreadcrumbs } from './AppBreadcrumbs'
import { UserMenu } from './UserMenu'
import { DisponibilidadToggle } from '@/features/dispensario/components/DisponibilidadToggle'
import { usePaletteStore } from '@/store/ui.palette.store'
import type { Subsistema } from '@/config/routes'
import classes from './Topbar.module.css'

interface Props {
  subsistema: Subsistema
  mobileOpened: boolean
  navbarCollapsed: boolean
  onMobileToggle: () => void
  onNavbarToggle: () => void
}

/**
 * Barra superior: dónde estoy y a qué llego desde aquí.
 *
 * La identidad institucional y la navegación viven en el sidebar; aquí solo
 * quedan el control del sidebar, las migas de pan y las acciones globales.
 */
export function Topbar({
  subsistema,
  mobileOpened,
  navbarCollapsed,
  onMobileToggle,
  onNavbarToggle,
}: Props) {
  const abrirPaleta = usePaletteStore((s) => s.open)

  return (
    <header className={classes.topbar}>
      <div className={classes.left}>
        <Burger
          opened={mobileOpened}
          onClick={onMobileToggle}
          hiddenFrom="md"
          size="sm"
          aria-label="Abrir menú"
        />

        <Tooltip label={navbarCollapsed ? 'Expandir menú' : 'Plegar menú'}>
          <ActionIcon
            onClick={onNavbarToggle}
            visibleFrom="md"
            size="lg"
            aria-label={navbarCollapsed ? 'Expandir menú' : 'Plegar menú'}
          >
            {navbarCollapsed ? (
              <IconLayoutSidebarLeftExpand size={20} stroke={1.6} />
            ) : (
              <IconLayoutSidebarLeftCollapse size={20} stroke={1.6} />
            )}
          </ActionIcon>
        </Tooltip>

        <Group visibleFrom="sm" gap={0} style={{ minWidth: 0 }}>
          <AppBreadcrumbs subsistema={subsistema} />
        </Group>
      </div>

      <div className={classes.right}>
        {/* Solo en el dispensario, y dentro de él solo a quien atiende: es su
            estado, y se cambia desde donde esté trabajando. */}
        {subsistema === 'salud' && <DisponibilidadToggle />}

        <UnstyledButton
          onClick={abrirPaleta}
          className={classes.searchTrigger}
          visibleFrom="lg"
          aria-label="Buscar pantalla"
        >
          <IconSearch size={15} />
          <span className={classes.searchLabel}>Buscar…</span>
          <Kbd size="xs">Ctrl+K</Kbd>
        </UnstyledButton>

        <Tooltip label="Buscar">
          <ActionIcon
            onClick={abrirPaleta}
            hiddenFrom="lg"
            size="lg"
            radius="xl"
            aria-label="Buscar pantalla"
          >
            <IconSearch size={19} stroke={1.6} />
          </ActionIcon>
        </Tooltip>

        <Tooltip label="Notificaciones">
          <Indicator color="red" size={7} offset={7} disabled>
            <ActionIcon size="lg" radius="xl" aria-label="Notificaciones">
              <IconBell size={19} stroke={1.6} />
            </ActionIcon>
          </Indicator>
        </Tooltip>

        <UserMenu />
      </div>
    </header>
  )
}
