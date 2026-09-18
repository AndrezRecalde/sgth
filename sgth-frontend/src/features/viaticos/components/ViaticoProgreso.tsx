'use client'

import { Paper, Stepper } from '@mantine/core'
import type { ViaticoConRelaciones } from '@/types/api'

/*
| En qué paso va el viático.
|
| Sin anticipo no hay paso de anticipo: antes el paso aparecía igual y el
| viático lo «saltaba». Un viático cancelado o rechazado no se muestra aquí:
| el Stepper lo pintaba en el primer paso, como si recién empezara; el aviso
| de la cabecera dice cómo terminó.
*/

const PASOS = [
  { estado: 'solicitado',            etiqueta: 'Solicitud' },
  { estado: 'aprobado',              etiqueta: 'Aprobado' },
  { estado: 'con_anticipo',          etiqueta: 'Anticipo' },
  { estado: 'en_comision',           etiqueta: 'Comisión' },
  { estado: 'pendiente_liquidacion', etiqueta: 'Liquidación' },
  { estado: 'liquidado',             etiqueta: 'Revisión' },
  { estado: 'contabilizado',         etiqueta: 'Contabilizado' },
]

interface Props {
  viatico: ViaticoConRelaciones
}

export function ViaticoProgreso({ viatico: d }: Props) {
  const estado = String(d.estado ?? '')
  const pasos = PASOS.filter(
    (p) => p.estado !== 'con_anticipo' || d.modalidad_anticipo !== 'sin_anticipo',
  )
  const activo = pasos.findIndex((p) => p.estado === estado)

  if (activo < 0) return null

  // El último paso se da por completo: contabilizado es el cierre.
  const paso = estado === 'contabilizado' ? pasos.length : activo

  return (
    <Paper withBorder radius="lg" p="md">
      <Stepper active={paso} size="xs" allowNextStepsSelect={false}>
        {pasos.map((p) => (
          <Stepper.Step key={p.estado} label={p.etiqueta} />
        ))}
      </Stepper>
    </Paper>
  )
}
