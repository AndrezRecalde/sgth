'use client'

import { Checkbox, Select, Stack, Text } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import { formatFechaMes } from '@/lib/fecha'
import { NINGUNA } from '../../schemas/resultadoMedico.schema'
import type { OrdenExamen } from '../../services/examenService'

interface Props {
  /** Las abiertas del paciente: a una completa o anulada no se le cuelga nada. */
  ordenes:    OrdenExamen[]
  ordenId:    string
  onOrden:    (id: string) => void
  items:      number[]
  onItems:    (ids: number[]) => void
  errorOrden?: string
  errorItems?: string
}

/** De qué orden es el resultado y qué exámenes de ella cubre el archivo. */
export function OrdenDelResultado({ ordenes, ordenId, onOrden, items, onItems, errorOrden, errorItems }: Props) {
  const contained = useContainedInput()
  const orden = ordenes.find((o) => String(o.id) === ordenId)

  return (
    <Stack gap="xs">
      <Select
        label="Orden de exámenes"
        description="Si el paciente trae lo que se le pidió, elija la orden y marque qué cubre el archivo."
        allowDeselect={false}
        {...contained}
        data={[
          { value: NINGUNA, label: 'Sin orden' },
          ...ordenes.map((o) => ({
            value: String(o.id),
            label: `${o.folio} · ${formatFechaMes(o.fecha_solicitud)} · `
              + `${o.items.filter((i) => i.estado === 'pendiente').length} pendientes`,
          })),
        ]}
        value={ordenId}
        onChange={(v) => onOrden(v ?? NINGUNA)}
        error={errorOrden}
      />

      {orden && (
        <Checkbox.Group label="Exámenes que cubre el archivo" value={items.map(String)}
          onChange={(v) => onItems(v.map(Number))} error={errorItems}>
          <Stack gap={6} mt={6}>
            {orden.items.map((i) => (
              <Checkbox
                key={i.id}
                size="xs"
                value={String(i.id)}
                // Uno ya recibido no se cubre dos veces: si el archivo
                // anterior estaba mal, se retira primero.
                disabled={i.estado === 'recibido'}
                label={
                  <Text size="xs">
                    {i.examen?.nombre}{i.nota ? ` (${i.nota})` : ''}
                    {i.estado === 'recibido' && <Text span c="dimmed"> · ya recibido</Text>}
                    {i.estado === 'no_realizado' && <Text span c="dimmed"> · marcado no realizado</Text>}
                  </Text>
                }
              />
            ))}
          </Stack>
        </Checkbox.Group>
      )}
    </Stack>
  )
}
