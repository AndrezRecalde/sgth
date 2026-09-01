'use client'

import { useState } from 'react'
import {
  Drawer, Stack, Text, Badge, Group, Accordion, Checkbox,
  ScrollArea, Button, ThemeIcon, Divider, Loader, Alert, Collapse,
  UnstyledButton, Box,
} from '@mantine/core'
import { IconShieldCheck, IconChevronDown, IconInfoCircle } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { usePermisos, usePermisosUsuario } from '../hooks/usePermisos'
import { useUsuarioMutations } from '../hooks/useUsuarioMutations'
import { etiquetaRol } from '../constants/roles'
import type { Usuario, PermisoGrupo, PermisoItem } from '@/types/api'

interface Props {
  opened:   boolean
  onClose:  () => void
  usuario:  Usuario | null
}

/** 'gestionar-inventario-med' → 'Gestionar inventario med'. */
const etiquetaPermiso = (nombre: string) => {
  const texto = nombre.replace(/-/g, ' ')
  return texto.charAt(0).toUpperCase() + texto.slice(1)
}

export function PermisosDrawer({ opened, onClose, usuario }: Props) {
  const { isMobile }            = useMobileBreakpoint()
  const { data: grupos = [] }   = usePermisos()
  const { sincronizarPermisos } = useUsuarioMutations()

  const usuarioId = usuario?.id ? Number(usuario.id) : null

  const { data: permisosUsuario, isLoading: cargando } =
    usePermisosUsuario(opened ? usuarioId : null)

  const [permisosActivos, setPermisosActivos] = useState<string[]>([])
  const [verPermisosRol,  setVerPermisosRol]  = useState(false)

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
  const roles = usuario?.roles ?? []
  const permisosCubiertos = new Set(
    (grupos as PermisoGrupo[]).flatMap(g =>
      g.permisos
        .filter((p: PermisoItem) => roles.some(r => p.roles?.includes(r)))
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

  const seleccionados = permisosActivos.filter(p => !permisosCubiertos.has(p))

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
                {roles.length > 0 && (
                  <>
                    <UnstyledButton onClick={() => setVerPermisosRol(v => !v)}>
                      <Group gap="xs" wrap="nowrap">
                        <IconChevronDown
                          size={14}
                          style={{
                            transform: verPermisosRol ? 'rotate(0deg)' : 'rotate(-90deg)',
                            transition: 'transform 150ms ease',
                          }}
                        />
                        <Text size="xs" fw={600} c="dimmed">
                          {permisosCubiertos.size} PERMISOS YA CONCEDIDOS POR SUS ROLES
                        </Text>
                      </Group>
                    </UnstyledButton>

                    <Collapse expanded={verPermisosRol}>
                      <Stack gap="xs">
                        <Group gap={4} wrap="wrap">
                          {roles.map(r => (
                            <Badge key={r} size="xs" variant="filled" color="teal">
                              {etiquetaRol(r)}
                            </Badge>
                          ))}
                        </Group>
                        <Group gap={4} wrap="wrap">
                          {Array.from(permisosCubiertos).sort().map(p => (
                            <Badge key={p} size="xs" variant="light" color="teal">
                              {etiquetaPermiso(p)}
                            </Badge>
                          ))}
                        </Group>
                      </Stack>
                    </Collapse>

                    <Divider />
                  </>
                )}

                <Stack gap={4}>
                  <Text size="xs" fw={600} c="dimmed">PERMISOS ADICIONALES</Text>
                  <Text size="xs" c="dimmed">
                    Selecciona permisos extra que este usuario necesita fuera de
                    su rol. Los que ya vienen por rol aparecen bloqueados.
                  </Text>
                </Stack>

                {(grupos as PermisoGrupo[]).length === 0 && (
                  <Alert color="gray" variant="light" icon={<IconInfoCircle size={16} />}>
                    <Text size="xs">No hay permisos disponibles configurados.</Text>
                  </Alert>
                )}

                <Accordion variant="separated" radius="md">
                  {(grupos as PermisoGrupo[]).map(grupo => {
                    const extras = grupo.permisos.filter(
                      p => seleccionados.includes(p.nombre)
                    ).length

                    return (
                      <Accordion.Item key={grupo.modulo} value={grupo.modulo}>
                        <Accordion.Control>
                          <Group gap="xs">
                            <Text size="sm" fw={600}>{grupo.modulo}</Text>
                            {extras > 0 && (
                              <Badge size="xs" variant="filled" color="violet">
                                {extras}
                              </Badge>
                            )}
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
                                <Group key={p.nombre} justify="space-between" wrap="nowrap">
                                  <Group gap="xs" wrap="nowrap">
                                    <Checkbox
                                      size="sm"
                                      color="violet"
                                      checked={activo || cubierto}
                                      disabled={cubierto}
                                      onChange={() => togglePermiso(p.nombre)}
                                      aria-label={etiquetaPermiso(p.nombre)}
                                    />
                                    <Text size="sm" c={cubierto ? 'dimmed' : undefined}>
                                      {etiquetaPermiso(p.nombre)}
                                    </Text>
                                  </Group>
                                  {cubierto && (
                                    <Badge size="xs" color="teal" variant="light">
                                      por rol
                                    </Badge>
                                  )}
                                </Group>
                              )
                            })}
                          </Stack>
                        </Accordion.Panel>
                      </Accordion.Item>
                    )
                  })}
                </Accordion>
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
