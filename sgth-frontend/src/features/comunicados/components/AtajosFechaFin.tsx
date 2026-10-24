'use client'

import { Button, Group, Text } from '@mantine/core'
import { sumarDias, sumarMeses } from '@/lib/fecha'

interface Props {
  /** Desde dónde se cuenta, `YYYY-MM-DD`. */
  desde: string
  /** La fecha de fin elegida, para marcar el atajo que coincide. */
  valor: string
  onElegir: (fecha: string) => void
  /** «Desde la fecha de inicio» en el formulario; «Desde hoy» al cambiarla en lo publicado. */
  etiqueta: string
}

const ATAJOS = [
  { etiqueta: '1 semana', calcular: (desde: string) => sumarDias(desde, 7) },
  { etiqueta: '15 días',  calcular: (desde: string) => sumarDias(desde, 15) },
  { etiqueta: '1 mes',    calcular: (desde: string) => sumarMeses(desde, 1) },
  { etiqueta: '3 meses',  calcular: (desde: string) => sumarMeses(desde, 3) },
] as const

/**
 * Las duraciones de siempre, para no contar días en el calendario. La fecha de
 * fin es obligatoria para publicar desde el 2026-10-24.
 */
export function AtajosFechaFin({ desde, valor, onElegir, etiqueta }: Props) {
  return (
    <Group gap={6} mt={6}>
      <Text size="xs" c="dimmed">{etiqueta}:</Text>
      {ATAJOS.map((a) => {
        const fecha = a.calcular(desde)
        return (
          <Button
            key={a.etiqueta}
            size="compact-xs"
            variant={fecha === valor ? 'light' : 'subtle'}
            onClick={() => onElegir(fecha)}
          >
            {a.etiqueta}
          </Button>
        )
      })}
    </Group>
  )
}
