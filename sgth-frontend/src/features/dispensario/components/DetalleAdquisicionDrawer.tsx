'use client'

import { Stack, Text, Alert, Button } from '@mantine/core'
import { IconFileText, IconBan, IconFileSearch } from '@tabler/icons-react'
import { useDescargarDocumentoAdquisicion } from '../hooks/useAdquisicion'
import type { Adquisicion } from '../services/adquisicionService'
import { DetailList, SectionHeading, SgthDrawer, SgthTable, StatusBadge } from '@/components/ui'
import { columnasItemsAdquisicion } from './itemsAdquisicion.columns'
import { formatFechaMes } from '@/lib/fecha'

interface Props {
  opened:      boolean
  onClose:     () => void
  adquisicion: Adquisicion | null
}

export function DetalleAdquisicionDrawer({
  opened, onClose, adquisicion,
}: Props) {
  const descargarDocumento = useDescargarDocumentoAdquisicion()

  return (
    <SgthDrawer
      opened={opened}
      onClose={onClose}
      title="Detalle de adquisición"
      description={adquisicion?.folio}
    >
      {adquisicion && (
        <Stack gap="md">
          {adquisicion.anulado_en && (
            <Alert
              icon={<IconBan size={16} />}
              color="amber"
              variant="light"
              title="Adquisición anulada"
            >
              <Text size="xs">
                {adquisicion.motivo_anulacion}
                {' — '}
                {adquisicion.anulador?.nombre_completo
                  ?? adquisicion.anulador?.usuario_ti ?? '—'}
                {', '}
                {formatFechaMes(adquisicion.anulado_en)}
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
              { label: 'Fecha', value: formatFechaMes(adquisicion.fecha_adquisicion) },
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

          <SectionHeading
            title={`Medicamentos (${adquisicion.items?.length ?? 0} ítems)`}
          />

          <SgthTable
            records={adquisicion.items ?? []}
            columns={columnasItemsAdquisicion}
            minHeight={100}
            noRecordsText="La adquisición no tiene medicamentos"
          />
        </Stack>
      )}
    </SgthDrawer>
  )
}
