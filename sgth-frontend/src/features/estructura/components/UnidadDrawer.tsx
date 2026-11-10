'use client'

import { Stack, Text, Group, Skeleton } from '@mantine/core'
import {
  IconBuilding, IconBriefcase, IconSitemap,
} from '@tabler/icons-react'
import type { UnidadConRelaciones } from '@/types/api'
import { SectionHeading, SgthDrawer, StatusBadge } from '@/components/ui'
import { REGIMEN_LABELS } from '@/lib/regimen'
import { usePuestos } from '../hooks/usePuestos'

interface Props {
  opened: boolean
  onClose: () => void
  unidad: UnidadConRelaciones | null
  isLoading?: boolean
}

export function UnidadDrawer({ opened, onClose, unidad, isLoading }: Props) {

  const hijos    = unidad?.hijos ?? []
  // El detalle de la unidad no trae sus puestos —solo cuántos son—, así que
  // esta sección no aparecía nunca, y una unidad sin subprocesos decía que no
  // tenía puestos aunque los tuviera. Se piden al listado, que trae el cargo.
  const { data: datosPuestos, isLoading: cargandoPuestos } = usePuestos(
    unidad ? { unidad_administrativa_id: unidad.id, per_page: 100 } : undefined,
    { habilitado: opened && !!unidad },
  )
  const puestos  = datosPuestos?.data ?? []
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
                <SectionHeading
                  title={`Subprocesos (${hijos.length})`}
                  icon={<IconSitemap size={14} />}
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
                <SectionHeading
                  title={`Puestos (${puestos.length})`}
                  icon={<IconBriefcase size={14} />}
                />
                <Stack gap="xs">
                  {puestos.map((p) => {
                    return (
                      <Group
                        key={p.id}
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
                          <Text size="sm">{p.cargo?.nombre ?? '-'}</Text>
                          {p.es_jefe && <StatusBadge size="xs">Jefe</StatusBadge>}
                          {p.tipo === 'contrato' && <StatusBadge size="xs">De contrato</StatusBadge>}
                        </Group>
                        <Group gap="xs">
                          {p.regimen_laboral && (
                            <StatusBadge size="xs">
                              {REGIMEN_LABELS[p.regimen_laboral] ?? p.regimen_laboral}
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

            {hijos.length === 0 && puestos.length === 0 && !cargandoPuestos && (
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
