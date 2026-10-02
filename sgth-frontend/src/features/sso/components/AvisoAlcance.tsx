'use client'

import { Alert, List, Text } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import type { AlcanceIndicador } from '../services/tipos'

interface Props {
  /** Los alcances que devolvió el backend, en el orden en que se quieran leer. */
  alcances: Record<string, AlcanceIndicador>
  /** Cómo se llama cada clave para quien lo lee. */
  etiquetas: Record<string, string>
}

/**
 * Las cifras del período que NO corresponden a la unidad consultada.
 *
 * Los indicadores aceptan una unidad administrativa y no todos pueden
 * honrarla: las capacitaciones no se registran por unidad, la normativa legal
 * aplica a toda la institución, las actividades del programa de drogas
 * tampoco. Antes el alcance era implícito y la respuesta se titulaba con la
 * unidad igual, así que una cifra institucional se presentaba como si fuera de
 * esa dirección — el tipo de número que acaba en un informe al Ministerio.
 *
 * El aviso aparece SOLO cuando hay algo que advertir: el backend manda la nota
 * únicamente si se pidió una unidad y el indicador no puede darla. Sin unidad
 * pedida todo es institucional y avisar de lo que nadie pidió es ruido, así
 * que el componente no pinta nada.
 */
export function AvisoAlcance({ alcances, etiquetas }: Props) {
  const conNota = Object.entries(alcances).filter(([, a]) => a.nota)

  if (!conNota.length) return null

  return (
    <Alert
      icon={<IconInfoCircle size={18} />}
      color="ocean"
      variant="light"
      title="Algunas cifras son de toda la institución"
    >
      <List size="sm" spacing={4}>
        {conNota.map(([clave, { nota }]) => (
          <List.Item key={clave}>
            <Text span fw={600}>{etiquetas[clave] ?? clave}:</Text> {nota}
          </List.Item>
        ))}
      </List>
    </Alert>
  )
}
