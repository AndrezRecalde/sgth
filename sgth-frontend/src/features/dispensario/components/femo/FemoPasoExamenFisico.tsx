'use client'

import { Text, Accordion, Group } from '@mantine/core'
import type { ExamenFisicoItemForm } from '../../schemas/femo.schema'
import { REGIONES_EXAMEN_FISICO } from '../../services/femoOptions'
import { ExamenFisicoRegionTable } from './ExamenFisicoRegionTable'
import { FemoSeccion } from './FemoSeccion'
import { StatusBadge } from '@/components/ui'

interface Props {
  examenFisico: ExamenFisicoItemForm[]
  onChange:     (data: ExamenFisicoItemForm[]) => void
}

export function FemoPasoExamenFisico({ examenFisico, onChange }: Props) {
  const contarAnormales = (region: string) =>
    examenFisico.filter(v => v.region === region && !v.normal).length

  return (
    <FemoSeccion letra="F" titulo="Examen físico regional">
      <Accordion multiple variant="separated" radius="md">
        {REGIONES_EXAMEN_FISICO.map((region) => {
          const anormales = contarAnormales(region.value)
          return (
            <Accordion.Item key={region.value} value={region.value}>
              <Accordion.Control>
                <Group justify="space-between" pr="sm">
                  <Text size="sm" fw={500}>{region.label}</Text>
                  {anormales > 0 && (
                    <StatusBadge tone="danger" size="xs">
                      {anormales} hallazgo{anormales !== 1 ? 's' : ''}
                    </StatusBadge>
                  )}
                </Group>
              </Accordion.Control>
              <Accordion.Panel>
                <ExamenFisicoRegionTable
                  region={region.value}
                  items={region.items}
                  valores={examenFisico}
                  onChange={onChange}
                />
              </Accordion.Panel>
            </Accordion.Item>
          )
        })}
      </Accordion>
    </FemoSeccion>
  )
}
