'use client'

import { Stepper } from '@mantine/core'

export type PasoStepper =
  | 'buscar'
  | 'elegir_accion'
  | 'completar_datos'
  | 'confirmacion'

const ORDEN_PASOS: PasoStepper[] = [
  'buscar', 'elegir_accion', 'completar_datos', 'confirmacion',
]

const ETIQUETAS: Record<PasoStepper, string> = {
  buscar:          'Buscar paciente',
  elegir_accion:   'Elegir acción',
  completar_datos: 'Completar datos',
  confirmacion:    'Confirmación',
}

interface Props {
  pasoActual: PasoStepper
}

export function FlujoStepper({ pasoActual }: Props) {
  const indiceActual = ORDEN_PASOS.indexOf(pasoActual)

  return (
    <Stepper
      active={indiceActual}
      size="sm"
      color="emerald"
      mb="lg"
    >
      {ORDEN_PASOS.map((paso) => (
        <Stepper.Step
          key={paso}
          label={ETIQUETAS[paso]}
        />
      ))}
    </Stepper>
  )
}
