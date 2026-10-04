'use client'

import { Group, Paper, Stack, Text } from '@mantine/core'
import { SectionHeading } from '@/components/ui'
import { formatFecha, formatFechaHora } from '@/lib/fecha'
import type {
  AccionSobreVinculo, VinculoConActividad,
} from '../services/actividadLaboralService'

/** Resumen legible del cambio que produjo una acción. */
function cambio(a: AccionSobreVinculo): string | null {
  if (a.unidad_destino && a.unidad_destino !== a.unidad_origen) {
    return `${a.unidad_origen ?? 'Sin unidad'} → ${a.unidad_destino}`
  }
  if (a.fecha_inicio) {
    return `${formatFecha(a.fecha_inicio)} – ${a.fecha_fin ? formatFecha(a.fecha_fin) : 'sin fecha de fin'}`
  }
  return null
}

function FilaAccion({ accion }: { accion: AccionSobreVinculo }) {
  const detalle = cambio(accion)

  return (
    <Paper withBorder p="xs" radius="sm">
      <Group justify="space-between" wrap="nowrap" align="flex-start">
        <div style={{ minWidth: 0 }}>
          <Text size="sm" fw={500}>{accion.etiqueta ?? accion.tipo_movimiento}</Text>
          {detalle && <Text size="xs" c="dimmed">{detalle}</Text>}
        </div>
        <div style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
          <Text size="xs">{formatFecha(accion.fecha_efectiva)}</Text>
          {accion.codigo_registro && (
            <Text size="xs" c="dimmed" ff="monospace">{accion.codigo_registro}</Text>
          )}
        </div>
      </Group>
    </Paper>
  )
}

/** Las acciones de personal ocurridas sobre un vínculo. */
export function AccionesSobreVinculo({ acciones }: { acciones: AccionSobreVinculo[] }) {
  return (
    <div>
      <SectionHeading title="Acciones de personal sobre este vínculo" mb="xs" />

      {acciones.length === 0 ? (
        <Text size="sm" c="dimmed">
          Sin acciones registradas sobre este vínculo.
        </Text>
      ) : (
        <Stack gap="xs">
          {acciones.map((a) => <FilaAccion key={a.id} accion={a} />)}
        </Stack>
      )}
    </div>
  )
}

/**
 * Quién movió el plazo y por qué. El plazo es lo único editable de un vínculo,
 * y moverlo cambia cuándo cesa el servidor — antes el motivo se exigía, se
 * guardaba y no lo veía nadie.
 *
 * De todo lo auditado sobre el contrato, la reprogramación es la única que
 * trae una explicación escrita por una persona. El alta y el cierre ya se
 * leen en el resto de la tarjeta.
 */
export function ReprogramacionesDelPlazo({ cambios }: { cambios: VinculoConActividad['cambios'] }) {
  const reprogramaciones = (cambios ?? []).filter((x) => x.motivo)
  if (reprogramaciones.length === 0) return null

  return (
    <div>
      <SectionHeading title="Reprogramaciones del plazo" mb="xs" />
      <Stack gap="xs">
        {reprogramaciones.map((r) => (
          <Paper key={r.id} withBorder p="xs" radius="sm">
            <Group gap="xs" wrap="nowrap" align="baseline">
              <Text size="sm" fw={500}>
                {formatFecha(r.fecha_fin_anterior)} → {formatFecha(r.fecha_fin_nueva)}
              </Text>
              <Text size="xs" c="dimmed">
                {formatFechaHora(r.fecha)}
                {r.por ? ` · ${r.por}` : ''}
              </Text>
            </Group>
            {r.motivo && <Text size="sm" mt={2}>{r.motivo}</Text>}
          </Paper>
        ))}
      </Stack>
    </div>
  )
}
