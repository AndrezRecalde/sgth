'use client'

import { Alert, Stack } from '@mantine/core'
import { IconAlertTriangle, IconBan, IconInfoCircle } from '@tabler/icons-react'
import { ESTADO_LABELS } from '../constants/viatico.constants'
import { resumenRevision } from '../utils/revisionComprobantes'
import type { AccionesViatico } from '../hooks/useAccionesViatico'
import type { ViaticoConRelaciones } from '@/types/api'

interface Props {
  viatico: ViaticoConRelaciones
  puede:   AccionesViatico
}

/**
 * Lo que hay que saber antes de actuar, bajo la cabecera: por qué falta un
 * botón, qué impide contabilizar y por qué terminó un viático cancelado o
 * rechazado. Antes quedaba al pie de la página, debajo de los botones.
 */
export function ViaticoAvisos({ viatico: d, puede }: Props) {
  const estado = String(d.estado ?? '')
  const revision = resumenRevision(d.liquidacion?.detalles_factura)

  // A quien aprueba o contabiliza pero viaja en este viático se le dice por
  // qué no tiene el botón, en vez de solo ocultárselo.
  const apruebaOtro = puede.apruebaPeroViaja(d)
  const contabilizaOtro = puede.revisarLiquidacion(d) && !puede.contabilizar(d)
  const faltaRevisar = puede.contabilizar(d) && !revision.completa
  const terminado = estado === 'cancelado' || estado === 'rechazado'

  if (!apruebaOtro && !contabilizaOtro && !faltaRevisar && !terminado) return null

  return (
    <Stack gap="xs">
      {terminado && (
        <Alert color="red" variant="light" icon={<IconBan size={16} />} title={`Viático ${ESTADO_LABELS[estado]?.toLowerCase()}`}>
          {d.motivo_rechazo ? `Motivo: ${d.motivo_rechazo}` : 'No se registró un motivo.'}
        </Alert>
      )}
      {(apruebaOtro || contabilizaOtro) && (
        <Alert color="slate" variant="light" icon={<IconInfoCircle size={16} />}>
          Viaja en este viático: {apruebaOtro ? 'aprobarlo o rechazarlo' : 'contabilizarlo'} le
          corresponde a otra persona de Financiero.
        </Alert>
      )}
      {faltaRevisar && (
        <Alert color="amber" variant="light" icon={<IconAlertTriangle size={16} />}>
          Para contabilizar, acepte todos los comprobantes
          {revision.pendientes > 0 ? ` (faltan ${revision.pendientes} por revisar)` : ''}.
        </Alert>
      )}
    </Stack>
  )
}
