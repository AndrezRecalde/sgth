'use client'

import { SimpleGrid, Textarea } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { FichaBaseForm } from '../../schemas/femo.schema'
import { FemoSeccion } from './FemoSeccion'
import { SiNoSinRespuesta } from './SiNoSinRespuesta'

interface Props {
  fichaData:     Partial<FichaBaseForm>
  onFichaChange: (data: Partial<FichaBaseForm>) => void
}

/**
 * Sección N, solo en las evaluaciones de retiro. Las dos preguntas son SÍ/NO
 * en el impreso; con casillas, «sin responder» salía como «No».
 */
export function FemoRetiroSection({ fichaData, onFichaChange }: Props) {
  const contained = useContainedInput()

  return (
    <FemoSeccion letra="N" titulo="Retiro (evaluación)">
      <SimpleGrid cols={{ base: 1, sm: 2 }}>
        <SiNoSinRespuesta
          pregunta="¿Se realiza la evaluación?"
          valor={fichaData.se_realiza_evaluacion_retiro}
          onChange={(v) => onFichaChange({ ...fichaData, se_realiza_evaluacion_retiro: v })}
        />
        <SiNoSinRespuesta
          pregunta="¿La condición de salud está relacionada con el trabajo?"
          valor={fichaData.condicion_relacionada_trabajo}
          onChange={(v) => onFichaChange({ ...fichaData, condicion_relacionada_trabajo: v })}
        />
      </SimpleGrid>
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
