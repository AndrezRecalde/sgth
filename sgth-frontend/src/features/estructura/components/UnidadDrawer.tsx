'use client'

import {
  Drawer, Stack, Text, Badge, Group,
  Divider, Skeleton, ScrollArea,
} from '@mantine/core'
import {
  IconBuilding, IconBriefcase, IconSitemap,
} from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import type { UnidadConRelaciones } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  unidad: UnidadConRelaciones | null
  isLoading?: boolean
}

const TIPO_COLORS: Record<string, string> = {
  G:   'blue',
  HAP: 'orange',
  HA:  'violet',
  AV:  'emerald',
}

export function UnidadDrawer({ opened, onClose, unidad, isLoading }: Props) {
  const { isMobile } = useMobileBreakpoint()

  const hijos    = unidad?.hijos ?? []
  const puestos  = unidad?.puestos ?? []
  const tipoAcronimo = unidad?.tipo_unidad?.acronimo ?? ''

  return (
    <Drawer
      opened={opened}
      onClose={onClose}
      title={
        <Group gap="xs">
          <IconBuilding size={18} />
          <Text fw={700} size="md">
            {unidad?.nombre ?? 'Unidad'}
          </Text>
        </Group>
      }
      position="right"
      size={isMobile ? '100%' : 520}
      padding="lg"
    >
      <ScrollArea h="calc(100vh - 80px)">
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
                <Badge
                  color={TIPO_COLORS[tipoAcronimo] ?? 'gray'}
                  variant="light"
                  size="sm"
                >
                  {unidad?.tipo_unidad?.descripcion ?? tipoAcronimo}
                </Badge>
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
                        key={hijo.id as unknown as number}
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
                      const p = puesto as unknown as {
                        id: number
                        denominacion?: string
                        regimen_laboral?: string
                        plazas?: number
                      }
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
                              <Badge
                                size="xs"
                                variant="light"
                                color={
                                  p.regimen_laboral === 'losep'
                                    ? 'emerald'
                                    : 'blue'
                                }
                              >
                                {p.regimen_laboral === 'losep'
                                  ? 'LOSEP'
                                  : 'CT'}
                              </Badge>
                            )}
                            {p.plazas && p.plazas > 1 && (
                              <Badge size="xs" variant="outline" color="gray">
                                {p.plazas} plazas
                              </Badge>
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
      </ScrollArea>
    </Drawer>
  )
}
