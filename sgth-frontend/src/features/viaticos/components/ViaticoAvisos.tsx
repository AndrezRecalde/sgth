'use client'

import { Alert, List, Stack, Text } from '@mantine/core'
import { IconAlertTriangle, IconBan, IconInfoCircle, IconRoute } from '@tabler/icons-react'
import { ESTADO_LABELS } from '../constants/viatico.constants'
import { resumenRevision } from '../utils/revisionComprobantes'
import type { AccionesViatico } from '../hooks/useAccionesViatico'
import type { ViaticoConRelaciones } from '@/types/api'

const ITINERARIO_EDITABLE = ['solicitado', 'aprobado', 'con_anticipo']

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
  // Lo que le falta al itinerario: se avisa mientras se puede corregir.
  // Si cambian las fechas del viático, los tramos quedan desajustados y el
  // cambio no se bloquea (decisión del usuario, 2026-09-18).
  const itinerario = ITINERARIO_EDITABLE.includes(estado) ? d.itinerario_problemas ?? [] : []

  if (!apruebaOtro && !contabilizaOtro && !faltaRevisar && !terminado && itinerario.length === 0) return null

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
      {itinerario.length > 0 && (
        <Alert color="amber" variant="light" icon={<IconRoute size={16} />} title="Revise el itinerario">
          <List size="sm" spacing={2}>
            {itinerario.map((p) => <List.Item key={p}>{p}</List.Item>)}
          </List>
          <Text size="sm" mt={4}>Sin esto no se puede aprobar el viático.</Text>
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
