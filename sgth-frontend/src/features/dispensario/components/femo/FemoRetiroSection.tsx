'use client'

import { Group, Checkbox, Textarea } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { FichaBaseForm } from '../../schemas/femo.schema'
import { FemoSeccion } from './FemoSeccion'

interface Props {
  fichaData:     Partial<FichaBaseForm>
  onFichaChange: (data: Partial<FichaBaseForm>) => void
}

export function FemoRetiroSection({ fichaData, onFichaChange }: Props) {
  const contained = useContainedInput()

  return (
    <FemoSeccion letra="N" titulo="Retiro (evaluación)">
      <Group gap="md">
        <Checkbox
          label="Se realiza la evaluación"
          checked={fichaData.se_realiza_evaluacion_retiro ?? false}
          onChange={(e) => onFichaChange({
            ...fichaData, se_realiza_evaluacion_retiro: e.currentTarget.checked,
          })}
        />
        <Checkbox
          label="La condición de salud está relacionada con el trabajo"
          checked={fichaData.condicion_relacionada_trabajo ?? false}
          onChange={(e) => onFichaChange({
            ...fichaData, condicion_relacionada_trabajo: e.currentTarget.checked,
          })}
        />
      </Group>
      <Textarea
        label="Observación"
        autosize
        minRows={2}
        {...contained}
        value={fichaData.observacion_retiro ?? ''}
        onChange={(e) => onFichaChange({
          ...fichaData, observacion_retiro: e.currentTarget.value,
        })}
      />
    </FemoSeccion>
  )
}
