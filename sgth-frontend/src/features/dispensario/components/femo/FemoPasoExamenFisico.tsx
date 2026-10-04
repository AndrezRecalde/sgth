'use client'

import { Text, Accordion, Group, Textarea } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { FichaBaseForm } from '../../schemas/femo.schema'
import type { ExamenFisicoItemForm } from '../../schemas/femoEvaluacion.schema'
import { REGIONES_EXAMEN_FISICO } from '../../services/femoOptions'
import { ExamenFisicoRegionTable } from './ExamenFisicoRegionTable'
import { FemoSeccion } from './FemoSeccion'
import { StatusBadge } from '@/components/ui'

interface Props {
  examenFisico:  ExamenFisicoItemForm[]
  onChange:      (data: ExamenFisicoItemForm[]) => void
  fichaData:     Partial<FichaBaseForm>
  onFichaChange: (data: Partial<FichaBaseForm>) => void
}

export function FemoPasoExamenFisico({
  examenFisico, onChange, fichaData, onFichaChange,
}: Props) {
  const contained = useContainedInput()

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

      <Textarea
        label="Observación"
        description="Observación de la sección F; los hallazgos de cada ítem ya van con su numeral"
        autosize
        minRows={2}
        {...contained}
        value={fichaData.observacion_examen_fisico ?? ''}
        onChange={(e) => onFichaChange({
          ...fichaData, observacion_examen_fisico: e.currentTarget.value,
        })}
      />
    </FemoSeccion>
  )
}
