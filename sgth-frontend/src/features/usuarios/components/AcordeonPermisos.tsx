'use client'

import { Accordion, Alert, Badge, Checkbox, Group, Stack, Text } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { etiquetaPermiso } from '../constants/permisos'
import type { PermisoGrupo, PermisoItem } from '@/types/api'

interface Props {
  grupos: PermisoGrupo[]
  /** Marcados a mano por quien edita. */
  permisosActivos: string[]
  /** Heredados por rol: se ven marcados y bloqueados. */
  permisosCubiertos: Set<string>
  /** Solo los extra, para el contador por módulo. */
  seleccionados: string[]
  onToggle: (nombre: string) => void
}

/** Catálogo de permisos agrupado por módulo, con el contador de extras. */
export function AcordeonPermisos({
  grupos,
  permisosActivos,
  permisosCubiertos,
  seleccionados,
  onToggle,
}: Props) {
  if (grupos.length === 0) {
    return (
      <Alert color="gray" variant="light" icon={<IconInfoCircle size={16} />}>
        <Text size="xs">No hay permisos disponibles configurados.</Text>
      </Alert>
    )
  }

  return (
    <Accordion variant="separated" radius="md">
      {grupos.map(grupo => {
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
                          onChange={() => onToggle(p.nombre)}
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
  )
}
