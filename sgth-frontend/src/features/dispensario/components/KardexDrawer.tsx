'use client'

import {
  Drawer, Stack, Group, Text, Badge,
  ThemeIcon, Skeleton,
} from '@mantine/core'
import { IconHistory, IconArrowUp, IconArrowDown } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useKardexMedicina } from '../hooks/useInventarioMedicina'
import { EmptyState } from '@/components/ui/EmptyState'
import type { InventarioMedicina } from '../services/inventarioMedicinaService'

interface Props {
  opened:   boolean
  onClose:  () => void
  medicina: InventarioMedicina | null
}

function formatFecha(fecha: string): string {
  return new Date(fecha).toLocaleString('es-EC', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

export function KardexDrawer({ opened, onClose, medicina }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const { data: movimientos = [], isLoading } = useKardexMedicina(
    medicina?.id ?? null
  )

  return (
    <Drawer
      opened={opened}
      onClose={onClose}
      title={
        <Group gap="xs">
          <ThemeIcon color="blue" variant="light" size="md" radius="md">
            <IconHistory size={16} />
          </ThemeIcon>
          <Stack gap={0}>
            <Text fw={700} size="sm">Kardex de movimientos</Text>
            <Text size="xs" c="dimmed">{medicina?.nombre}</Text>
          </Stack>
        </Group>
      }
      position="right"
      size={isMobile ? '100%' : 480}
      padding="lg"
    >
      <Stack gap="sm">
        {isLoading ? (
          <>
            <Skeleton height={56} radius="md" />
            <Skeleton height={56} radius="md" />
          </>
        ) : movimientos.length === 0 ? (
          <EmptyState
            icon={IconHistory}
            title="Sin movimientos"
            description="Esta medicina aún no tiene
              movimientos registrados."
          />
        ) : (
          movimientos.map((m) => {
            const esIngreso = m.tipo_movimiento === 'ingreso'
            return (
              <Group
                key={m.id}
                justify="space-between"
                p="sm"
                style={{
                  border: '1px solid var(--mantine-color-gray-2)',
                  borderRadius: 8,
                }}
              >
                <Group gap="sm">
                  <ThemeIcon
                    color={esIngreso ? 'emerald' : 'red'}
                    variant="light"
                    size="sm"
                  >
                    {esIngreso
                      ? <IconArrowUp size={13} />
                      : <IconArrowDown size={13} />}
                  </ThemeIcon>
                  <Stack gap={0}>
                    <Text size="sm" fw={500}>{m.motivo}</Text>
                    <Text size="xs" c="dimmed">
                      {formatFecha(m.created_at)} —{' '}
                      {m.registrador?.nombre_completo
                        ?? m.registrador?.usuario_ti ?? '—'}
                    </Text>
                  </Stack>
                </Group>
                <Stack gap={0} align="flex-end">
                  <Text
                    size="sm"
                    fw={600}
                    c={esIngreso ? 'emerald' : 'red'}
                  >
                    {esIngreso ? '+' : ''}{m.cantidad}
                  </Text>
                  <Badge size="xs" variant="light" color="gray">
                    Stock: {m.stock_resultante}
                  </Badge>
                </Stack>
              </Group>
            )
          })
        )}
      </Stack>
    </Drawer>
  )
}
