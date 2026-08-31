'use client'

import { useState } from 'react'
import {
  Drawer, Stack, Text, Badge, Group,
  Accordion, Checkbox, ScrollArea,
  Button, ThemeIcon, Divider,
  Loader, Alert,
} from '@mantine/core'
import { IconShieldCheck } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { usePermisos, usePermisosUsuario } from '../hooks/usePermisos'
import { useUsuarioMutations } from '../hooks/useUsuarioMutations'
import type { Usuario, PermisoGrupo, PermisoItem } from '@/types/api'

interface Props {
  opened:   boolean
  onClose:  () => void
  usuario:  Usuario | null
}

export function PermisosDrawer({ opened, onClose, usuario }: Props) {
  const { isMobile }            = useMobileBreakpoint()
  const { data: grupos = [] }   = usePermisos()
  const { sincronizarPermisos } = useUsuarioMutations()

  const usuarioId = usuario?.id ? Number(usuario.id) : null

  // Los permisos del usuario ya tenían su hook de React Query; antes se
  // pedían a mano desde un efecto que además llamaba a setState en su cuerpo.
  const { data: permisosUsuario, isLoading: cargando } =
    usePermisosUsuario(opened ? usuarioId : null)

  const [permisosActivos, setPermisosActivos] = useState<string[]>([])
  const [guardando,       setGuardando]       = useState(false)

  // Los permisos cargados solo siembran la selección: a partir de ahí el
  // usuario marca y desmarca. Se resiembra al abrir el panel sobre otro
  // servidor, ajustando el estado durante el render en vez de en un efecto.
  const semilla = opened && usuarioId !== null && permisosUsuario
    ? String(usuarioId)
    : null
  const [semillaAplicada, setSemillaAplicada] = useState<string | null>(null)

  if (semilla !== semillaAplicada) {
    setSemillaAplicada(semilla)
    setPermisosActivos(
      semilla === null
        ? []
        : (permisosUsuario ?? []).map((p: PermisoItem) => p.nombre)
    )
  }

  // Permisos cubiertos por roles del usuario
  const roles = (usuario?.roles as string[]) ?? []
  const permisosCubiertos = new Set(
    (grupos as PermisoGrupo[]).flatMap(g =>
      g.permisos
        .filter((p: PermisoItem) =>
          roles.some(r => p.roles?.includes(r))
        )
        .map((p: PermisoItem) => p.nombre)
    )
  )

  const togglePermiso = (nombre: string) => {
    if (permisosCubiertos.has(nombre)) return
    setPermisosActivos(prev =>
      prev.includes(nombre)
        ? prev.filter(p => p !== nombre)
        : [...prev, nombre]
    )
  }

  const handleGuardar = async () => {
    if (!usuario?.id) return
    setGuardando(true)
    try {
      await sincronizarPermisos.mutateAsync({
        id:       Number(usuario.id),
        permisos: permisosActivos.filter(
          p => !permisosCubiertos.has(p)
        ),
      })
      onClose()
    } catch {}
    finally { setGuardando(false) }
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
      <ScrollArea h="calc(100vh - 120px)">
        <Stack gap="md">
          {cargando ? (
            <Group justify="center" py="xl">
              <Loader size="sm" />
              <Text size="sm" c="dimmed">
                Cargando permisos...
              </Text>
            </Group>
          ) : (
            <>
              {roles.length > 0 && (
                <>
                  <Text size="xs" fw={600} c="dimmed">
                    PERMISOS DEL ROL (solo lectura)
                  </Text>
                  <Group gap={4} wrap="wrap">
                    {Array.from(permisosCubiertos).map(p => (
                      <Badge
                        key={p}
                        size="xs"
                        variant="light"
                        color="teal"
                      >
                        {p}
                      </Badge>
                    ))}
                  </Group>
                  <Divider />
                </>
              )}

              <Text size="xs" fw={600} c="dimmed">
                PERMISOS ADICIONALES
              </Text>
              <Text size="xs" c="dimmed">
                Selecciona permisos extra que este usuario
                necesita fuera de su rol.
              </Text>

              {(grupos as PermisoGrupo[]).length === 0 && (
                <Alert color="gray" variant="light">
                  <Text size="xs">
                    No hay permisos disponibles configurados.
                  </Text>
                </Alert>
              )}

              <Accordion variant="separated" radius="md">
                {(grupos as PermisoGrupo[]).map(grupo => (
                  <Accordion.Item
                    key={grupo.modulo}
                    value={grupo.modulo}
                  >
                    <Accordion.Control>
                      <Group gap="xs">
                        <Text size="sm" fw={600}>
                          {grupo.modulo}
                        </Text>
                        <Badge size="xs" variant="outline" color="gray">
                          {grupo.permisos.length}
                        </Badge>
                      </Group>
                    </Accordion.Control>
                    <Accordion.Panel>
                      <Stack gap="xs">
                        {grupo.permisos.map((p: PermisoItem) => {
                          const cubierto = permisosCubiertos.has(p.nombre)
                          const activo   = permisosActivos.includes(p.nombre)
                          return (
                            <Group
                              key={p.nombre}
                              justify="space-between"
                              wrap="nowrap"
                            >
                              <Group gap="xs" wrap="nowrap">
                                <Checkbox
                                  size="sm"
                                  color="violet"
                                  checked={activo || cubierto}
                                  disabled={cubierto}
                                  onChange={() => togglePermiso(p.nombre)}
                                />
                                <Text
                                  size="sm"
                                  c={cubierto ? 'dimmed' : undefined}
                                >
                                  {p.nombre}
                                </Text>
                              </Group>
                              {cubierto && (
                                <Badge
                                  size="xs"
                                  color="teal"
                                  variant="light"
                                >
                                  por rol
                                </Badge>
                              )}
                            </Group>
                          )
                        })}
                      </Stack>
                    </Accordion.Panel>
                  </Accordion.Item>
                ))}
              </Accordion>
            </>
          )}
        </Stack>
      </ScrollArea>

      <Group justify="flex-end" pt="md"
        style={{ borderTop: '1px solid var(--mantine-color-gray-2)' }}>
        <Button variant="default" onClick={onClose}>
          Cancelar
        </Button>
        <Button
          color="violet"
          loading={guardando}
          leftSection={<IconShieldCheck size={14} />}
          onClick={handleGuardar}
        >
          Guardar permisos
        </Button>
      </Group>
    </Drawer>
  )
}
