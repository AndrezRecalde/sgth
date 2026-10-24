'use client'

import { Alert, Stack, Text } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import { MOTIVOS_NO_ENTREGADO } from '../schemas/noEntregado.schema'
import { esItemExterno, nombreDeItem, type ItemReceta } from '../services/recetaService'

/**
 * Lo que el paciente adquiere fuera del Dispensario: lo que la farmacia no
 * maneja y lo que el mostrador marcó «no entregado» (decisión del 2026-10-24).
 * Va antes de la lista de entrega: quien atiende tiene que poder decírselo al
 * paciente de entrada.
 */
export function AdquirirFueraAlert({ items }: { items: ItemReceta[] }) {
  if (items.length === 0) return null

  const motivo = (m?: string | null) => MOTIVOS_NO_ENTREGADO.find((x) => x.value === m)?.label.toLowerCase()

  return (
    <Alert icon={<IconAlertTriangle size={14} />} color="amber" variant="light"
      title={items.length === 1 ? 'Un medicamento se adquiere fuera' : `${items.length} medicamentos se adquieren fuera`}>
      <Stack gap={4} mt={4}>
        {items.map((item) => {
          const falta = item.cantidad_prescrita - (item.cantidad_despachada ?? 0)
          return (
            <Text size="xs" key={item.id}>
              <Text span fw={600} c="inherit">{nombreDeItem(item)}</Text>
              {' — '}
              {esItemExterno(item)
                ? `${item.cantidad_prescrita} unid. · no lo maneja la farmacia`
                : `${falta} unid. · no entregado (${motivo(item.motivo_no_entregado) ?? 'sin motivo'})`
                  + (item.detalle_no_entregado ? `: ${item.detalle_no_entregado}` : '')}
            </Text>
          )
        })}
        <Text size="xs" c="dimmed">No se despacha ni descuenta existencias. El impreso de la receta lo indica.</Text>
      </Stack>
    </Alert>
  )
}
