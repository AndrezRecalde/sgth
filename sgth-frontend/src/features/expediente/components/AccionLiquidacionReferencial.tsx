'use client'

import { Alert, Stack, Text } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import { DetailList, SectionHeading } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { MovimientoPersonal } from '@/types/api'
import { dinero } from '../utils/dinero'
import { BloqueDetalle } from './BloqueDetalle'

/**
 * La liquidación referencial de la terminación de un obrero (fase 4.5): lo que
 * el Código del Trabajo fija para su causal —la bonificación por desahucio y,
 * en el despido, la indemnización—, sobre la remuneración y los años de
 * servicio del acto. Financiero establece el valor definitivo.
 */
export function AccionLiquidacionReferencial({ m }: { m: MovimientoPersonal }) {
  const liquidacion = m.liquidacion_referencial

  if (!liquidacion && !m.desahucio_sin_el_aviso) return null

  return (
    <Stack gap="sm">
      {m.desahucio_sin_el_aviso && (
        <Alert color="amber" variant="light" icon={<IconAlertTriangle size={16} />}>
          Rige antes de los 15 días de aviso del desahucio: la renuncia se presentó el{' '}
          {formatFecha(m.fecha_presentacion_renuncia)} (Código del Trabajo, Art. 184).
        </Alert>
      )}

      {liquidacion && (
        <BloqueDetalle>
          <SectionHeading title="Liquidación referencial" mb="xs" />
          <DetailList
            columnas={1}
            items={[
              ...liquidacion.rubros.map((r) => ({
                label: r.concepto,
                value: `${dinero(r.monto)} · ${r.detalle} (${r.base_legal})`,
              })),
              { label: 'Total referencial', value: dinero(liquidacion.total) },
            ]}
          />
          <Text size="xs" c="dimmed" mt="xs">
            Sobre una remuneración de {dinero(liquidacion.remuneracion)} y servicio desde el{' '}
            {formatFecha(liquidacion.desde)}. {liquidacion.nota}
          </Text>
        </BloqueDetalle>
      )}
    </Stack>
  )
}
