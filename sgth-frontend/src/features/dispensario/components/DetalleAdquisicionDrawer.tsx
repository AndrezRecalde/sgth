'use client'

import {
  Drawer, Stack, Group, Text, ThemeIcon, Divider, Alert, Button,
} from '@mantine/core'
import {
  IconShoppingCart, IconFileText, IconBan, IconFileSearch,
} from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useDescargarDocumentoAdquisicion } from '../hooks/useAdquisicion'
import type { Adquisicion } from '../services/adquisicionService'
import { DetailList, SgthTable, StatusBadge } from '@/components/ui'
import { columnasItemsAdquisicion } from './itemsAdquisicion.columns'

interface Props {
  opened:      boolean
  onClose:     () => void
  adquisicion: Adquisicion | null
}

function formatFecha(fecha?: string | null): string {
  if (!fecha) return '—'
  return new Date(fecha).toLocaleDateString('es-EC', {
    day: '2-digit', month: 'short', year: 'numeric',
  })
}

export function DetalleAdquisicionDrawer({
  opened, onClose, adquisicion,
}: Props) {
  const { isMobile } = useMobileBreakpoint()
  const descargarDocumento = useDescargarDocumentoAdquisicion()

  return (
    <Drawer
      opened={opened}
      onClose={onClose}
      title={
        <Group gap="xs">
          <ThemeIcon
            color={adquisicion?.tipo === 'donacion' ? 'violet' : 'blue'}
            variant="light"
            size="md"
            radius="md"
          >
            <IconShoppingCart size={16} />
          </ThemeIcon>
          <Stack gap={0}>
            <Text fw={700} size="sm">
              Detalle de adquisición
            </Text>
            <Text size="xs" c="dimmed" ff="monospace">
              {adquisicion?.folio}
            </Text>
          </Stack>
        </Group>
      }
      position="right"
      size={isMobile ? '100%' : 580}
      padding="lg"
    >
      {adquisicion && (
        <Stack gap="md">
          {adquisicion.anulado_en && (
            <Alert
              icon={<IconBan size={16} />}
              color="orange"
              variant="light"
              title="Adquisición anulada"
            >
              <Text size="xs">
                {adquisicion.motivo_anulacion}
                {' — '}
                {adquisicion.anulador?.nombre_completo
                  ?? adquisicion.anulador?.usuario_ti ?? '—'}
                {', '}
                {formatFecha(adquisicion.anulado_en)}
              </Text>
              <Text size="xs" mt={4} c="dimmed">
                Lo que aportó se devolvió al inventario con su contrapartida en
                el kardex.
              </Text>
            </Alert>
          )}

          <DetailList
            columnas={2}
            items={[
              { label: 'Tipo', value: <StatusBadge>{adquisicion.tipo === 'donacion' ? 'Donación' : 'Compra'}</StatusBadge> },
              { label: 'N.° de documento', value: <Text size="sm" ff="monospace">{adquisicion.numero_documento}</Text> },
              { label: 'Proveedor / donante', value: adquisicion.proveedor_o_donante },
              { label: 'Fecha', value: formatFecha(adquisicion.fecha_adquisicion) },
              {
                label: 'Registrado por',
                value: adquisicion.registrador?.nombre_completo ?? adquisicion.registrador?.usuario_ti,
              },
              {
                label: 'Documento de respaldo',
                value: adquisicion.documento_respaldo ? (
                  <Button
                    size="compact-xs"
                    variant="light"
                    leftSection={<IconFileSearch size={12} />}
                    loading={descargarDocumento.isPending}
                    onClick={() => descargarDocumento.mutate({
                      id: adquisicion.id, folio: adquisicion.folio,
                    })}
                  >
                    Descargar
                  </Button>
                ) : (
                  <StatusBadge tone="warning" variant="outline" rightSection={<IconFileText size={12} />}>
                    Pendiente
                  </StatusBadge>
                ),
              },
              ...(adquisicion.observaciones
                ? [{ label: 'Observaciones', value: adquisicion.observaciones, ancho: true }]
                : []),
            ]}
          />

          <Divider
            label={
              <Text size="xs" fw={600} tt="uppercase" c="dimmed">
                Medicamentos ({adquisicion.items?.length ?? 0} ítems)
              </Text>
            }
            labelPosition="left"
          />

          <SgthTable
            records={adquisicion.items ?? []}
            columns={columnasItemsAdquisicion}
            minHeight={100}
            noRecordsText="La adquisición no tiene medicamentos"
          />
        </Stack>
      )}
    </Drawer>
  )
}
