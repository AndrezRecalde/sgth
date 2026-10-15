'use client'

import {
  Divider, Group, Menu, Stack, Switch, Text,
  UnstyledButton, useMantineColorScheme,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconKey, IconLogout, IconMoon, IconSun } from '@tabler/icons-react'
import { useAuth } from '@/hooks/useAuth'
import { CambiarContrasenaModal } from '@/features/auth/components/CambiarContrasenaModal'
import { useCerrarSesion } from '@/features/auth/hooks/useCerrarSesion'
import { FotoServidor } from '@/features/comunicacion/components/FotoServidor'

/**
 * Menú de cuenta. Agrupa TODAS las preferencias del usuario, incluido el modo
 * oscuro: la barra superior no lleva interruptor de tema suelto.
 */
export function UserMenu() {
  const { colorScheme, toggleColorScheme } = useMantineColorScheme()
  const { usuario, hasPermiso } = useAuth()
  const { cerrarSesion } = useCerrarSesion()
  const [cambiandoClave, cambioDeClave] = useDisclosure(false)

  const nombre = usuario?.nombre_completo || usuario?.usuario_ti || 'Usuario'
  const primerNombre = nombre.split(' ')[0]
  // Con foto, la foto; sin ella, las iniciales, como antes (fase 1.2).
  const fotografiaId = usuario?.servidor?.fotografia?.id

  return (
    <>
      <Menu width={280} position="bottom-end" transitionProps={{ transition: 'pop' }}>
        <Menu.Target>
          <UnstyledButton aria-label="Menú de usuario">
            <FotoServidor fotografiaId={fotografiaId} nombre={nombre}
              variant="filled" color="var(--sgth-accent)" size={34} radius="xl" fw={600} />
          </UnstyledButton>
        </Menu.Target>

        <Menu.Dropdown p="xs">
          <Group gap="sm" wrap="nowrap" px="xs" py="sm">
            <FotoServidor fotografiaId={fotografiaId} nombre={nombre}
              variant="filled" color="var(--sgth-accent)" size={42} radius="xl" fw={600} />
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

          {/* Lo tienen todos los servidores; el permiso existe para poder
              retirárselo a una cuenta compartida o de servicio. */}
          {hasPermiso('cambiar-contrasena') && (
            <Menu.Item
              leftSection={<IconKey size={17} stroke={1.6} />}
              onClick={cambioDeClave.open}
            >
              Cambiar contraseña
            </Menu.Item>
          )}

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

      {/* Fuera del menú: su desplegable se desmonta al cerrarse, y con él
          se iría el modal. */}
      <CambiarContrasenaModal opened={cambiandoClave} onClose={cambioDeClave.close} />
    </>
  )
}
