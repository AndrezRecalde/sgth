'use client'

import { Drawer, Text, Skeleton, Stack } from '@mantine/core'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useViatico } from '../hooks/useViaticos'
import type { ViaticoConRelaciones } from '@/types/api'

interface Props {
  opened:  boolean
  onClose: () => void
  viatico: ViaticoConRelaciones | null
}

export function ViaticoDetalle({ opened, onClose, viatico }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const { data: detalle, isLoading } = useViatico(
    opened ? (viatico?.id ?? null) : null
  )

  return (
    <Drawer
      opened={opened}
      onClose={onClose}
      title={`Viático ${detalle?.codigo_viatico ?? '...'}`}
      position="right"
      size={isMobile ? '100%' : 'lg'}
    >
      {isLoading ? (
        <Stack gap="sm">
          <Skeleton height={40} />
          <Skeleton height={200} />
        </Stack>
      ) : (
        <Text c="dimmed" size="sm">
          Detalle completo — próxima iteración.
        </Text>
      )}
    </Drawer>
  )
}
