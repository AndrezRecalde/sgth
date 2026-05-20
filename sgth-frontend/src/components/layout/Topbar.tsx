'use client'

import { Group, Burger, ActionIcon, useMantineColorScheme, Box } from '@mantine/core'
import { IconSun, IconMoon, IconBell } from '@tabler/icons-react'

interface TopbarProps {
  opened: boolean
  onBurgerClick: () => void
}

export function Topbar({ opened, onBurgerClick }: TopbarProps) {
  const { colorScheme, toggleColorScheme } = useMantineColorScheme()

  return (
    <Group h="100%" px="md" justify="space-between" align="center" style={{ borderBottom: '0.5px solid var(--mantine-color-default-border)' }}>
      <Group>
        <Burger opened={opened} onClick={onBurgerClick} hiddenFrom="md" size="sm" />
        <Box visibleFrom="md">
          <span style={{ fontSize: 14, color: 'var(--mantine-color-dimmed)' }}>Inicio</span>
        </Box>
      </Group>

      <Group gap="sm">
        <ActionIcon variant="default" size="lg" aria-label="Modo de color" onClick={toggleColorScheme}>
          {colorScheme === 'dark' ? <IconSun size={18} /> : <IconMoon size={18} />}
        </ActionIcon>
        <ActionIcon variant="default" size="lg" aria-label="Notificaciones">
          <IconBell size={18} />
        </ActionIcon>
      </Group>
    </Group>
  )
}
