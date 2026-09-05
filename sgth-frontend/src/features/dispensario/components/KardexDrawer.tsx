'use client'

import { useState } from 'react'
import {
  Drawer, Stack, Group, Text, Badge,
  ThemeIcon, Skeleton, Pagination, Center, Alert,
} from '@mantine/core'
import {
  IconHistory, IconArrowUp, IconArrowDown, IconAlertTriangle,
} from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useKardexMedicina } from '../hooks/useInventarioMedicina'
import { EmptyState } from '@/components/ui/EmptyState'
import { getApiErrorMessage } from '@/types/api'
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
  const [page, setPage] = useState(1)

  // Cambiar de medicina sin volver a la primera página deja el cajón mostrando
  // la página 7 de un kardex que quizá solo tiene dos. Se ajusta durante el
  // render y no en un efecto: así no hay un primer pintado con la página vieja.
  const [medicinaVista, setMedicinaVista] = useState(medicina?.id)
  if (medicina?.id !== medicinaVista) {
    setMedicinaVista(medicina?.id)
    setPage(1)
  }

  const { data, isLoading, isError, error } = useKardexMedicina(
    medicina?.id ?? null,
    page
  )

  const movimientos  = data?.movimientos ?? []
  const ultimaPagina = data?.ultimaPagina ?? 1

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
        ) : isError ? (
          // Sin esto un fallo de red se veía igual que un kardex vacío, y decía
          // «Sin movimientos» de una medicina que sí los tiene.
          <Alert
            icon={<IconAlertTriangle size={16} />}
            color="red"
            variant="light"
            title="No se pudo cargar el kardex"
          >
            <Text size="xs">{getApiErrorMessage(error)}</Text>
          </Alert>
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
                  <Stack gap={2}>
                    <Text size="sm" fw={500}>{m.motivo}</Text>
                    {/* El lote va con la fecha y no junto al motivo: los
                        motivos de las entradas son largos —folio, documento y
                        proveedor— y ahí la insignia se cortaba. Los
                        movimientos anteriores al control por lotes no lo
                        llevan, y no se les inventa: el kardex es inmutable. */}
                    <Group gap={6} wrap="wrap">
                      <Text size="xs" c="dimmed">
                        {formatFecha(m.created_at)} —{' '}
                        {m.registrador?.nombre_completo
                          ?? m.registrador?.usuario_ti ?? '—'}
                      </Text>
                      {m.lote && (
                        <Badge size="xs" variant="outline" color="gray">
                          Lote {m.lote.codigo_lote ?? 'sin identificar'}
                        </Badge>
                      )}
                    </Group>
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

        {ultimaPagina > 1 && (
          <Stack gap={4} mt="xs">
            <Center>
              <Pagination
                size="sm"
                value={page}
                onChange={setPage}
                total={ultimaPagina}
                withEdges
              />
            </Center>
            <Text size="xs" c="dimmed" ta="center">
              {data?.total} movimiento{data?.total !== 1 ? 's' : ''} en total
            </Text>
          </Stack>
        )}
      </Stack>
    </Drawer>
  )
}
