'use client'

import {
  Avatar, Divider, Group, Menu, Stack, Switch, Text,
  UnstyledButton, useMantineColorScheme,
} from '@mantine/core'
import { IconLogout, IconMoon, IconSun } from '@tabler/icons-react'
import { useAuth } from '@/hooks/useAuth'
import { ROUTES } from '@/config/routes'

/** Iniciales para el avatar: primer nombre + primer apellido. */
function iniciales(nombre: string): string {
  const partes = nombre.trim().split(' ').filter(Boolean)
  if (partes.length === 0) return 'US'
  // Los nombres ecuatorianos suelen ser "Nombre1 Nombre2 Apellido1 Apellido2":
  // la tercera palabra es el primer apellido, no la segunda.
  if (partes.length >= 3) return (partes[0][0] + partes[2][0]).toUpperCase()
  if (partes.length === 2) return (partes[0][0] + partes[1][0]).toUpperCase()
  return partes[0].slice(0, 2).toUpperCase()
}

/**
 * Menú de cuenta. Agrupa TODAS las preferencias del usuario, incluido el modo
 * oscuro: la barra superior no lleva interruptor de tema suelto.
 */
export function UserMenu() {
  const { colorScheme, toggleColorScheme } = useMantineColorScheme()
  const { usuario, clearAuth } = useAuth()

  const nombre = usuario?.nombre_completo || usuario?.usuario_ti || 'Usuario'
  const iniciada = iniciales(nombre)
  const primerNombre = nombre.split(' ')[0]

  const cerrarSesion = () => {
    clearAuth()
    // Recarga completa a propósito: descarta la caché de TanStack Query, que
    // guarda datos del servidor del usuario que sale.
    window.location.href = `${ROUTES.AUTH.LOGIN}?logout=true`
  }

  return (
    <Menu width={280} position="bottom-end" transitionProps={{ transition: 'pop' }}>
      <Menu.Target>
        <UnstyledButton aria-label="Menú de usuario">
          <Avatar variant="filled" color="var(--sgth-accent)" size={34} radius="xl" fw={600}>
            {iniciada}
          </Avatar>
        </UnstyledButton>
      </Menu.Target>

      <Menu.Dropdown p="xs">
        <Group gap="sm" wrap="nowrap" px="xs" py="sm">
          <Avatar variant="filled" color="var(--sgth-accent)" size={42} radius="xl" fw={600}>
            {iniciada}
          </Avatar>
          <Stack gap={0} style={{ minWidth: 0 }}>
            <Text size="sm" fw={600} truncate>
              {primerNombre}
            </Text>
            <Text size="xs" c="dimmed" truncate>
              {usuario?.email}
            </Text>
          </Stack>
        </Group>

        <Divider my={4} />

        {/* Aquí iría "Gestionar mi cuenta". Se quitó porque /configuracion no
            existe: el menú anterior ofrecía un botón que no llevaba a ninguna
            parte. Cuando exista la pantalla, se agrega con su ruta en
            config/routes.ts, no con la URL escrita aquí. */}

        <Menu.Item
          closeMenuOnClick={false}
          onClick={toggleColorScheme}
          leftSection={
            colorScheme === 'dark' ? (
              <IconSun size={17} stroke={1.6} />
            ) : (
              <IconMoon size={17} stroke={1.6} />
            )
          }
          rightSection={
            <Switch
              checked={colorScheme === 'dark'}
              size="xs"
              readOnly
              tabIndex={-1}
              style={{ pointerEvents: 'none' }}
              aria-hidden
            />
          }
        >
          Modo oscuro
        </Menu.Item>

        <Divider my={4} />

        <Menu.Item
          color="red"
          leftSection={<IconLogout size={17} stroke={1.6} />}
          onClick={cerrarSesion}
        >
          Cerrar sesión
        </Menu.Item>
      </Menu.Dropdown>
    </Menu>
  )
}
