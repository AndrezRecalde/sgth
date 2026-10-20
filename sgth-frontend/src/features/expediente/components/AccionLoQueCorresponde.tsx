'use client'

import { Alert, Stack, Text } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import { DetailList, SectionHeading } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { MovimientoPersonal } from '@/types/api'
import { dinero } from '../utils/dinero'
import { BloqueDetalle } from './BloqueDetalle'

/**
 * Lo que corresponde a la terminación de un obrero (fase 4.5): los rubros que
 * el Código del Trabajo fija para su causal —la bonificación por desahucio y,
 * en el despido, la indemnización—, con la remuneración y los años de servicio
 * del acto. Sin montos desde el 2026-10-20: la liquidación la calcula
 * Financiero.
 *
 * En su reintegro por despido ineficaz, las remuneraciones pendientes de los
 * días fuera con el 10 % de recargo (Art. 195.3; fase 4.5b).
 */
export function AccionLoQueCorresponde({ m }: { m: MovimientoPersonal }) {
  const corresponde = m.lo_que_corresponde

  if (!corresponde && !m.desahucio_sin_el_aviso) return null

  return (
    <Stack gap="sm">
      {m.desahucio_sin_el_aviso && (
        <Alert color="amber" variant="light" icon={<IconAlertTriangle size={16} />}>
          Rige antes de los 15 días de aviso del desahucio: la renuncia se presentó el{' '}
          {formatFecha(m.fecha_presentacion_renuncia)} (Código del Trabajo, Art. 184).
        </Alert>
      )}

      {corresponde && (
        <BloqueDetalle>
          <SectionHeading
            title={corresponde.tipo === 'reintegro' ? 'Lo que corresponde al reintegro' : 'Lo que corresponde a la terminación'}
            mb="xs"
          />
          <DetailList
            columnas={1}
            items={corresponde.rubros.map((r) => ({
              label: r.concepto,
              value: `${r.detalle} (${r.base_legal})`,
            }))}
          />
          <Text size="xs" c="dimmed" mt="xs">
            {corresponde.tipo === 'reintegro'
              ? `Con una remuneración de ${dinero(corresponde.remuneracion)} y `
                + (corresponde.dias_fuera
                  ? `${corresponde.dias_fuera} días fuera, del ${formatFecha(corresponde.desde)} al ${formatFecha(corresponde.hasta)}.`
                  : 'sin días fuera.')
              : `Con una remuneración de ${dinero(corresponde.remuneracion)} y servicio desde el ${formatFecha(corresponde.desde)}`
                + (corresponde.anios_servicio != null
                  ? `: ${corresponde.anios_servicio.toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} años.`
                  : '.')}
            {' '}{corresponde.nota}
          </Text>
        </BloqueDetalle>
      )}
    </Stack>
  )
}
