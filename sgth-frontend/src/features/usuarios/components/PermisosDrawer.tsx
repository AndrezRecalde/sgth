'use client'

import { Group, Loader, Stack, Text } from '@mantine/core'
import { ModalFooter, SgthDrawer } from '@/components/ui'
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
    <SgthDrawer
      opened={opened}
      onClose={onClose}
      title="Permisos adicionales"
      description={nombreUsuario}
    >
          <Stack gap="md">
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

      {/* El pie de ModalFooter es pegajoso: con la lista larga los botones se
          quedan al fondo del panel. */}
      <ModalFooter
        onCancel={onClose}
        onSubmit={handleGuardar}
        submitLabel="Guardar permisos"
        submitting={sincronizarPermisos.isPending}
        leftSection={
          <Text size="xs" c="dimmed">
            {seleccionados.length} permiso(s) adicional(es)
          </Text>
        }
      />
    </SgthDrawer>
  )
}
