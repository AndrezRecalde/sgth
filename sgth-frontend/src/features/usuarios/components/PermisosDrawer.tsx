'use client'

import {
  Box, Button, Drawer, Group, Loader, ScrollArea, Stack, Text, ThemeIcon,
} from '@mantine/core'
import { IconShieldCheck } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useSeleccionPermisos } from '../hooks/useSeleccionPermisos'
import { useUsuarioMutations } from '../hooks/useUsuarioMutations'
import { PermisosPorRol } from './PermisosPorRol'
import { AcordeonPermisos } from './AcordeonPermisos'
import type { Usuario } from '@/types/api'

interface Props {
  opened:  boolean
  onClose: () => void
  usuario: Usuario | null
}

/** Panel de permisos directos: los que se conceden fuera de los roles. */
export function PermisosDrawer({ opened, onClose, usuario }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const { sincronizarPermisos } = useUsuarioMutations()

  const {
    usuarioId,
    grupos,
    cargando,
    permisosActivos,
    permisosCubiertos,
    seleccionados,
    togglePermiso,
  } = useSeleccionPermisos(usuario, opened)

  const handleGuardar = () => {
    if (!usuarioId) return
    sincronizarPermisos.mutate(
      { id: usuarioId, permisos: seleccionados },
      { onSuccess: onClose },
    )
  }

  const nombreUsuario = usuario?.nombre_completo
    || usuario?.servidor?.nombre
    || usuario?.email
    || '—'

  return (
    <Drawer
      opened={opened}
      onClose={onClose}
      title={
        <Group gap="xs">
          <ThemeIcon color="violet" variant="light" size="md" radius="md">
            <IconShieldCheck size={16} />
          </ThemeIcon>
          <Stack gap={0}>
            <Text fw={700} size="sm">Permisos adicionales</Text>
            <Text size="xs" c="dimmed">{nombreUsuario}</Text>
          </Stack>
        </Group>
      }
      position="right"
      size={isMobile ? '100%' : 520}
      padding="lg"
    >
      {/* Columna a altura completa: el pie queda anclado y solo scrollea la
          lista. Antes el pie vivía fuera del ScrollArea de alto fijo y en
          pantallas cortas los botones quedaban fuera del viewport. */}
      <Stack gap={0} h="calc(100vh - 80px)">
        <ScrollArea style={{ flex: 1 }} offsetScrollbars>
          <Stack gap="md" pr="xs">
            {cargando ? (
              <Group justify="center" py="xl">
                <Loader size="sm" />
                <Text size="sm" c="dimmed">Cargando permisos...</Text>
              </Group>
            ) : (
              <>
                <PermisosPorRol
                  roles={usuario?.roles ?? []}
                  permisosCubiertos={permisosCubiertos}
                />

                <Stack gap={4}>
                  <Text size="xs" fw={600} c="dimmed">PERMISOS ADICIONALES</Text>
                  <Text size="xs" c="dimmed">
                    Selecciona permisos extra que este usuario necesita fuera de
                    su rol. Los que ya vienen por rol aparecen bloqueados.
                  </Text>
                </Stack>

                <AcordeonPermisos
                  grupos={grupos}
                  permisosActivos={permisosActivos}
                  permisosCubiertos={permisosCubiertos}
                  seleccionados={seleccionados}
                  onToggle={togglePermiso}
                />
              </>
            )}
          </Stack>
        </ScrollArea>

        <Box
          pt="md"
          style={{ borderTop: '1px solid var(--mantine-color-default-border)' }}
        >
          <Group justify="space-between">
            <Text size="xs" c="dimmed">
              {seleccionados.length} permiso(s) adicional(es)
            </Text>
            <Group gap="xs">
              <Button variant="default" onClick={onClose}>Cancelar</Button>
              <Button
                color="violet"
                loading={sincronizarPermisos.isPending}
                leftSection={<IconShieldCheck size={14} />}
                onClick={handleGuardar}
              >
                Guardar permisos
              </Button>
            </Group>
          </Group>
        </Box>
      </Stack>
    </Drawer>
  )
}
