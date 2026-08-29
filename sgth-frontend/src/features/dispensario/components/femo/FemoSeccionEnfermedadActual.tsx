'use client'

import { Textarea } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { FichaBaseForm } from '../../schemas/femo.schema'
import { FemoSeccion } from './FemoSeccion'

interface Props {
  fichaData: Partial<FichaBaseForm>
  onFichaChange: (data: Partial<FichaBaseForm>) => void
}

/**
 * Sección D del formulario 028 — enfermedad o problema actual.
 *
 * Es una sección propia en el impreso, con su letra. Estaba metida como un
 * campo más dentro de «C. Antecedentes personales», que es otra cosa: los
 * antecedentes son historia y esto es el motivo por el que consulta hoy.
 */
export function FemoSeccionEnfermedadActual({ fichaData, onFichaChange }: Props) {
  const contained = useContainedInput()

  return (
    <FemoSeccion letra="D" titulo="Enfermedad o problema actual">
      <Textarea
        label="Descripción"
        placeholder="Motivo por el que consulta hoy"
        autosize
        minRows={3}
        {...contained}
        value={fichaData.enfermedad_actual ?? ''}
        onChange={(e) => onFichaChange({
          ...fichaData, enfermedad_actual: e.currentTarget.value,
        })}
      />
    </FemoSeccion>
  )
}
