'use client'

import { Stack, Text, Group, Divider, Skeleton } from '@mantine/core'
import {
  IconBuilding, IconBriefcase, IconSitemap,
} from '@tabler/icons-react'
import type { UnidadConRelaciones, PuestoConRelaciones } from '@/types/api'
import { SgthDrawer, StatusBadge } from '@/components/ui'

interface Props {
  opened: boolean
  onClose: () => void
  unidad: UnidadConRelaciones | null
  isLoading?: boolean
}

export function UnidadDrawer({ opened, onClose, unidad, isLoading }: Props) {

  const hijos    = unidad?.hijos ?? []
  const puestos  = unidad?.puestos ?? []
  const tipoAcronimo = unidad?.tipo_unidad?.acronimo ?? ''

  return (
    <SgthDrawer
      opened={opened}
      onClose={onClose}
      title={unidad?.nombre ?? 'Unidad'}
    >
      <Stack gap="md">
        {isLoading ? (
          <>
            <Skeleton height={20} />
            <Skeleton height={20} />
            <Skeleton height={80} />
          </>
        ) : (
          <>
            {tipoAcronimo && (
              <StatusBadge>
                {unidad?.tipo_unidad?.descripcion ?? tipoAcronimo}
              </StatusBadge>
            )}

            {hijos.length > 0 && (
              <>
                <Divider
                  label={
                    <Group gap="xs">
                      <IconSitemap size={14} />
                      <Text size="sm" fw={600}>
                        Subprocesos ({hijos.length})
                      </Text>
                    </Group>
                  }
                  labelPosition="left"
                />
                <Stack gap="xs">
                  {hijos.map(hijo => (
                    <Group
                      key={Number(hijo.id)}
                      gap="xs"
                      p="xs"
                      style={{
                        borderRadius: 8,
                        border: '1px solid var(--mantine-color-default-border)',
                      }}
                    >
                      <IconBuilding
                        size={14}
                        color="var(--mantine-color-dimmed)"
                      />
                      <Text size="sm">{hijo.nombre}</Text>
                    </Group>
                  ))}
                </Stack>
              </>
            )}

            {puestos.length > 0 && (
              <>
                <Divider
                  label={
                    <Group gap="xs">
                      <IconBriefcase size={14} />
                      <Text size="sm" fw={600}>
                        Puestos ({puestos.length})
                      </Text>
                    </Group>
                  }
                  labelPosition="left"
                />
                <Stack gap="xs">
                  {puestos.map((puesto, i) => {
                    const p = puesto as PuestoConRelaciones
                    return (
                      <Group
                        key={p.id ?? i}
                        justify="space-between"
                        p="xs"
                        style={{
                          borderRadius: 8,
                          border: '1px solid var(--mantine-color-default-border)',
                        }}
                      >
                        <Group gap="xs">
                          <IconBriefcase
                            size={14}
                            color="var(--mantine-color-dimmed)"
                          />
                          <Text size="sm">{p.denominacion ?? '-'}</Text>
                        </Group>
                        <Group gap="xs">
                          {p.regimen_laboral && (
                            <StatusBadge size="xs">
                              {p.regimen_laboral === 'losep'
                                ? 'LOSEP'
                                : 'CT'}
                            </StatusBadge>
                          )}
                          {p.plazas && p.plazas > 1 && (
                            <StatusBadge size="xs" variant="outline">
                              {p.plazas} plazas
                            </StatusBadge>
                          )}
                        </Group>
                      </Group>
                    )
                  })}
                </Stack>
              </>
            )}

            {hijos.length === 0 && puestos.length === 0 && (
              <Text size="sm" c="dimmed" ta="center" mt="xl">
                Esta unidad no tiene subprocesos ni puestos registrados.
              </Text>
            )}
          </>
        )}
      </Stack>
    </SgthDrawer>
  )
}
